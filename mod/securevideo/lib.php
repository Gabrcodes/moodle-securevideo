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

// =========================================================
// REQUIRED MOODLE ACTIVITY MODULE CALLBACKS
// =========================================================

function securevideo_add_instance(stdClass $data, $mform = null): int {
    global $DB;
    // If the completion percent checkbox is unchecked, store 0.
    if (empty($data->completionpercenton)) {
        $data->completionpercent = 0;
    }
    $data->timecreated  = time();
    $data->timemodified = time();
    return $DB->insert_record('securevideo', $data);
}

function securevideo_update_instance(stdClass $data, $mform = null): bool {
    global $DB;
    if (empty($data->completionpercenton)) {
        $data->completionpercent = 0;
    }
    $data->id           = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('securevideo', $data);
}

function securevideo_delete_instance(int $id): bool {
    global $DB;
    if (!$DB->record_exists('securevideo', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('securevideo', ['id' => $id]);
    return true;
}

// =========================================================
// COMPLETION SUPPORT
// =========================================================

/**
 * Called by Moodle's completion system to check if a user has completed
 * this activity. Returns COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
 */
function securevideo_get_completion_state($course, $cm, $userid, $type): bool {
    global $DB;

    $instance = $DB->get_record('securevideo', ['id' => $cm->instance], '*', MUST_EXIST);

    // If no completion percent configured, fall back to view-based completion.
    if (empty($instance->completionpercent) || $instance->completionpercent <= 0) {
        return $type == COMPLETION_AND ? true : false;
    }

    // Get all videos in the playlist.
    $plvideos = $DB->get_records(
        'local_securevideo_plvids',
        ['playlistid' => $instance->playlistid],
        'sortorder ASC'
    );

    if (empty($plvideos)) {
        return true; // No videos = trivially complete.
    }

    $threshold = (int)$instance->completionpercent;

    // Check every video in the playlist meets the threshold.
    foreach ($plvideos as $plv) {
        $progress = $DB->get_record('local_securevideo_progress', [
            'userid'     => $userid,
            'playlistid' => $instance->playlistid,
            'videoid'    => $plv->videoid,
        ]);
        if (!$progress || (int)$progress->percent < $threshold) {
            return false; // At least one video not watched enough.
        }
    }

    return true; // All videos meet threshold.
}

/**
 * Returns whether this module supports a given feature.
 */
function securevideo_supports(string $feature): ?bool {
    switch ($feature) {
        case FEATURE_MOD_INTRO:              return true;
        case FEATURE_SHOW_DESCRIPTION:       return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:   return true;
        case FEATURE_GRADE_HAS_GRADE:        return false;
        case FEATURE_BACKUP_MOODLE2:         return false;
        case FEATURE_MOD_PURPOSE:            return MOD_PURPOSE_CONTENT;
        default:                             return null;
    }
}

/**
 * Returns the list of custom completion rules for this module.
 * Used by Moodle's completion UI.
 */
function securevideo_get_completion_rules(): array {
    return ['completionpercent'];
}

/**
 * Returns the description of a custom completion rule's current setting.
 */
function securevideo_get_completion_rules_description(cm_info $cm): array {
    global $DB;
    $instance = $DB->get_record('securevideo', ['id' => $cm->instance]);
    $pct = $instance ? (int)$instance->completionpercent : 90;
    return ['completionpercent' => "Watch at least {$pct}% of each video"];
}
