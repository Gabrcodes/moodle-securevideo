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

$string['modulename']          = 'Secure Video';
$string['modulenameplural']    = 'Secure Videos';
$string['modulename_help']     = 'Add a secure video playlist to your course. Students watch encrypted videos with forensic watermarking. Progress is tracked and completion is automatic.';
$string['pluginname']          = 'Secure Video';
$string['pluginadministration'] = 'Secure Video administration';

$string['securevideo:view']        = 'View secure videos';
$string['securevideo:addinstance'] = 'Add a secure video activity';

$string['selectplaylist']      = 'Playlist';
$string['selectplaylist_help'] = 'Choose which video playlist students will see in this activity.';
$string['noplaylistsfound']    = 'No playlists found. Create one in the Secure Video Manager first.';
$string['completionpercent']   = 'Completion: require watching (%)';
$string['completionpercent_help'] = 'The percentage of each video a student must watch for this activity to be marked complete. Set to 0 to disable automatic completion.';
$string['completionpercentgroup'] = 'Require watching';
$string['novideosinplaylist']  = 'This playlist has no videos yet.';
$string['activitylocked']      = 'Complete the previous video to unlock this one.';
