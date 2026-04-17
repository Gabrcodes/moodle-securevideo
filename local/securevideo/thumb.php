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
 * Serves video thumbnail images.
 * Requires login — thumbnails are not public.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login(null, false);
$context = context_system::instance();
if (isguestuser()) {
    http_response_code(403);
    exit;
}

$videoid = required_param('id', PARAM_INT);
$thumbfile = local_securevideo_get_storage_path() . '/thumbs/' . (int)$videoid . '.jpg';

if (!file_exists($thumbfile)) {
    // Return a simple dark placeholder as a 1x1 JPEG.
    http_response_code(404);
    exit;
}

// Serve with short cache — thumbnails rarely change.
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($thumbfile));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($thumbfile);
