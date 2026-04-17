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

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_securevideo_mod_form extends moodleform_mod {

    public function definition(): void {
        global $DB;
        $mform = $this->_form;

        // ── Standard header ──────────────────────────────────────────────
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // ── Playlist selector ────────────────────────────────────────────
        $mform->addElement('header', 'videosettings', get_string('pluginname', 'mod_securevideo'));

        $playlists = $DB->get_records('local_securevideo_plists', null, 'name ASC', 'id, name, sequential');
        if (empty($playlists)) {
            $mform->addElement('static', 'noplaylist', get_string('selectplaylist', 'mod_securevideo'),
                '<span class="text-danger">' . get_string('noplaylistsfound', 'mod_securevideo') . '</span>');
        } else {
            $options = [];
            foreach ($playlists as $pl) {
                $label = $pl->name;
                if ($pl->sequential) {
                    $label .= ' [Sequential]';
                }
                $count  = $DB->count_records('local_securevideo_plvids', ['playlistid' => $pl->id]);
                $label .= " ({$count} video" . ($count !== 1 ? 's' : '') . ')';
                $options[$pl->id] = $label;
            }
            $mform->addElement('select', 'playlistid',
                get_string('selectplaylist', 'mod_securevideo'), $options);
            $mform->addHelpButton('playlistid', 'selectplaylist', 'mod_securevideo');
            $mform->addRule('playlistid', null, 'required', null, 'server');
        }

        // ── Standard course module elements (includes completion section) ─
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    // ── Custom completion rules ──────────────────────────────────────────
    // These appear inside Moodle's own "Completion tracking" section
    // only when the teacher sets completion to "Show activity as complete
    // when conditions are met".

    public function add_completion_rules(): array {
        $mform = $this->_form;

        $group = [];
        $group[] = $mform->createElement('checkbox', 'completionpercenton', '',
            get_string('completionpercent', 'mod_securevideo'));
        $group[] = $mform->createElement('text', 'completionpercent', '', ['size' => '4']);
        $mform->setType('completionpercent', PARAM_INT);
        $mform->addGroup($group, 'completionpercentgroup',
            get_string('completionpercentgroup', 'mod_securevideo'), ['&nbsp;'], false);
        $mform->addHelpButton('completionpercentgroup', 'completionpercent', 'mod_securevideo');
        $mform->setDefault('completionpercent', 90);
        $mform->disabledIf('completionpercent', 'completionpercenton', 'notchecked');

        return ['completionpercentgroup'];
    }

    public function completion_rule_enabled($data) {
        return !empty($data['completionpercenton']) && (int)($data['completionpercent'] ?? 0) > 0;
    }

    public function data_preprocessing(&$defaults): void {
        parent::data_preprocessing($defaults);
        // Restore the checkbox state when editing an existing instance.
        if (!empty($defaults['completionpercent'])) {
            $defaults['completionpercenton'] = 1;
        }
    }
}
