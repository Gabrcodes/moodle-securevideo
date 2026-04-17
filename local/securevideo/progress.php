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
 * Progress tracking endpoint. Receives watch-time data from the player.
 * Uses real unique-seconds-watched to prevent seek-cheating.
 */
require_once(__DIR__ . '/../../config.php');

require_login(null, false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die();
}

$input = file_get_contents('php://input');
if (strlen($input) > 4096) {
    http_response_code(413);
    die();
}

$data = json_decode($input, true);
if (!$data || empty($data['videoId']) || empty($data['playlistId'])) {
    http_response_code(400);
    die();
}

$videoid = (int)$data['videoId'];
$playlistid = (int)$data['playlistId'];
$watchedseconds = max(0, min(86400, (int)($data['watchedSeconds'] ?? 0)));
$totalseconds = max(1, min(86400, (int)($data['totalSeconds'] ?? 1)));
$currenttime = max(0, min(86400, (int)($data['currentTime'] ?? 0)));
$percent = min(100, round(($watchedseconds / $totalseconds) * 100));

// Verify video and playlist exist.
$video = $DB->get_record('local_securevideo_videos', ['id' => $videoid]);
$playlist = $DB->get_record('local_securevideo_plists', ['id' => $playlistid]);
if (!$video || !$playlist) {
    http_response_code(404);
    die();
}

// Check capability.
$context = context_system::instance();
if (isguestuser()) {
    http_response_code(403);
    die();
}

$now = time();
$existing = $DB->get_record('local_securevideo_progress', [
    'userid' => $USER->id,
    'playlistid' => $playlistid,
    'videoid' => $videoid,
]);

if ($existing) {
    // Only update if progress increased.
    $updated = false;
    if ($watchedseconds > $existing->watchedseconds) {
        $existing->watchedseconds = $watchedseconds;
        $updated = true;
    }
    if ($percent > $existing->percent) {
        $existing->percent = $percent;
        $updated = true;
    }
    if ($existing->percent >= 90 && !$existing->completed) {
        $existing->completed = 1;
        $updated = true;
    }
    if ($updated) {
        $existing->totalseconds = $totalseconds;
        $existing->lastposition = $currenttime;
        $existing->timemodified = $now;
        $DB->update_record('local_securevideo_progress', $existing);
    }
} else {
    $record = new stdClass();
    $record->userid = $USER->id;
    $record->playlistid = $playlistid;
    $record->videoid = $videoid;
    $record->watchedseconds = $watchedseconds;
    $record->totalseconds = $totalseconds;
    $record->percent = $percent;
    $record->completed = ($percent >= 90) ? 1 : 0;
    $record->lastposition = $currenttime;
    $record->timecreated = $now;
    $record->timemodified = $now;
    $DB->insert_record('local_securevideo_progress', $record);
}

http_response_code(204);
