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
 * Security event reporting endpoint.
 * Receives events from the video player via sendBeacon.
 * Uses JSON logging (no string interpolation) to prevent log injection.
 */
require_once(__DIR__ . '/../../config.php');

// Must be logged in.
if (!isloggedin() || isguestuser()) {
    http_response_code(403);
    die();
}

// Rate limit: max 30 reports per minute per user (stored in session).
$ratekey = 'securevideo_report_' . $USER->id;
$now = time();
$cache = cache::make('core', 'session');
$ratelog = $cache->get($ratekey);
if (!is_array($ratelog)) {
    $ratelog = [];
}
// Prune entries older than 60 seconds.
$ratelog = array_filter($ratelog, function($t) use ($now) { return $t > $now - 60; });
if (count($ratelog) >= 30) {
    http_response_code(429);
    die();
}
$ratelog[] = $now;
$cache->set($ratekey, $ratelog);

// Parse input.
$input = file_get_contents('php://input');
if (strlen($input) > 4096) {
    http_response_code(413);
    die();
}
$data = json_decode($input, true);

if (!$data || empty($data['videoid']) || empty($data['event'])) {
    http_response_code(400);
    die();
}

// Strict sanitization — only allow known event types.
$videoid = (int)$data['videoid'];
$allowed_events = [
    'screen_capture_api', 'screen_capture_permission', 'screen_capture_permission_change',
    'webrtc_screen_share', 'recording_extension', 'devtools_opened',
    'tab_hidden', 'suspicious_blur', 'print_screen', 'print_attempt',
    'blocked_shortcut', 'watermark_tamper', 'watermark_hidden', 'heartbeat',
];
$event = clean_param($data['event'], PARAM_ALPHANUMEXT);
if (!in_array($event, $allowed_events)) {
    $event = 'unknown';
}

$code = isset($data['code']) ? clean_param(substr($data['code'], 0, 20), PARAM_ALPHANUMEXT) : '';
$details = isset($data['details']) ? clean_param(substr($data['details'], 0, 200), PARAM_ALPHANUMEXT) : '';
$violations = isset($data['violations']) ? min(9999, max(0, (int)$data['violations'])) : 0;
$videotime = isset($data['time']) ? min(86400, max(0, (int)$data['time'])) : 0;

// Verify the video exists.
$video = $DB->get_record('local_securevideo_videos', ['id' => $videoid]);
if (!$video) {
    http_response_code(404);
    die();
}

// Log as JSON (one object per line — no string concatenation, prevents injection).
$logfile = $CFG->dataroot . '/securevideo/security_events.log';
$logdir = dirname($logfile);
if (!is_dir($logdir)) {
    mkdir($logdir, 0770, true);
}

$logentry = json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'userid' => (int)$USER->id,
    'username' => $USER->username,
    'fullname' => fullname($USER),
    'videoid' => $videoid,
    'event' => $event,
    'accesscode' => $code,
    'details' => $details,
    'violations' => $violations,
    'videotime' => $videotime,
    'ip' => getremoteaddr(),
], JSON_UNESCAPED_UNICODE) . "\n";

file_put_contents($logfile, $logentry, FILE_APPEND | LOCK_EX);

http_response_code(204);
