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
 * CLI script: downloads a video from a URL and triggers HLS conversion.
 * Usage: php url_download.php <videoid> <url>
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

if ($argc < 3) {
    cli_error('Usage: php url_download.php <videoid> <urlfile>');
}

$videoid = (int)$argv[1];
$urlfile = $argv[2];

// Read URL from temp file (avoids shell escaping issues with long signed URLs).
if (!file_exists($urlfile)) {
    cli_error("URL file not found: {$urlfile}");
}
$url = trim(file_get_contents($urlfile));
unlink($urlfile); // Clean up temp file.

if (empty($url)) {
    cli_error('URL is empty.');
}

$video = $DB->get_record('local_securevideo_videos', ['id' => $videoid]);
if (!$video) {
    cli_error("Video {$videoid} not found.");
}

$destpath = local_securevideo_get_original_path() . '/' . $video->filename;

// Mark as downloading.
$DB->set_field('local_securevideo_videos', 'status', 'converting', ['id' => $videoid]);
$DB->set_field('local_securevideo_videos', 'timemodified', time(), ['id' => $videoid]);

mtrace("Downloading: {$url}");
mtrace("Destination: {$destpath}");

// Download using curl.
$ch = curl_init($url);
$fp = fopen($destpath, 'wb');
if (!$fp) {
    $DB->set_field('local_securevideo_videos', 'status', 'error', ['id' => $videoid]);
    cli_error("Could not open destination file: {$destpath}");
}

curl_setopt_array($ch, [
    CURLOPT_FILE           => $fp,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 3600,      // 1 hour max
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_USERAGENT      => 'Mozilla/5.0',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FAILONERROR    => true,
]);

$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);
fclose($fp);

if (!$result || $httpcode >= 400) {
    $DB->set_field('local_securevideo_videos', 'status', 'error', ['id' => $videoid]);
    if (file_exists($destpath)) {
        unlink($destpath);
    }
    cli_error("Download failed: HTTP {$httpcode} — {$error}");
}

$filesize = filesize($destpath);
mtrace("Download complete: " . round($filesize / 1048576, 1) . " MB");

// Update filesize.
$DB->set_field('local_securevideo_videos', 'filesize', $filesize, ['id' => $videoid]);

// Now trigger HLS conversion.
$phpbin     = PHP_BINARY ?: 'php';
$scriptpath = __DIR__ . '/convert.php';
$cmd = escapeshellarg($phpbin) . ' ' . escapeshellarg($scriptpath) . ' ' . (int)$videoid;
mtrace("Starting conversion: {$cmd}");

// Run conversion synchronously (we're already in the background).
$cmdoutput = [];
$ret = null;
exec($cmd . ' 2>&1', $cmdoutput, $ret);

if ($ret !== 0) {
    mtrace("Conversion failed with exit code {$ret}");
    mtrace(implode("\n", $cmdoutput));
} else {
    mtrace("Conversion complete.");
}

mtrace("Done.");
