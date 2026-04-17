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
 * CLI script: converts uploaded video to HLS segments.
 * Usage: php convert.php <videoid>
 */
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

if ($argc < 2) {
    cli_error('Usage: php convert.php <videoid>');
}

$videoid = (int)$argv[1];
$video = $DB->get_record('local_securevideo_videos', ['id' => $videoid]);
if (!$video) {
    cli_error("Video ID {$videoid} not found.");
}

// Mark as converting.
$DB->set_field('local_securevideo_videos', 'status', 'converting', ['id' => $videoid]);
$DB->set_field('local_securevideo_videos', 'timemodified', time(), ['id' => $videoid]);

$ffmpeg = get_config('local_securevideo', 'ffmpegpath') ?: '/usr/bin/ffmpeg';
$inputfile = local_securevideo_get_original_path() . '/' . $video->filename;
$hlsdir = local_securevideo_get_hls_path($videoid);

if (!file_exists($inputfile)) {
    $DB->set_field('local_securevideo_videos', 'status', 'error', ['id' => $videoid]);
    cli_error("Input file not found: {$inputfile}");
}

// Generate AES-128 encryption key for HLS segments.
$keyfile = $hlsdir . '/enc.key';
$keydata = random_bytes(16);
file_put_contents($keyfile, $keydata);

// The key URL will go through our serve.php proxy.
// We use a placeholder that gets replaced at playlist serve time.
$keyinfofile = $hlsdir . '/enc.keyinfo';
$iv = bin2hex(random_bytes(16));
file_put_contents($keyinfofile, "enc.key\n{$keyfile}\n{$iv}");

$output = $hlsdir . '/stream.m3u8';
$segpattern = escapeshellarg($hlsdir . '/seg_%04d.ts');
$hlsflags = '-hls_time 6 -hls_list_size 0 -hls_segment_type mpegts '
    . '-hls_key_info_file ' . escapeshellarg($keyinfofile) . ' '
    . '-hls_segment_filename ' . $segpattern . ' '
    . '-f hls ' . escapeshellarg($output);

// Attempt 1: stream copy — remux without re-encoding (very fast, seconds not minutes).
// -bsf:v h264_mp4toannexb is required to convert H.264 from MP4 container format to
// the raw Annex-B format that MPEG-TS (HLS) segments need.
$cmd = sprintf(
    '%s -i %s -c:v copy -c:a copy -bsf:v h264_mp4toannexb %s 2>&1',
    escapeshellarg($ffmpeg),
    escapeshellarg($inputfile),
    $hlsflags
);

mtrace("Attempt 1 (stream copy): {$cmd}");
$result = null;
$cmdoutput = [];
exec($cmd, $cmdoutput, $result);

if ($result !== 0) {
    // Stream copy failed (uncommon codec or container). Clean up partial output.
    array_map('unlink', glob($hlsdir . '/seg_*.ts'));
    if (file_exists($output)) unlink($output);

    mtrace("Stream copy failed (exit {$result}), falling back to re-encode...");

    // Attempt 2: re-encode with ultrafast preset — slower but handles any input format.
    $cmd = sprintf(
        '%s -i %s -c:v libx264 -c:a aac -preset ultrafast -crf 23 %s 2>&1',
        escapeshellarg($ffmpeg),
        escapeshellarg($inputfile),
        $hlsflags
    );

    mtrace("Attempt 2 (re-encode): {$cmd}");
    exec($cmd, $cmdoutput, $result);

    if ($result !== 0) {
        $DB->set_field('local_securevideo_videos', 'status', 'error', ['id' => $videoid]);
        if (file_exists($keyinfofile)) unlink($keyinfofile);
        mtrace("FFmpeg re-encode also failed with exit code {$result}");
        mtrace(implode("\n", $cmdoutput));
        exit(1);
    }
}

// Write .htaccess to prevent direct web access to HLS directory.
$htaccess = $hlsdir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Deny from all\n");
}

// Get duration via ffprobe.
$ffprobe = dirname($ffmpeg) . '/ffprobe';
$durationcmd = sprintf(
    '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
    escapeshellarg($ffprobe),
    escapeshellarg($inputfile)
);
$duration = trim(shell_exec($durationcmd));
$durationval = is_numeric($duration) ? (float)$duration : 0;
if ($durationval > 0) {
    $DB->set_field('local_securevideo_videos', 'duration', (int)$durationval, ['id' => $videoid]);
}

// Generate thumbnail at ~15% into the video (avoids black intro frames).
$thumbdir = local_securevideo_get_storage_path() . '/thumbs';
if (!is_dir($thumbdir)) {
    mkdir($thumbdir, 0770, true);
}
$thumbfile = $thumbdir . '/' . $videoid . '.jpg';
$thumbtime = $durationval > 0 ? max(2, (int)($durationval * 0.15)) : 2;
$thumbcmd  = sprintf(
    '%s -y -ss %s -i %s -frames:v 1 '
    . '-vf "scale=480:270:force_original_aspect_ratio=decrease,pad=480:270:(ow-iw)/2:(oh-ih)/2:black" '
    . '-q:v 4 %s 2>&1',
    escapeshellarg($ffmpeg),
    escapeshellarg((string)$thumbtime),
    escapeshellarg($inputfile),
    escapeshellarg($thumbfile)
);
exec($thumbcmd);
if (file_exists($thumbfile)) {
    mtrace("Thumbnail generated: {$thumbfile}");
} else {
    mtrace("Thumbnail generation failed (non-fatal).");
}

// Rewrite the m3u8 to use serve.php URLs for the key.
$m3u8 = file_get_contents($output);
$m3u8 = preg_replace(
    '#URI="[^"]+"#',
    'URI="SERVE_KEY_URL"',
    $m3u8
);
file_put_contents($output, $m3u8);

// Clean up keyinfo file (no longer needed).
unlink($keyinfofile);

// Mark as ready.
$DB->set_field('local_securevideo_videos', 'status', 'ready', ['id' => $videoid]);
$DB->set_field('local_securevideo_videos', 'timemodified', time(), ['id' => $videoid]);
mtrace("Video {$videoid} conversion complete.");
