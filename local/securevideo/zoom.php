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
 * Zoom Cloud Recordings Import
 * Lists recordings from your Zoom account and lets you import them directly.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/securevideo:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/securevideo/zoom.php'));
$PAGE->set_title('Import from Zoom');
$PAGE->set_heading('Import from Zoom');
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);

// =========================================================
// ZOOM API HELPERS
// =========================================================
function sv_zoom_get_token() {
    $accountid    = get_config('local_securevideo', 'zoom_account_id');
    $clientid     = get_config('local_securevideo', 'zoom_client_id');
    $clientsecret = get_config('local_securevideo', 'zoom_client_secret');

    if (empty($accountid) || empty($clientid) || empty($clientsecret)) {
        return null;
    }

    $ch = curl_init('https://zoom.us/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'grant_type=account_credentials&account_id=' . urlencode($accountid),
        CURLOPT_USERPWD        => $clientid . ':' . $clientsecret,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function sv_zoom_api_get($endpoint, $token, $params = []) {
    $url = 'https://api.zoom.us/v2/' . ltrim($endpoint, '/');
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode !== 200) {
        return null;
    }

    return json_decode($response, true);
}

// =========================================================
// HANDLE IMPORT ACTION
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey() && $action === 'import') {
    $downloadurl = required_param('downloadurl', PARAM_URL);
    $vidname     = required_param('vidname', PARAM_TEXT);
    $token       = sv_zoom_get_token();

    $isajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
           && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (empty($token)) {
        if ($isajax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'msg' => 'Could not authenticate with Zoom API.']);
            exit;
        }
        redirect($PAGE->url, 'Zoom API auth failed.', null, \core\output\notification::NOTIFY_ERROR);
    }

    // Check for duplicates.
    if ($DB->record_exists('local_securevideo_videos', ['name' => trim($vidname)])) {
        if ($isajax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'msg' => 'A video named "' . trim($vidname) . '" already exists.']);
            exit;
        }
        redirect($PAGE->url, 'Duplicate name.', null, \core\output\notification::NOTIFY_ERROR);
    }

    // Append access token to download URL.
    $sep = (strpos($downloadurl, '?') !== false) ? '&' : '?';
    $fullurl = $downloadurl . $sep . 'access_token=' . urlencode($token);

    $safename = time() . '_' . bin2hex(random_bytes(8)) . '.mp4';

    $record = new stdClass();
    $record->name        = trim($vidname);
    $record->filename    = $safename;
    $record->status      = 'pending';
    $record->filesize    = 0;
    $record->createdby   = $USER->id;
    $record->timecreated = time();
    $record->timemodified = time();
    $videoid = $DB->insert_record('local_securevideo_videos', $record);

    // Save URL to temp file and spawn background download.
    $urlfile = sys_get_temp_dir() . '/sv_url_' . $videoid . '.txt';
    file_put_contents($urlfile, $fullurl);

    $phpbin     = PHP_BINARY ?: 'php';
    $scriptpath = __DIR__ . '/url_download.php';
    $cmd = escapeshellarg($phpbin) . ' ' . escapeshellarg($scriptpath)
         . ' ' . (int)$videoid . ' ' . escapeshellarg($urlfile);
    exec($cmd . ' > /dev/null 2>&1 &');

    if ($isajax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'msg' => 'Download started for "' . trim($vidname) . '".', 'videoid' => $videoid]);
        exit;
    }
    redirect($PAGE->url, 'Download started.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// =========================================================
// RENDER PAGE
// =========================================================
echo $OUTPUT->header();

$manageurl = (new moodle_url('/local/securevideo/manage.php'))->out(false);
echo '<p><a href="' . $manageurl . '" class="btn btn-sm btn-outline-secondary">&larr; Back to Video Manager</a></p>';

// Get Zoom token.
$token = sv_zoom_get_token();

if (empty($token)) {
    echo $OUTPUT->notification('Could not connect to Zoom API. Check the credentials in plugin settings.', 'error');
    echo $OUTPUT->footer();
    exit;
}

echo '<div class="alert alert-success">Connected to Zoom API.</div>';

// Fetch recordings — last 30 days.
$from = date('Y-m-d', strtotime('-30 days'));
$to   = date('Y-m-d');
$page = optional_param('zpage', '', PARAM_TEXT);

$params = ['from' => $from, 'to' => $to, 'page_size' => 50];
if (!empty($page)) {
    $params['next_page_token'] = $page;
}

$recordings = sv_zoom_api_get('users/me/recordings', $token, $params);

if (empty($recordings) || empty($recordings['meetings'])) {
    echo '<p class="text-muted">No recordings found in the last 30 days.</p>';
    echo $OUTPUT->footer();
    exit;
}

echo '<h4>Zoom Cloud Recordings</h4>';
echo '<p class="text-muted small mb-3">Recordings from the last 30 days. Click Import to download directly to the server.</p>';

$selfurl = $PAGE->url->out(false);

foreach ($recordings['meetings'] as $meeting) {
    $topic    = $meeting['topic'] ?? 'Untitled';
    $start    = $meeting['start_time'] ?? '';
    $startfmt = $start ? userdate(strtotime($start), '%b %d, %Y %I:%M %p') : '-';
    $duration = (int)($meeting['duration'] ?? 0);
    $durtext  = $duration > 0 ? ($duration >= 60 ? floor($duration / 60) . 'h ' . ($duration % 60) . 'min' : $duration . ' min') : '';

    $files = $meeting['recording_files'] ?? [];
    $videofiles = array_filter($files, function($f) {
        return ($f['file_type'] ?? '') === 'MP4' && ($f['status'] ?? '') === 'completed';
    });

    if (empty($videofiles)) continue;

    echo '<div class="card mb-3"><div class="card-body">';
    echo '<div class="d-flex justify-content-between align-items-center mb-2">';
    echo '<div>';
    echo '<h5 class="mb-0">' . s($topic) . '</h5>';
    echo '<small class="text-muted">' . $startfmt . ($durtext ? ' &bull; ' . $durtext : '') . '</small>';
    echo '</div></div>';

    foreach ($videofiles as $vf) {
        $size    = $vf['file_size'] ?? 0;
        $sizefmt = $size >= 1048576 ? round($size / 1048576, 1) . ' MB' : round($size / 1024) . ' KB';
        $dlurl   = $vf['download_url'] ?? '';
        $rectype = $vf['recording_type'] ?? 'unknown';
        $defaultname = s($topic) . ' - ' . $startfmt;

        if (empty($dlurl)) continue;

        // Calculate individual file duration from start/end timestamps.
        $fileDur = '';
        $recStart = $vf['recording_start'] ?? '';
        $recEnd   = $vf['recording_end'] ?? '';
        if ($recStart && $recEnd) {
            $secs = strtotime($recEnd) - strtotime($recStart);
            if ($secs > 0) {
                $fileDur = $secs >= 3600
                    ? floor($secs / 3600) . 'h ' . floor(($secs % 3600) / 60) . 'min'
                    : floor($secs / 60) . 'min';
            }
        }

        echo '<form class="sv-zoom-import d-flex align-items-center mb-2 flex-wrap" style="gap:8px">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="action" value="import">';
        echo '<input type="hidden" name="downloadurl" value="' . s($dlurl) . '">';
        echo '<input type="text" name="vidname" class="form-control form-control-sm" '
            . 'value="' . $defaultname . '" style="max-width:320px" required>';
        echo '<span class="text-muted small" style="white-space:nowrap">' . $sizefmt . ($fileDur ? ' &bull; ' . $fileDur : '') . ' &bull; ' . s($rectype) . '</span>';
        echo '<button type="submit" class="btn btn-sm btn-success">Import</button>';
        echo '</form>';
    }

    echo '</div></div>';
}

// Pagination.
if (!empty($recordings['next_page_token'])) {
    $nexturl = new moodle_url('/local/securevideo/zoom.php', ['zpage' => $recordings['next_page_token']]);
    echo '<a href="' . $nexturl->out(false) . '" class="btn btn-outline-primary">Load More Recordings</a>';
}

// AJAX handler for import forms.
echo '<script>
document.addEventListener("submit", function(e) {
    var form = e.target;
    if (!form.classList.contains("sv-zoom-import")) return;
    e.preventDefault();

    var btn = form.querySelector("button[type=submit]");
    if (btn.disabled) return;
    btn.disabled = true;
    btn.textContent = "Downloading...";

    var fd = new FormData(form);
    var url = form.getAttribute("action") || window.location.href;
    fetch(url, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            btn.textContent = "Downloading...";
            btn.className = "btn btn-sm btn-warning";
            var inp = form.querySelector("input[name=vidname]");
            if (inp) inp.disabled = true;
            // Poll status until ready or error.
            if (res.videoid) pollVideoStatus(res.videoid, btn);
        } else {
            alert(res.msg || "Import failed.");
            btn.disabled = false;
            btn.textContent = "Import";
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = "Import";
        alert("Request failed. Try again.");
    });
});

var STATUS_URL = ' . json_encode((new moodle_url('/local/securevideo/status.php'))->out(false)) . ';

function pollVideoStatus(videoId, btn) {
    var poll = setInterval(function() {
        fetch(STATUS_URL)
            .then(function(r) { return r.json(); })
            .then(function(videos) {
                var v = videos.find(function(x) { return x.id === videoId; });
                if (!v) return;

                if (v.status === "converting") {
                    btn.textContent = "Converting...";
                    btn.className = "btn btn-sm btn-info";
                } else if (v.status === "ready") {
                    btn.textContent = "Ready ✓";
                    btn.className = "btn btn-sm btn-success";
                    clearInterval(poll);
                } else if (v.status === "error") {
                    btn.textContent = "Failed ✗";
                    btn.className = "btn btn-sm btn-danger";
                    clearInterval(poll);
                } else {
                    btn.textContent = "Downloading...";
                    btn.className = "btn btn-sm btn-warning";
                }
            })
            .catch(function() {});
    }, 3000);
}
</script>';

echo $OUTPUT->footer();
