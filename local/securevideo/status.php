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
 * Returns JSON array of all video IDs and their current conversion status.
 * Used by manage.php to poll statuses without a full page reload.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/securevideo:manage', $context);

$videos = $DB->get_records('local_securevideo_videos', null, 'timecreated DESC', 'id, name, status');
$result = [];
foreach ($videos as $v) {
    $result[] = ['id' => (int)$v->id, 'name' => $v->name, 'status' => $v->status];
}

header('Content-Type: application/json');
echo json_encode($result);
