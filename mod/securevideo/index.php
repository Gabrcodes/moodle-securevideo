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
 * Lists all Secure Video activities in a course.
 * Shown when clicking the module name in the course outline.
 */
require_once('../../config.php');

$id = required_param('id', PARAM_INT); // course ID
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);

$PAGE->set_url('/mod/securevideo/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_securevideo'));

$instances = get_all_instances_in_course('securevideo', $course);

if (empty($instances)) {
    echo $OUTPUT->notification(get_string('thereareno', 'moodle', get_string('modulenameplural', 'mod_securevideo')));
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head  = ['#', get_string('name'), get_string('selectplaylist', 'mod_securevideo')];
$table->align = ['center', 'left', 'left'];

foreach ($instances as $inst) {
    $playlist = $DB->get_record('local_securevideo_plists', ['id' => $inst->playlistid]);
    $plname   = $playlist ? format_string($playlist->name) : '-';
    $link     = html_writer::link(
        new moodle_url('/mod/securevideo/view.php', ['id' => $inst->coursemodule]),
        format_string($inst->name)
    );
    $table->data[] = [$inst->section, $link, $plname];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
