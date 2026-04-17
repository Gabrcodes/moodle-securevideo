<?php
// This file is part of the Secure Video Player plugin for Moodle.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Secure video proxy - serves HLS segments and encryption keys.
 * Validates Moodle session + HMAC token before serving any file.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login(null, false);

$videoid = required_param('id', PARAM_INT);
$file = required_param('file', PARAM_TEXT);
// Immediately validate against strict whitelist — blocks path traversal.
$file = basename($file);
if (!preg_match('/^(enc\.key|stream\.m3u8|seg_\d{4}\.ts)$/', $file)) {
    http_response_code(400);
    die('Invalid file.');
}
$token = required_param('token', PARAM_RAW);

// Validate token.
if (!local_securevideo_verify_token($videoid, $USER->id, $token)) {
    http_response_code(403);
    die('Access denied: invalid or expired token.');
}

// Check capability.
$context = context_system::instance();
if (isguestuser()) {
    http_response_code(403);
    die('Access denied.');
}

// Validate video exists and is ready.
$video = $DB->get_record('local_securevideo_videos', ['id' => $videoid, 'status' => 'ready']);
if (!$video) {
    http_response_code(404);
    die('Video not found.');
}

$hlsdir = local_securevideo_get_hls_path($videoid);

// Serve encryption key.
if ($file === 'enc.key') {
    $keypath = $hlsdir . '/enc.key';
    if (!file_exists($keypath)) {
        http_response_code(404);
        die('Key not found.');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($keypath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    readfile($keypath);
    exit;
}

$filepath = $hlsdir . '/' . $file;
if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found.');
}

// For m3u8 playlist, rewrite segment URLs to go through this proxy.
if ($file === 'stream.m3u8') {
    $content = file_get_contents($filepath);

    // Build base URL directly from $CFG->wwwroot — do NOT use moodle_url->out()
    // because moodle_url HTML-encodes ampersands (&amp;) which breaks HLS.js URL parsing.
    $baseurl = rtrim($CFG->wwwroot, '/') . '/local/securevideo/serve.php';

    // Build key URL using & not &amp;.
    $keyurl = $baseurl . '?id=' . $videoid . '&file=enc.key&token=' . urlencode($token);

    // Replace key URL — handle both the SERVE_KEY_URL placeholder (from convert.php)
    // and the raw "enc.key" relative path (fallback if placeholder replacement failed).
    $content = preg_replace(
        '/URI="(?:SERVE_KEY_URL|enc\.key)"/',
        'URI="' . $keyurl . '"',
        $content
    );

    // Replace segment filenames with proxied URLs — use & not &amp;.
    $content = preg_replace_callback('/^(seg_\d{4}\.ts)$/m', function($matches) use ($baseurl, $videoid, $token) {
        return $baseurl . '?id=' . $videoid . '&file=' . $matches[1] . '&token=' . urlencode($token);
    }, $content);

    header('Content-Type: application/vnd.apple.mpegurl');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo $content;
    exit;
}

// Serve .ts segment.
header('Content-Type: video/mp2t');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Accept-Ranges: none');
readfile($filepath);
