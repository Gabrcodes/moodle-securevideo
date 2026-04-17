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

/**
 * Adds a link to the admin menu under "Local plugins".
 */
function local_securevideo_extend_settings_navigation($settingsnav, $context) {
    global $CFG;
    if (has_capability('local/securevideo:manage', context_system::instance())) {
        $node = $settingsnav->find('localplugins', navigation_node::TYPE_CATEGORY);
        if ($node) {
            $node->add(
                get_string('manage', 'local_securevideo'),
                new moodle_url('/local/securevideo/manage.php'),
                navigation_node::TYPE_SETTING,
                null,
                'local_securevideo_manage',
                new pix_icon('i/settings', '')
            );
        }
    }
}

function local_securevideo_get_storage_path() {
    global $CFG;
    $path = $CFG->dataroot . '/securevideo';
    if (!is_dir($path)) {
        mkdir($path, 0770, true);
    }
    return $path;
}

function local_securevideo_get_original_path() {
    $path = local_securevideo_get_storage_path() . '/original';
    if (!is_dir($path)) {
        mkdir($path, 0770, true);
    }
    return $path;
}

function local_securevideo_get_hls_path($videoid = null) {
    $path = local_securevideo_get_storage_path() . '/hls';
    if (!is_dir($path)) {
        mkdir($path, 0770, true);
    }
    if ($videoid !== null) {
        $path .= '/' . (int)$videoid;
        if (!is_dir($path)) {
            mkdir($path, 0770, true);
        }
    }
    return $path;
}

function local_securevideo_get_secret() {
    $secret = get_config('local_securevideo', 'tokensecret');
    if (empty($secret)) {
        // Auto-generate and persist on first use.
        $secret = bin2hex(random_bytes(32));
        set_config('tokensecret', $secret, 'local_securevideo');
    }
    return $secret;
}

function local_securevideo_generate_token($videoid, $userid) {
    $secret = local_securevideo_get_secret();
    $expiry = (int)get_config('local_securevideo', 'tokenexpiry') ?: 14400;
    $expires = time() + $expiry;
    $payload = "{$videoid}:{$userid}:{$expires}";
    $sig = hash_hmac('sha256', $payload, $secret);
    return base64_encode("{$expires}:{$sig}");
}

function local_securevideo_verify_token($videoid, $userid, $token) {
    $secret = local_securevideo_get_secret();
    $decoded = base64_decode($token, true);
    if ($decoded === false) {
        return false;
    }
    $parts = explode(':', $decoded, 2);
    if (count($parts) !== 2) {
        return false;
    }
    [$expires, $sig] = $parts;
    if ((int)$expires < time()) {
        return false;
    }
    $payload = "{$videoid}:{$userid}:{$expires}";
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $sig);
}

function local_securevideo_generate_access_code($videoid, $userid) {
    global $DB;

    // Reuse existing code for same user+video within last 24h.
    $rows = $DB->get_records_sql(
        "SELECT accesscode FROM {local_securevideo_access}
         WHERE videoid = ? AND userid = ? AND timecreated > ?
         ORDER BY timecreated DESC",
        [$videoid, $userid, time() - 86400],
        0, 1
    );
    $recent = $rows ? reset($rows) : null;
    if ($recent) {
        return $recent->accesscode;
    }

    // Generate a short unique code.
    $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

    $record = new stdClass();
    $record->videoid = $videoid;
    $record->userid = $userid;
    $record->accesscode = $code;
    $record->ip = getremoteaddr();
    $record->timecreated = time();
    $DB->insert_record('local_securevideo_access', $record);

    return $code;
}
