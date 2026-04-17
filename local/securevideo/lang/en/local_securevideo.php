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

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Secure Video Player';
$string['manage'] = 'Manage Videos';
$string['uploadvideo'] = 'Upload Video';
$string['videoname'] = 'Video Name';
$string['selectfile'] = 'Select Video File';
$string['upload'] = 'Upload';
$string['status_pending'] = 'Pending conversion';
$string['status_converting'] = 'Converting...';
$string['status_ready'] = 'Ready';
$string['status_error'] = 'Error';
$string['embedcode'] = 'Embed Code';
$string['deletevideo'] = 'Delete Video';
$string['confirmdelete'] = 'Are you sure you want to delete this video?';
$string['novideofound'] = 'Video not found.';
$string['nopermission'] = 'You do not have permission to view this video.';
$string['securevideo:manage'] = 'Manage secure videos';
$string['securevideo:view'] = 'View secure videos';
$string['ffmpegpath'] = 'FFmpeg path';
$string['ffmpegpath_desc'] = 'Full path to the ffmpeg binary (e.g. /usr/bin/ffmpeg)';
$string['tokensecret'] = 'Token secret';
$string['tokensecret_desc'] = 'Secret key used for signing video access tokens. Change this to invalidate all existing tokens.';
$string['tokenexpiry'] = 'Token expiry (seconds)';
$string['tokenexpiry_desc'] = 'How long a video access token remains valid.';
$string['watermarkopacity'] = 'Watermark opacity (%)';
$string['watermarkopacity_desc'] = 'Opacity of the anti-piracy watermark (5-50).';
