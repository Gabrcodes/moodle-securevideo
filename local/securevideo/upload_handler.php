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

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_sesskey();
$context = context_system::instance();
require_capability('local/securevideo:manage', $context);

$videoname = required_param('videoname', PARAM_TEXT);

// Prevent duplicates.
if ($DB->record_exists('local_securevideo_videos', ['name' => trim($videoname)])) {
    redirect(
        new moodle_url('/local/securevideo/manage.php'),
        'A video named "' . s(trim($videoname)) . '" already exists. Use a different name or delete the existing one first.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

if (empty($_FILES['videofile']) || $_FILES['videofile']['error'] !== UPLOAD_ERR_OK) {
    redirect(
        new moodle_url('/local/securevideo/manage.php'),
        'Upload failed. Please try again.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$uploadedfile = $_FILES['videofile'];
$ext = strtolower(pathinfo($uploadedfile['name'], PATHINFO_EXTENSION));
$allowed_ext = ['mp4', 'webm', 'mkv', 'mov', 'avi'];
if (!in_array($ext, $allowed_ext)) {
    redirect(
        new moodle_url('/local/securevideo/manage.php'),
        'Invalid file type. Allowed: ' . implode(', ', $allowed_ext),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// MIME type validation (magic bytes, not just extension).
$allowed_mimes = [
    'video/mp4', 'video/webm', 'video/x-matroska', 'video/quicktime',
    'video/x-msvideo', 'video/mpeg',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedmime = $finfo->file($uploadedfile['tmp_name']);
if (!in_array($detectedmime, $allowed_mimes)) {
    redirect(
        new moodle_url('/local/securevideo/manage.php'),
        'File content does not appear to be a valid video (detected: ' . s($detectedmime) . ').',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// File size limit (500 MB).
$maxsize = 500 * 1024 * 1024;
if ($uploadedfile['size'] > $maxsize) {
    redirect(
        new moodle_url('/local/securevideo/manage.php'),
        'File too large. Maximum 500 MB.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Safe filename: use only generated ID + validated extension (no original name).
$safename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$destpath = local_securevideo_get_original_path() . '/' . $safename;

if (!move_uploaded_file($uploadedfile['tmp_name'], $destpath)) {
    redirect(
        new moodle_url('/local/securevideo/manage.php'),
        'Failed to save uploaded file.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Create DB record.
$record = new stdClass();
$record->name = $videoname;
$record->filename = $safename;
$record->status = 'pending';
$record->filesize = $uploadedfile['size'];
$record->createdby = $USER->id;
$record->timecreated = time();
$record->timemodified = time();
$videoid = $DB->insert_record('local_securevideo_videos', $record);

// Trigger HLS conversion in background.
$phpbin = PHP_BINARY ?: 'php';
$scriptpath = __DIR__ . '/convert.php';
$cmd = escapeshellarg($phpbin) . ' ' . escapeshellarg($scriptpath) . ' ' . (int)$videoid;
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // On Windows, wrap in cmd /c to safely handle the redirects.
    pclose(popen('cmd /c "' . $cmd . ' > NUL 2>&1"', 'r'));
} else {
    exec($cmd . ' > /dev/null 2>&1 &');
}

redirect(
    new moodle_url('/local/securevideo/manage.php'),
    'Video uploaded! HLS conversion started in background.',
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
