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
 * Chunked upload handler.
 * Receives individual file chunks, assembles them, then triggers HLS conversion.
 * Called multiple times per file (once per chunk), with several chunks in parallel.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_sesskey();
$context = context_system::instance();
require_capability('local/securevideo:manage', $context);

header('Content-Type: application/json');

// Dropzone sends these parameter names automatically.
$uuid        = required_param('dzuuid',            PARAM_ALPHANUMEXT);
$chunkindex  = required_param('dzchunkindex',      PARAM_INT);
$totalchunks = required_param('dztotalchunkcount', PARAM_INT);
$videoname   = required_param('videoname',         PARAM_TEXT);
$dzfilename  = required_param('dzfilename',        PARAM_TEXT);
$ext         = strtolower(pathinfo($dzfilename, PATHINFO_EXTENSION));

// Validate extension.
$allowed_ext = ['mp4', 'webm', 'mkv', 'mov', 'avi'];
if (!in_array(strtolower($ext), $allowed_ext)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type.']);
    exit;
}

// Validate values.
if ($totalchunks < 1 || $totalchunks > 10000 || $chunkindex < 0 || $chunkindex >= $totalchunks) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid chunk parameters.']);
    exit;
}

// Read chunk from uploaded file (sent as FormData — avoids php://input being
// consumed by Moodle's bootstrap before we can read it).
// Dropzone sends the chunk as the 'file' field.
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $uploaderr = $_FILES['file']['error'] ?? 'missing';
    http_response_code(400);
    echo json_encode(['error' => 'Chunk file missing or upload error: ' . $uploaderr]);
    exit;
}

if ($_FILES['file']['size'] > 6 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Chunk too large.']);
    exit;
}

$chunkdata = file_get_contents($_FILES['file']['tmp_name']);
if ($chunkdata === false || strlen($chunkdata) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty chunk.']);
    exit;
}

// Temp directory for this upload session.
$tmpdir = sys_get_temp_dir() . '/sv_upload_' . $uuid;
if (!is_dir($tmpdir)) {
    mkdir($tmpdir, 0700, true);
}

// Write this chunk.
$chunkfile = $tmpdir . '/chunk_' . sprintf('%05d', $chunkindex);
file_put_contents($chunkfile, $chunkdata);

// Check how many chunks we have so far.
$received = count(glob($tmpdir . '/chunk_*'));

if ($received < $totalchunks) {
    // Not all chunks received yet — acknowledge and wait.
    echo json_encode(['status' => 'chunk_received', 'received' => $received, 'total' => $totalchunks]);
    exit;
}

// All chunks received — assemble the file.
$safename = time() . '_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
$destpath = local_securevideo_get_original_path() . '/' . $safename;

$out = fopen($destpath, 'wb');
if (!$out) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not create destination file.']);
    exit;
}

for ($i = 0; $i < $totalchunks; $i++) {
    $chunkfile = $tmpdir . '/chunk_' . sprintf('%05d', $i);
    if (!file_exists($chunkfile)) {
        fclose($out);
        unlink($destpath);
        http_response_code(500);
        echo json_encode(['error' => 'Missing chunk ' . $i]);
        exit;
    }
    $data = file_get_contents($chunkfile);
    fwrite($out, $data);
    unlink($chunkfile);
}
fclose($out);
rmdir($tmpdir);

$filesize = filesize($destpath);

// MIME validation on assembled file.
$allowed_mimes = [
    'video/mp4', 'video/webm', 'video/x-matroska', 'video/quicktime',
    'video/x-msvideo', 'video/mpeg',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedmime = $finfo->file($destpath);
if (!in_array($detectedmime, $allowed_mimes)) {
    unlink($destpath);
    http_response_code(400);
    echo json_encode(['error' => 'File does not appear to be a valid video (detected: ' . $detectedmime . ').']);
    exit;
}

// Create DB record.
$record = new stdClass();
$record->name = $videoname;
$record->filename = $safename;
$record->status = 'pending';
$record->filesize = $filesize;
$record->createdby = $USER->id;
$record->timecreated = time();
$record->timemodified = time();
$videoid = $DB->insert_record('local_securevideo_videos', $record);

// Trigger HLS conversion in background.
$phpbin = PHP_BINARY ?: 'php';
$scriptpath = __DIR__ . '/convert.php';
$cmd = escapeshellarg($phpbin) . ' ' . escapeshellarg($scriptpath) . ' ' . (int)$videoid;
exec($cmd . ' > /dev/null 2>&1 &');

echo json_encode([
    'status' => 'complete',
    'videoid' => (int)$videoid,
    'name' => $videoname,
    'size' => $filesize,
]);
