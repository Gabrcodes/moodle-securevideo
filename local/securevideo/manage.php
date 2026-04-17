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

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/securevideo:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/securevideo/manage.php'));
$PAGE->set_title(get_string('manage', 'local_securevideo'));
$PAGE->set_heading(get_string('manage', 'local_securevideo'));
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);

// Helper: respond as JSON for AJAX calls, or redirect for normal forms.
function sv_respond(bool $isajax, moodle_url $url, string $msg, array $data = []): void {
    if ($isajax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok' => true, 'msg' => $msg], $data));
        exit;
    }
    redirect($url, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

// =========================================================
// HANDLE POST ACTIONS
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $isajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
           && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    // Delete video.
    if ($action === 'deletevideo') {
        $vid = required_param('vid', PARAM_INT);
        $video = $DB->get_record('local_securevideo_videos', ['id' => $vid]);
        if ($video) {
            $hlspath = local_securevideo_get_hls_path($vid);
            if (is_dir($hlspath)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($hlspath, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $f) {
                    $f->isDir() ? rmdir($f->getRealPath()) : unlink($f->getRealPath());
                }
                rmdir($hlspath);
            }
            $origpath = local_securevideo_get_original_path() . '/' . $video->filename;
            if (file_exists($origpath)) {
                unlink($origpath);
            }
            $DB->delete_records('local_securevideo_plvids', ['videoid' => $vid]);
            $DB->delete_records('local_securevideo_progress', ['videoid' => $vid]);
            $DB->delete_records('local_securevideo_access', ['videoid' => $vid]);
            $DB->delete_records('local_securevideo_videos', ['id' => $vid]);
        }
        sv_respond($isajax, $PAGE->url, 'Video deleted.', ['vid' => $vid]);
    }

    // Create playlist.
    if ($action === 'createplaylist') {
        $name = required_param('name', PARAM_TEXT);
        $sequential = optional_param('sequential', 0, PARAM_INT);
        $record = new stdClass();
        $record->name = $name;
        $record->sequential = $sequential ? 1 : 0;
        $record->createdby = $USER->id;
        $record->timecreated = time();
        $record->timemodified = time();
        $DB->insert_record('local_securevideo_plists', $record);
        sv_respond($isajax, $PAGE->url, 'Playlist created.');
    }

    // Delete playlist.
    if ($action === 'deleteplaylist') {
        $plid = required_param('plid', PARAM_INT);
        $DB->delete_records('local_securevideo_plvids', ['playlistid' => $plid]);
        $DB->delete_records('local_securevideo_progress', ['playlistid' => $plid]);
        $DB->delete_records('local_securevideo_plists', ['id' => $plid]);
        sv_respond($isajax, $PAGE->url, 'Playlist deleted.', ['plid' => $plid]);
    }

    // Add video to playlist.
    if ($action === 'addvideo') {
        $plid = required_param('plid', PARAM_INT);
        $vid = required_param('vid', PARAM_INT);
        $exists = $DB->record_exists('local_securevideo_plvids', ['playlistid' => $plid, 'videoid' => $vid]);
        if (!$exists) {
            $maxsort = (int)$DB->get_field_sql(
                "SELECT COALESCE(MAX(sortorder), 0) FROM {local_securevideo_plvids} WHERE playlistid = ?", [$plid]
            );
            $record = new stdClass();
            $record->playlistid = $plid;
            $record->videoid = $vid;
            $record->sortorder = $maxsort + 1;
            $DB->insert_record('local_securevideo_plvids', $record);
        }
        $vname = isset($allvideos[$vid]) ? $allvideos[$vid]->name : '';
        sv_respond($isajax, $PAGE->url, 'Video added.', ['plid' => $plid, 'vid' => $vid, 'vname' => $vname]);
    }

    // Remove video from playlist.
    if ($action === 'removevideo') {
        $plid = required_param('plid', PARAM_INT);
        $vid = required_param('vid', PARAM_INT);
        $DB->delete_records('local_securevideo_plvids', ['playlistid' => $plid, 'videoid' => $vid]);
        sv_respond($isajax, $PAGE->url, 'Video removed.', ['plid' => $plid, 'vid' => $vid]);
    }

    // Move video up/down.
    if ($action === 'moveup' || $action === 'movedown') {
        $plid = required_param('plid', PARAM_INT);
        $vid = required_param('vid', PARAM_INT);
        $items = $DB->get_records('local_securevideo_plvids', ['playlistid' => $plid], 'sortorder ASC');
        $items = array_values($items);
        $idx = -1;
        foreach ($items as $i => $item) {
            if ((int)$item->videoid === $vid) { $idx = $i; break; }
        }
        if ($idx >= 0) {
            $swap = ($action === 'moveup') ? $idx - 1 : $idx + 1;
            if ($swap >= 0 && $swap < count($items)) {
                $tmpSort = $items[$idx]->sortorder;
                $items[$idx]->sortorder = $items[$swap]->sortorder;
                $items[$swap]->sortorder = $tmpSort;
                $DB->update_record('local_securevideo_plvids', $items[$idx]);
                $DB->update_record('local_securevideo_plvids', $items[$swap]);
            }
        }
        sv_respond($isajax, $PAGE->url, 'Order updated.', ['plid' => $plid, 'action' => $action, 'vid' => $vid]);
    }

    // Rename video.
    if ($action === 'renamevideo') {
        $vid     = required_param('vid', PARAM_INT);
        $newname = required_param('newname', PARAM_TEXT);
        if (trim($newname) !== '') {
            $DB->set_field('local_securevideo_videos', 'name', trim($newname), ['id' => $vid]);
            $DB->set_field('local_securevideo_videos', 'timemodified', time(), ['id' => $vid]);
        }
        sv_respond($isajax, $PAGE->url, 'Video renamed.', ['vid' => $vid, 'newname' => trim($newname)]);
    }

    // Import a video from Moodle file store.
    if ($action === 'importvideo') {
        $fileid   = required_param('fileid', PARAM_INT);
        $vidname  = required_param('vidname', PARAM_TEXT);
        $fs = get_file_storage();
        $file = $fs->get_file_by_id($fileid);
        if ($file && $file->get_filesize() > 0) {
            $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            $safename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destpath = local_securevideo_get_original_path() . '/' . $safename;
            $file->copy_content_to($destpath);

            $record = new stdClass();
            $record->name        = trim($vidname) ?: $file->get_filename();
            $record->filename    = $safename;
            $record->status      = 'pending';
            $record->filesize    = $file->get_filesize();
            $record->createdby   = $USER->id;
            $record->timecreated = time();
            $record->timemodified = time();
            $videoid = $DB->insert_record('local_securevideo_videos', $record);

            $phpbin     = PHP_BINARY ?: 'php';
            $scriptpath = __DIR__ . '/convert.php';
            $cmd = escapeshellarg($phpbin) . ' ' . escapeshellarg($scriptpath) . ' ' . (int)$videoid;
            exec($cmd . ' > /dev/null 2>&1 &');
        }
        sv_respond($isajax, $PAGE->url, 'Import started.', ['fileid' => $fileid]);
    }

    // Import from URL (Zoom, direct download links, etc.).
    if ($action === 'importurl') {
        $vidname = required_param('vidname', PARAM_TEXT);
        $vidurl  = required_param('vidurl',  PARAM_URL);

        if (empty(trim($vidname)) || empty(trim($vidurl))) {
            sv_respond($isajax, $PAGE->url, 'Name and URL are required.', []);
        }

        // Prevent duplicates — check if a video with this exact name already exists.
        if ($DB->record_exists('local_securevideo_videos', ['name' => trim($vidname)])) {
            if ($isajax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'msg' => 'A video named "' . trim($vidname) . '" already exists. Use a different name or delete the existing one first.']);
                exit;
            }
            redirect($PAGE->url, 'A video with that name already exists.', null, \core\output\notification::NOTIFY_ERROR);
        }

        // Guess extension from URL or default to mp4.
        $urlpath = parse_url($vidurl, PHP_URL_PATH);
        $ext = strtolower(pathinfo($urlpath, PATHINFO_EXTENSION));
        $allowed = ['mp4', 'webm', 'mkv', 'mov', 'avi'];
        if (!in_array($ext, $allowed)) {
            $ext = 'mp4'; // Default to mp4 for Zoom and most services.
        }

        $safename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

        // Create DB record as pending.
        $record = new stdClass();
        $record->name        = trim($vidname);
        $record->filename    = $safename;
        $record->status      = 'pending';
        $record->filesize    = 0;
        $record->createdby   = $USER->id;
        $record->timecreated = time();
        $record->timemodified = time();
        $videoid = $DB->insert_record('local_securevideo_videos', $record);

        // Save URL to temp file (URLs like Zoom signed links are too long for shell args).
        $urlfile = sys_get_temp_dir() . '/sv_url_' . $videoid . '.txt';
        file_put_contents($urlfile, $vidurl);

        // Spawn background download + conversion.
        $phpbin     = PHP_BINARY ?: 'php';
        $scriptpath = __DIR__ . '/url_download.php';
        $cmd = escapeshellarg($phpbin) . ' ' . escapeshellarg($scriptpath)
             . ' ' . (int)$videoid . ' ' . escapeshellarg($urlfile);
        exec($cmd . ' > /dev/null 2>&1 &');

        sv_respond($isajax, $PAGE->url, 'Download started. The video will appear when ready.', ['videoid' => $videoid]);
    }

    // Reorder videos via drag-and-drop (receives full ordered array of video IDs).
    if ($action === 'reordervideos') {
        $plid   = required_param('plid', PARAM_INT);
        $rawids = required_param('order', PARAM_TEXT); // comma-separated video IDs
        $ids    = array_filter(array_map('intval', explode(',', $rawids)));
        foreach ($ids as $pos => $vid) {
            $DB->set_field('local_securevideo_plvids', 'sortorder', $pos,
                ['playlistid' => $plid, 'videoid' => $vid]);
        }
        sv_respond($isajax, $PAGE->url, 'Order saved.', ['plid' => $plid]);
    }

    // Toggle sequential.
    if ($action === 'toggleseq') {
        $plid = required_param('plid', PARAM_INT);
        $pl = $DB->get_record('local_securevideo_plists', ['id' => $plid], '*', MUST_EXIST);
        $pl->sequential = $pl->sequential ? 0 : 1;
        $pl->timemodified = time();
        $DB->update_record('local_securevideo_plists', $pl);
        sv_respond($isajax, $PAGE->url, 'Sequential mode ' . ($pl->sequential ? 'enabled' : 'disabled') . '.', ['plid' => $plid, 'sequential' => $pl->sequential]);
    }
}

// =========================================================
// RENDER PAGE
// =========================================================
echo $OUTPUT->header();
// SortableJS for drag-and-drop playlist reordering.
echo '<script src="https://unpkg.com/sortablejs@1.15.2/Sortable.min.js"></script>';
echo '<style>
.sv-plv-row { display:flex; align-items:center; gap:8px; padding:8px 10px;
    border:1px solid #dee2e6; margin-bottom:4px; background:#fff;
    border-radius:4px; cursor:grab; transition:box-shadow .15s; }
.sv-plv-row:active { cursor:grabbing; }
.sv-plv-row.sortable-ghost { opacity:.4; background:#fff3cd; }
.sv-plv-row.sortable-drag  { box-shadow:0 4px 12px rgba(0,0,0,.15); cursor:grabbing; }
.sv-drag-handle { color:#aaa; font-size:18px; cursor:grab; flex-shrink:0; user-select:none; }
.sv-drag-handle:hover { color:#555; }
.sv-plv-num  { background:#000; color:#FFED00; width:22px; height:22px; display:flex;
    align-items:center; justify-content:center; font-size:10px; font-weight:900;
    flex-shrink:0; border-radius:2px; }
.sv-plv-name { flex:1; font-size:13px; font-weight:600; }
.sv-order-saving { color:#888; font-size:11px; font-style:italic; margin-left:6px; }
</style>';

// upload_handler.php is the proven-working upload endpoint (no chunking needed).
$uploadurl = (new moodle_url('/local/securevideo/upload_handler.php'))->out(false);
$sesskey   = sesskey();

// No Dropzone CSS — we use Bootstrap classes exclusively to avoid conflicts.
echo '<script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>';

echo '
<div class="card mb-4"><div class="card-body">
<h4 class="card-title">Upload Videos</h4>
<p class="text-muted small mb-2">Drag and drop videos here, or click to browse. Up to 3 files upload in parallel.</p>

<style>
#svDzZone {
    border: 2px dashed #007bff;
    border-radius: 6px;
    background: #f8f9ff;
    padding: 30px 20px;
    text-align: center;
    cursor: pointer;
    transition: background .15s, border-color .15s;
}
#svDzZone:hover, #svDzZone.dz-drag-hover {
    background: #e8eeff;
    border-color: #0056b3;
}
#svDzZone .dz-message { pointer-events: none; }
#svFileList { margin-top: 12px; }
.sv-file-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    background: #fff;
    margin-bottom: 6px;
}
.sv-file-icon { font-size: 22px; flex-shrink: 0; }
.sv-file-body { flex: 1; min-width: 0; }
.sv-file-name-input {
    width: 100%;
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 3px 8px;
    font-size: 13px;
    font-weight: 600;
    background: #f8f9fa;
    margin-bottom: 3px;
}
.sv-file-name-input:focus { border-color: #007bff; outline: none; background: #fff; }
.sv-file-meta { font-size: 11px; color: #6c757d; }
.sv-progress-wrap {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 5px;
}
.sv-progress-bar {
    height: 100%;
    width: 0%;
    background: #007bff;
    border-radius: 3px;
    transition: width .15s;
}
.sv-file-status { flex-shrink: 0; font-size: 12px; font-weight: 600; width: 70px; text-align: right; }
.sv-file-remove {
    flex-shrink: 0;
    background: none;
    border: none;
    color: #dc3545;
    font-size: 18px;
    cursor: pointer;
    padding: 0 4px;
    line-height: 1;
}
.sv-file-remove:hover { color: #a71d2a; }
</style>

<div id="svDzZone">
  <div class="dz-message">
    <div style="font-size:36px;margin-bottom:6px">&#127916;</div>
    <strong>Drop video files here or click to browse</strong><br>
    <span class="text-muted small">MP4, WebM, MOV, MKV, AVI &mdash; up to 2 GB each</span>
  </div>
</div>

<div id="svFileList"></div>

<div id="svActions" style="display:none;margin-top:10px">
  <button class="btn btn-primary" id="svStartBtn">Upload All</button>
  <button class="btn btn-outline-secondary ml-2" id="svClearBtn">Clear All</button>
  <span class="text-muted ml-3 small" id="svInfo"></span>
</div>
</div></div>

<script>
Dropzone.autoDiscover = false;
(function(){
  var UPLOAD_URL = ' . json_encode($uploadurl) . ';
  var SESSKEY    = ' . json_encode($sesskey) . ';

  // Build a completely custom preview template — pure Bootstrap, zero Dropzone CSS.
  var tpl = [
    \'<div class="sv-file-row">\',
      \'<div class="sv-file-icon">&#127916;</div>\',
      \'<div class="sv-file-body">\',
        \'<input type="text" class="sv-file-name-input" placeholder="Video name">\',
        \'<div class="sv-file-meta">\',
          \'<span class="sv-fname" style="margin-right:6px"></span>\',
          \'<span class="sv-fsize"></span>\',
        \'</div>\',
        \'<div class="sv-progress-wrap"><div class="sv-progress-bar" data-dz-uploadprogress></div></div>\',
      \'</div>\',
      \'<div class="sv-file-status text-muted">Queued</div>\',
      \'<button type="button" class="sv-file-remove" data-dz-remove title="Remove">&times;</button>\',
    \'</div>\'
  ].join("");

  var dz = new Dropzone("#svDzZone", {
    url:                  UPLOAD_URL,
    paramName:            "videofile",
    parallelUploads:      3,
    autoProcessQueue:     false,
    acceptedFiles:        "video/*,.mp4,.webm,.mkv,.mov,.avi",
    maxFilesize:          2048,
    previewsContainer:    "#svFileList",
    previewTemplate:      tpl,
    createImageThumbnails: false,
    params: function(files, xhr) {
      var file = Array.isArray(files) ? files[0] : files;
      var inp  = file && file.previewElement
                   ? file.previewElement.querySelector(".sv-file-name-input") : null;
      return {
        sesskey:   SESSKEY,
        videoname: inp && inp.value.trim()
                     ? inp.value.trim()
                     : (file ? file.name.replace(/\.[^.]+$/, "") : "Video")
      };
    }
  });

  function fmtSize(b) {
    if (b >= 1073741824) return (b/1073741824).toFixed(1) + " GB";
    if (b >= 1048576)    return (b/1048576).toFixed(1) + " MB";
    if (b >= 1024)       return (b/1024).toFixed(0) + " KB";
    return b + " B";
  }

  dz.on("addedfile", function(file) {
    if (!file.previewElement) return;
    var el = file.previewElement;
    // Prefill name input with filename minus extension.
    var inp = el.querySelector(".sv-file-name-input");
    if (inp) {
      inp.value = file.name.replace(/\.[^.]+$/, "");
      inp.addEventListener("click", function(e){ e.stopPropagation(); });
    }
    var fn = el.querySelector(".sv-fname");
    if (fn) fn.textContent = file.name;
    var fs = el.querySelector(".sv-fsize");
    if (fs) fs.textContent = fmtSize(file.size);
    updateActions();
  });

  dz.on("uploadprogress", function(file, pct) {
    if (!file.previewElement) return;
    var bar = file.previewElement.querySelector(".sv-progress-bar");
    var st  = file.previewElement.querySelector(".sv-file-status");
    if (bar) bar.style.width = pct + "%";
    if (st)  st.textContent = Math.round(pct) + "%";
  });

  dz.on("sending", function(file) {
    if (!file.previewElement) return;
    var st = file.previewElement.querySelector(".sv-file-status");
    var bar = file.previewElement.querySelector(".sv-progress-bar");
    if (st) { st.textContent = "Uploading"; st.style.color = "#007bff"; }
    if (bar) bar.style.background = "#007bff";
  });

  dz.on("success", function(file) {
    if (!file.previewElement) return;
    var st = file.previewElement.querySelector(".sv-file-status");
    var bar = file.previewElement.querySelector(".sv-progress-bar");
    if (st)  { st.textContent = "Done ✓"; st.style.color = "#28a745"; }
    if (bar) { bar.style.width = "100%"; bar.style.background = "#28a745"; }
    // KEY FIX: with autoProcessQueue:false, Dropzone stops after the initial
    // parallelUploads slots finish. Calling processQueue() here restarts it
    // so files beyond the first batch actually upload.
    dz.processQueue();
  });

  dz.on("error", function(file, msg) {
    if (!file.previewElement) return;
    var st = file.previewElement.querySelector(".sv-file-status");
    var bar = file.previewElement.querySelector(".sv-progress-bar");
    if (st)  { st.textContent = "Failed ✗"; st.style.color = "#dc3545"; }
    if (bar) bar.style.background = "#dc3545";
    console.error("Upload error:", msg);
    // Continue queue even after an error.
    dz.processQueue();
  });

  dz.on("removedfile", function() { updateActions(); });

  dz.on("queuecomplete", function() {
    setTimeout(function(){ window.location.reload(); }, 1500);
  });

  document.getElementById("svStartBtn").addEventListener("click", function() {
    this.disabled = true;
    this.textContent = "Uploading...";
    dz.processQueue();
  });

  document.getElementById("svClearBtn").addEventListener("click", function() {
    dz.removeAllFiles(true);
    updateActions();
  });

  function updateActions() {
    var n = dz.files.filter(function(f){ return f.status !== "canceled"; }).length;
    document.getElementById("svActions").style.display = n > 0 ? "block" : "none";
    document.getElementById("svInfo").textContent = n + " file" + (n !== 1 ? "s" : "") + " in queue";
    var b = document.getElementById("svStartBtn");
    if (b && !dz.getUploadingFiles().length) { b.disabled = false; b.textContent = "Upload All"; }
  }
})();
</script>
';

// =========================================================
// PLAYLISTS
// =========================================================
// =========================================================
// IMPORT FROM URL (Zoom, Google Drive, direct links)
// =========================================================
echo '<div class="card mb-4"><div class="card-body">';
echo '<h4 class="card-title">Import from URL</h4>';
echo '<p class="text-muted small mb-2">Paste a Zoom recording download link or any direct video URL. The server downloads it directly — no need to download to your computer first.</p>';
echo '<form id="svUrlForm" class="form-row align-items-end">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="importurl">';
echo '<div class="form-group col-md-4 mb-2">';
echo '<label class="font-weight-bold small text-uppercase">Video Name</label>';
echo '<input type="text" name="vidname" class="form-control" placeholder="e.g. Lesson 5 - March 26" required>';
echo '</div>';
echo '<div class="form-group col-md-5 mb-2">';
echo '<label class="font-weight-bold small text-uppercase">Download URL</label>';
echo '<input type="url" name="vidurl" class="form-control" placeholder="https://zoom.us/rec/download/..." required>';
echo '</div>';
echo '<div class="form-group col-md-3 mb-2">';
echo '<button type="submit" class="btn btn-success btn-block" id="svUrlBtn">Download &amp; Import</button>';
echo '</div>';
echo '</form>';
echo '<div id="svUrlStatus" style="display:none;margin-top:8px"></div>';
echo '</div></div>';

$zoomurl = (new moodle_url('/local/securevideo/zoom.php'))->out(false);
echo '<div class="card mb-4 border-info"><div class="card-body d-flex justify-content-between align-items-center">';
echo '<div>';
echo '<h5 class="mb-0">Import from Zoom</h5>';
echo '<small class="text-muted">Browse your Zoom cloud recordings and import directly — no downloading to your PC.</small>';
echo '</div>';
echo '<a href="' . $zoomurl . '" class="btn btn-info">Open Zoom Recordings</a>';
echo '</div></div>';

echo '<h4>Course Playlists</h4>';

// Create playlist form.
echo '<div class="card mb-3"><div class="card-body">';
echo '<form method="post" action="' . $PAGE->url->out(false) . '" class="form-inline">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="createplaylist">';
echo '<input type="text" name="name" class="form-control mr-2 mb-1" placeholder="New playlist name" required>';
echo '<label class="mr-2 mb-1"><input type="checkbox" name="sequential" value="1" class="mr-1"> Sequential</label>';
echo '<button type="submit" class="btn btn-success mb-1">Create Playlist</button>';
echo '</form></div></div>';

$playlists = $DB->get_records('local_securevideo_plists', null, 'timecreated DESC');
$allvideos = $DB->get_records('local_securevideo_videos', null, 'name ASC');

if (!$playlists) {
    echo '<p class="text-muted">No playlists yet.</p>';
}

foreach ($playlists as $pl) {
    $embedurl = (new moodle_url('/local/securevideo/embed.php', ['playlist' => $pl->id]))->out(false);
    $embedcode = '<iframe src="' . s($embedurl) . '" width="100%" height="600" frameborder="0" allowfullscreen></iframe>';
    $plvideos = $DB->get_records('local_securevideo_plvids', ['playlistid' => $pl->id], 'sortorder ASC');

    echo '<div class="card mb-3 border-primary" id="plcard_' . $pl->id . '"><div class="card-body">';

    // Header.
    echo '<div class="d-flex justify-content-between align-items-center mb-2">';
    echo '<div>';
    echo '<h5 class="mb-0">' . s($pl->name) . '</h5>';
    echo '<span id="seqbadge_' . $pl->id . '" class="badge badge-' . ($pl->sequential ? 'danger' : 'success') . '">';
    echo $pl->sequential ? 'SEQUENTIAL' : 'FREE ACCESS';
    echo '</span> <small class="text-muted" id="plcount_' . $pl->id . '">' . count($plvideos) . ' video(s)</small>';
    echo '</div>';
    echo '<div>';
    // Toggle sequential.
    echo '<form method="post" action="' . $PAGE->url->out(false) . '" style="display:inline">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" value="toggleseq">';
    echo '<input type="hidden" name="plid" value="' . $pl->id . '">';
    echo '<button type="submit" id="seqbtn_' . $pl->id . '" class="btn btn-sm btn-outline-secondary mr-1">';
    echo $pl->sequential ? 'Disable Sequential' : 'Enable Sequential';
    echo '</button></form>';
    // Delete playlist.
    echo '<form method="post" action="' . $PAGE->url->out(false) . '" style="display:inline">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="action" value="deleteplaylist">';
    echo '<input type="hidden" name="plid" value="' . $pl->id . '">';
    echo '<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this playlist?\')">Delete</button>';
    echo '</form>';
    echo '</div></div>';

    // Embed code.
    echo '<div class="mb-3">';
    echo '<label class="font-weight-bold small text-uppercase">Embed Code (paste in Moodle course)</label>';
    echo '<input type="text" readonly class="form-control form-control-sm" style="font-family:monospace;font-size:0.75rem" value=\'' . s($embedcode) . '\' onclick="this.select()">';
    echo '<small class="text-muted">New videos added to this playlist appear automatically.</small>';
    echo '</div>';

    // Video list in playlist — draggable for reordering.
    echo '<label class="font-weight-bold small text-uppercase">';
    echo 'Videos in Playlist';
    echo ' <span class="text-muted font-weight-normal" style="font-size:11px;font-style:italic">drag to reorder</span>';
    echo '<span class="sv-order-saving" id="saving_' . $pl->id . '" style="display:none">Saving...</span>';
    echo '</label>';
    if ($plvideos) {
        echo '<div id="plvlist_' . $pl->id . '" class="mb-2">';
        $idx = 1;
        foreach ($plvideos as $plv) {
            $vname = isset($allvideos[$plv->videoid]) ? s($allvideos[$plv->videoid]->name) : '(deleted)';
            echo '<div class="sv-plv-row" data-vid="' . $plv->videoid . '">';
            echo '<span class="sv-drag-handle" title="Drag to reorder">&#8597;</span>';
            echo '<span class="sv-plv-num">' . $idx . '</span>';
            echo '<span class="sv-plv-name">' . $vname . '</span>';
            echo '<form method="post" action="' . $PAGE->url->out(false) . '" style="display:inline">';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
            echo '<input type="hidden" name="action" value="removevideo">';
            echo '<input type="hidden" name="plid" value="' . $pl->id . '">';
            echo '<input type="hidden" name="vid" value="' . $plv->videoid . '">';
            echo '<button type="submit" class="btn btn-sm btn-outline-danger py-0">&times;</button></form>';
            echo '</div>';
            $idx++;
        }
        echo '</div>';
    } else {
        echo '<p class="text-muted small" id="plvlist_' . $pl->id . '">No videos added yet.</p>';
    }

    // Add video form.
    if ($allvideos) {
        echo '<form method="post" action="' . $PAGE->url->out(false) . '" class="form-inline">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="action" value="addvideo">';
        echo '<input type="hidden" name="plid" value="' . $pl->id . '">';
        echo '<select name="vid" class="form-control form-control-sm mr-2">';
        foreach ($allvideos as $v) {
            echo '<option value="' . $v->id . '">' . s($v->name) . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" class="btn btn-sm btn-primary">Add Video</button>';
        echo '</form>';
    }

    echo '</div></div>';
}

// ── SortableJS init for all playlist video lists ────────────────────────────
$manageurl = $PAGE->url->out(false);
$sk = sesskey();
echo '<script>
(function() {
    var MANAGE_URL = ' . json_encode($manageurl) . ';
    var SESSKEY    = ' . json_encode($sk) . ';

    // Init drag-and-drop for every playlist list on the page.
    document.querySelectorAll("[id^=\"plvlist_\"]").forEach(function(list) {
        var plid = list.id.replace("plvlist_", "");
        var saving = document.getElementById("saving_" + plid);

        Sortable.create(list, {
            handle:    ".sv-drag-handle",
            animation: 150,
            ghostClass: "sortable-ghost",
            dragClass:  "sortable-drag",

            onEnd: function() {
                // Collect new order from DOM.
                var ids = [];
                list.querySelectorAll("[data-vid]").forEach(function(row) {
                    ids.push(row.getAttribute("data-vid"));
                });

                // Renumber the visible badges immediately.
                list.querySelectorAll(".sv-plv-num").forEach(function(badge, i) {
                    badge.textContent = i + 1;
                });

                // Show saving indicator.
                if (saving) { saving.style.display = "inline"; saving.textContent = "Saving..."; }

                // Send to server via AJAX.
                var fd = new FormData();
                fd.append("sesskey", SESSKEY);
                fd.append("action",  "reordervideos");
                fd.append("plid",    plid);
                fd.append("order",   ids.join(","));

                var url = MANAGE_URL;
                fetch(url, {
                    method: "POST",
                    headers: { "X-Requested-With": "XMLHttpRequest" },
                    body: fd
                })
                .then(function(r) { return r.json(); })
                .then(function() {
                    if (saving) { saving.textContent = "Saved ✓"; setTimeout(function(){ saving.style.display = "none"; }, 1500); }
                })
                .catch(function() {
                    if (saving) { saving.textContent = "Error!"; saving.style.color = "red"; }
                });
            }
        });
    });
})();
</script>';

// =========================================================
// ALL VIDEOS
// =========================================================
// =========================================================
// IMPORT FROM MOODLE
// =========================================================
echo '<h4 class="mt-4">Import from Moodle Courses</h4>';
echo '<p class="text-muted small mb-3">Import video files already uploaded to any Moodle course — no re-uploading needed.</p>';

// Query Moodle file store for video files across all courses.
$moodlevideos = $DB->get_records_sql(
    "SELECT f.id AS fileid, f.filename, f.filesize, f.mimetype, f.timemodified,
            c.id AS courseid, c.fullname AS coursename
     FROM {files} f
     JOIN {context} ctx ON ctx.id = f.contextid
     JOIN {course} c ON c.id = ctx.instanceid
     WHERE ctx.contextlevel = :ctxlevel
       AND " . $DB->sql_like('f.mimetype', ':mime') . "
       AND f.filename != '.'
       AND f.filesize > 0
     ORDER BY c.fullname, f.filename",
    ['ctxlevel' => CONTEXT_COURSE, 'mime' => 'video/%']
);

// Also get module-level video files.
$modulevideos = $DB->get_records_sql(
    "SELECT f.id AS fileid, f.filename, f.filesize, f.mimetype, f.timemodified,
            c.id AS courseid, c.fullname AS coursename
     FROM {files} f
     JOIN {context} ctx ON ctx.id = f.contextid
     JOIN {course_modules} cm ON cm.id = ctx.instanceid
     JOIN {course} c ON c.id = cm.course
     WHERE ctx.contextlevel = :ctxlevel
       AND " . $DB->sql_like('f.mimetype', ':mime') . "
       AND f.filename != '.'
       AND f.filesize > 0
     ORDER BY c.fullname, f.filename",
    ['ctxlevel' => CONTEXT_MODULE, 'mime' => 'video/%']
);

$allimport = array_merge((array)$moodlevideos, (array)$modulevideos);

// Remove duplicates by fileid.
$seen = [];
$allimport = array_filter($allimport, function($f) use (&$seen) {
    if (isset($seen[$f->fileid])) return false;
    $seen[$f->fileid] = true;
    return true;
});

if (empty($allimport)) {
    echo '<p class="text-muted">No video files found in any Moodle course.</p>';
} else {
    // Group by course.
    $bycourse = [];
    foreach ($allimport as $f) {
        $bycourse[$f->coursename][] = $f;
    }

    echo '<style>
.sv-course-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 14px; background:#f8f9fa; border:1px solid #dee2e6;
    border-radius:6px; cursor:pointer; margin-bottom:4px;
    user-select:none;
}
.sv-course-header:hover { background:#e9ecef; }
.sv-course-header .sv-toggle-icon { font-size:12px; color:#6c757d; margin-left:8px; }
.sv-course-body { display:none; padding:10px; border:1px solid #dee2e6;
    border-top:none; border-radius:0 0 6px 6px; margin-bottom:8px; }
.sv-course-body.open { display:block; }
</style>';

    foreach ($bycourse as $coursename => $files) {
        $cid = 'svc_' . md5($coursename);
        echo '<div>';
        echo '<div class="sv-course-header" onclick="svToggle(\'' . $cid . '\')">';
        echo '<span><strong>' . s($coursename) . '</strong>'
            . ' &nbsp;<span class="badge badge-secondary">' . count($files) . ' video(s)</span></span>';
        echo '<span class="sv-toggle-icon" id="ico_' . $cid . '">&#9660; Show</span>';
        echo '</div>';
        echo '<div class="sv-course-body" id="' . $cid . '">';

        foreach ($files as $f) {
            $sizefmt = $f->filesize >= 1048576
                ? round($f->filesize / 1048576, 1) . ' MB'
                : round($f->filesize / 1024) . ' KB';
            echo '<form method="post" action="' . $PAGE->url->out(false) . '"'
                . ' class="d-flex align-items-center mb-2 flex-wrap" style="gap:8px">';
            echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
            echo '<input type="hidden" name="action" value="importvideo">';
            echo '<input type="hidden" name="fileid" value="' . $f->fileid . '">';
            echo '<input type="text" name="vidname" class="form-control form-control-sm"'
                . ' value="' . s(pathinfo($f->filename, PATHINFO_FILENAME)) . '"'
                . ' placeholder="Video name" style="max-width:260px" required>';
            echo '<span class="text-muted small">' . s($f->filename) . ' &bull; ' . $sizefmt . '</span>';
            echo '<button type="submit" class="btn btn-sm btn-success">Import</button>';
            echo '</form>';
        }

        echo '</div></div>';
    }

    echo '<script>
function svToggle(id) {
    var body = document.getElementById(id);
    var ico  = document.getElementById("ico_" + id);
    if (!body) return;
    var open = body.classList.toggle("open");
    if (ico) ico.innerHTML = open ? "&#9650; Hide" : "&#9660; Show";
}

// ── Single AJAX handler for ALL manage.php form submissions ──────────────────
// Every form sends X-Requested-With so PHP returns JSON.
// We update the DOM in-place based on the action.
var SKIP_AJAX = ["createplaylist"]; // these still do a full reload (new card needed)

document.addEventListener("submit", function(e) {
    var form = e.target;
    if (!form) return;
    var actionInput = form.querySelector("[name=action]");
    if (!actionInput) return;
    var action = actionInput.value;
    if (SKIP_AJAX.indexOf(action) !== -1) return; // let these reload normally
    e.preventDefault();

    var btn = form.querySelector("button[type=submit]");
    var origLabel = btn ? btn.innerHTML : "";
    if (btn) { btn.disabled = true; btn.textContent = "..."; }

    var data = new FormData(form);
    // form.action is shadowed by <input name="action"> — use getAttribute instead.
    var url = form.getAttribute("action") || window.location.href;
    fetch(url, {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: data
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res.ok) { alert(res.msg || "Action failed."); if(btn){btn.innerHTML=origLabel;btn.disabled=false;} return; }

        if (action === "deletevideo") {
            var row = document.querySelector("tr[data-vid=\"" + res.vid + "\"]");
            if (row) row.remove();

        } else if (action === "deleteplaylist") {
            var card = document.getElementById("plcard_" + res.plid);
            if (card) { card.style.opacity="0"; card.style.transition="opacity .3s"; setTimeout(function(){ card.remove(); }, 300); }

        } else if (action === "renamevideo") {
            // Input already has the new value — just show brief confirmation.
            if (btn) { btn.innerHTML = "&#10003;"; btn.className = "btn btn-sm btn-success"; setTimeout(function(){ btn.innerHTML = origLabel; btn.className = "btn btn-sm btn-outline-primary"; btn.disabled = false; }, 1500); return; }

        } else if (action === "importvideo") {
            if (btn) { btn.textContent = "Done ✓"; btn.className = "btn btn-sm btn-secondary"; }
            var inp = form.querySelector("input[name=vidname]");
            if (inp) inp.disabled = true;
            return;

        } else if (action === "toggleseq") {
            var seq = res.sequential;
            var badge = document.getElementById("seqbadge_" + res.plid);
            var toggleBtn = document.getElementById("seqbtn_" + res.plid);
            if (badge) { badge.textContent = seq ? "SEQUENTIAL" : "FREE ACCESS"; badge.className = "badge badge-" + (seq ? "danger" : "success"); }
            if (toggleBtn) { toggleBtn.textContent = seq ? "Disable Sequential" : "Enable Sequential"; }

        } else if (action === "addvideo") {
            var list = document.getElementById("plvlist_" + res.plid);
            if (list) {
                var items = list.querySelectorAll("li");
                var num = items.length + 1;
                var li = document.createElement("li");
                li.className = "list-group-item d-flex justify-content-between align-items-center py-2";
                li.setAttribute("data-vid", res.vid);
                li.innerHTML = "<span><strong>" + num + ".</strong> " + svEsc(res.vname) + "</span><span><em>Refresh to reorder</em></span>";
                list.appendChild(li);
            } else {
                // No list yet — reload so it renders properly.
                window.location.reload(); return;
            }
            var countEl = document.getElementById("plcount_" + res.plid);
            if (countEl) countEl.textContent = (parseInt(countEl.textContent) + 1) + " video(s)";

        } else if (action === "removevideo") {
            var list2 = document.getElementById("plvlist_" + res.plid);
            if (list2) {
                var item = list2.querySelector("li[data-vid=\"" + res.vid + "\"]");
                if (item) item.remove();
                // Renumber.
                list2.querySelectorAll("li").forEach(function(li, i) {
                    var s2 = li.querySelector("span strong");
                    if (s2) s2.textContent = (i+1) + ".";
                });
            }
            var countEl2 = document.getElementById("plcount_" + res.plid);
            if (countEl2) countEl2.textContent = Math.max(0, parseInt(countEl2.textContent) - 1) + " video(s)";

        } else if (action === "moveup" || action === "movedown") {
            var list3 = document.getElementById("plvlist_" + res.plid);
            if (list3) {
                var target = list3.querySelector("li[data-vid=\"" + res.vid + "\"]");
                if (target) {
                    if (action === "moveup" && target.previousElementSibling)
                        list3.insertBefore(target, target.previousElementSibling);
                    else if (action === "movedown" && target.nextElementSibling)
                        list3.insertBefore(target.nextElementSibling, target);
                    // Renumber.
                    list3.querySelectorAll("li").forEach(function(li, i) {
                        var s3 = li.querySelector("span strong");
                        if (s3) s3.textContent = (i+1) + ".";
                    });
                }
            }
        }

        // Restore button for non-destructive actions.
        if (btn && action !== "importvideo" && action !== "renamevideo") {
            btn.innerHTML = origLabel;
            btn.disabled = false;
        }
    })
    .catch(function() {
        if (btn) { btn.innerHTML = origLabel; btn.disabled = false; }
        alert("Action failed. Please try again.");
    });
});

function svEsc(s) {
    return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
}
</script>';
}

echo '<h4 class="mt-4">All Videos</h4>';
if ($allvideos) {
    $table = new html_table();
    $table->head = ['ID', 'Name', 'Status', 'Actions'];
    $table->attributes['class'] = 'table table-striped';
    $has_pending = false;
    foreach ($allvideos as $v) {
        // Status badge with colour and spinner for in-progress states.
        $attr = 'data-videoid="' . $v->id . '" style="font-size:13px"';
        if ($v->status === 'ready') {
            $statusbadge = '<span class="badge badge-success" ' . $attr . '>&#10003; Ready</span>';
        } else if ($v->status === 'converting') {
            $statusbadge = '<span class="badge badge-info" ' . $attr . '>'
                . '<span class="spinner-border spinner-border-sm mr-1" role="status"></span>Converting...</span>';
            $has_pending = true;
        } else if ($v->status === 'pending') {
            $statusbadge = '<span class="badge badge-warning" ' . $attr . '>'
                . '<span class="spinner-border spinner-border-sm mr-1" role="status"></span>Queued...</span>';
            $has_pending = true;
        } else {
            $statusbadge = '<span class="badge badge-danger" ' . $attr . '>Error</span>';
        }

        // Rename form — inline editable name field.
        $renameform = '<form method="post" action="' . $PAGE->url->out(false) . '" class="d-flex" style="gap:4px">'
            . '<input type="hidden" name="sesskey" value="' . sesskey() . '">'
            . '<input type="hidden" name="action" value="renamevideo">'
            . '<input type="hidden" name="vid" value="' . $v->id . '">'
            . '<input type="text" name="newname" value="' . s($v->name) . '" class="form-control form-control-sm" style="min-width:180px" required>'
            . '<button type="submit" class="btn btn-sm btn-outline-primary">Rename</button>'
            . '</form>';

        $deleteform = '<form method="post" action="' . $PAGE->url->out(false) . '" style="display:inline">'
            . '<input type="hidden" name="sesskey" value="' . sesskey() . '">'
            . '<input type="hidden" name="action" value="deletevideo">'
            . '<input type="hidden" name="vid" value="' . $v->id . '">'
            . '<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this video?\')">Delete</button></form>';

        $row = new html_table_row([$v->id, $renameform, $statusbadge, $deleteform]);
        $row->attributes['data-vid'] = $v->id;
        $table->data[] = $row;
    }
    echo html_writer::table($table);

    // Poll video statuses via AJAX every 5 seconds while any are still converting.
    // NEVER use window.location.reload() — that kills the upload queue.
    if ($has_pending) {
        $statusurl = (new moodle_url('/local/securevideo/status.php'))->out(false);
        echo '<p class="text-muted small" id="svConvertingMsg"><span class="spinner-border spinner-border-sm mr-1"></span>Videos are being converted — statuses update automatically.</p>';
        echo '<script>
(function pollStatus() {
  setTimeout(function() {
    fetch(' . json_encode($statusurl) . ')
      .then(function(r){ return r.json(); })
      .then(function(videos) {
        var allDone = true;
        videos.forEach(function(v) {
          var badge = document.querySelector("[data-videoid=\"" + v.id + "\"]");
          if (badge) {
            if (v.status === "ready") {
              badge.className = "badge badge-success"; badge.innerHTML = "&#10003; Ready";
            } else if (v.status === "converting") {
              badge.className = "badge badge-info"; badge.innerHTML = "<span class=\"spinner-border spinner-border-sm mr-1\"></span>Converting...";
              allDone = false;
            } else if (v.status === "pending") {
              badge.className = "badge badge-warning"; badge.innerHTML = "<span class=\"spinner-border spinner-border-sm mr-1\"></span>Queued...";
              allDone = false;
            } else {
              badge.className = "badge badge-danger"; badge.innerHTML = "Error";
            }
          }
        });
        if (allDone) {
          var msg = document.getElementById("svConvertingMsg");
          if (msg) msg.remove();
        } else {
          pollStatus();
        }
      })
      .catch(function(){ pollStatus(); });
  }, 5000);
})();
</script>';
    }
} else {
    echo '<p class="text-muted">No videos uploaded yet.</p>';
}

// =========================================================
// FORENSIC INVESTIGATION PANEL
// =========================================================
$searchcode = optional_param('searchcode', '', PARAM_ALPHANUMEXT);
$searchuser = optional_param('searchuser', '', PARAM_TEXT);

echo '<hr class="mt-4">';
$forensicurl = (new moodle_url('/local/securevideo/forensic.php'))->out(false);
echo '<div class="d-flex justify-content-between align-items-center mt-4 mb-2">';
echo '<h4 class="mb-0">&#128269; Forensic Investigation</h4>';
echo '<a href="' . $forensicurl . '" class="btn btn-sm btn-outline-danger">&#128247; Analyze Recording for Watermarks</a>';
echo '</div>';
echo '<p class="text-muted small">Look up a leaked recording by its watermark code, or investigate a specific user\'s access history. ';
echo 'Use the <strong>Analyze Recording</strong> button to upload a screenshot/clip and reveal the invisible watermarks.</p>';

// Search form.
echo '<div class="card mb-3"><div class="card-body">';
echo '<form method="get" action="' . $PAGE->url->out(false) . '" class="form-row align-items-end">';
echo '<div class="form-group col-md-4 mb-2">';
echo '<label class="font-weight-bold small text-uppercase">Watermark Code (e.g. A3F2B1C8)</label>';
echo '<input type="text" name="searchcode" class="form-control" placeholder="Paste code from recording"'
    . ' value="' . s($searchcode) . '" maxlength="20" style="font-family:monospace;text-transform:uppercase">';
echo '</div>';
echo '<div class="form-group col-md-4 mb-2">';
echo '<label class="font-weight-bold small text-uppercase">Or search by name / email</label>';
echo '<input type="text" name="searchuser" class="form-control" placeholder="e.g. ahmed or ahmed@school.com"'
    . ' value="' . s($searchuser) . '">';
echo '</div>';
echo '<div class="form-group col-md-2 mb-2">';
echo '<button type="submit" class="btn btn-danger btn-block">Investigate</button>';
echo '</div>';
echo '<div class="form-group col-md-2 mb-2">';
echo '<a href="' . $PAGE->url->out(false) . '" class="btn btn-outline-secondary btn-block">Clear</a>';
echo '</div>';
echo '</form></div></div>';

// Results.
if ($searchcode !== '' || $searchuser !== '') {
    $params = [];
    $where  = [];

    if ($searchcode !== '') {
        $where[]  = 'a.accesscode = :code';
        $params['code'] = strtoupper($searchcode);
    }
    if ($searchuser !== '') {
        $where[]  = '(' . $DB->sql_like('u.firstname', ':fn', false)
                  . ' OR ' . $DB->sql_like('u.lastname',  ':ln', false)
                  . ' OR ' . $DB->sql_like('u.email',     ':em', false) . ')';
        $params['fn'] = '%' . $DB->sql_like_escape($searchuser) . '%';
        $params['ln'] = '%' . $DB->sql_like_escape($searchuser) . '%';
        $params['em'] = '%' . $DB->sql_like_escape($searchuser) . '%';
    }

    $sql = "SELECT a.id, a.accesscode, a.ip, a.timecreated,
                   u.firstname, u.lastname, u.email, u.username,
                   v.name AS videoname
            FROM {local_securevideo_access} a
            JOIN {user} u ON u.id = a.userid
            JOIN {local_securevideo_videos} v ON v.id = a.videoid
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.timecreated DESC";

    $records = $DB->get_records_sql($sql, $params, 0, 200);

    if (empty($records)) {
        echo '<div class="alert alert-warning">No access records found for that search.</div>';
    } else {
        echo '<div class="alert alert-info small mb-2">'
            . count($records) . ' access record(s) found.</div>';

        $table = new html_table();
        $table->head = ['Code', 'Full Name', 'Email', 'Username', 'IP Address', 'Video', 'Watched At', 'Security Events'];
        $table->attributes['class'] = 'table table-sm table-bordered table-striped';

        foreach ($records as $r) {
            // Count security events for this code.
            $logfile = $CFG->dataroot . '/securevideo/security_events.log';
            $evcount = 0;
            if (file_exists($logfile)) {
                $lines = file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $ev = json_decode($line, true);
                    if ($ev && isset($ev['accesscode']) && $ev['accesscode'] === $r->accesscode) {
                        $evcount++;
                    }
                }
            }

            $evbadge = $evcount > 0
                ? '<span class="badge badge-warning">' . $evcount . ' events</span>'
                : '<span class="badge badge-success">Clean</span>';

            $table->data[] = [
                '<code style="font-size:13px;font-weight:700">' . s($r->accesscode) . '</code>',
                '<strong>' . s($r->firstname . ' ' . $r->lastname) . '</strong>',
                s($r->email),
                s($r->username),
                s($r->ip),
                s($r->videoname),
                userdate($r->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                $evbadge,
            ];
        }
        echo html_writer::table($table);
    }
}

// Security events log — show last 50 events if no search active.
echo '<h5 class="mt-4">Recent Security Events</h5>';
$logfile = $CFG->dataroot . '/securevideo/security_events.log';
if (!file_exists($logfile)) {
    echo '<p class="text-muted small">No security events logged yet.</p>';
} else {
    $lines = file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($lines);

    // Filter by code if searching.
    if ($searchcode !== '') {
        $lines = array_filter($lines, function($l) use ($searchcode) {
            $e = json_decode($l, true);
            return $e && isset($e['accesscode']) && strtoupper($e['accesscode']) === strtoupper($searchcode);
        });
    }
    if ($searchuser !== '') {
        $lines = array_filter($lines, function($l) use ($searchuser) {
            $e = json_decode($l, true);
            return $e && isset($e['user']) && stripos($e['user'], $searchuser) !== false;
        });
    }

    $lines = array_slice(array_values($lines), 0, 100);

    if (empty($lines)) {
        echo '<p class="text-muted small">No matching events.</p>';
    } else {
        $evtable = new html_table();
        $evtable->head = ['Timestamp', 'User', 'Code', 'Event', 'Details'];
        $evtable->attributes['class'] = 'table table-sm table-bordered table-striped';

        $high_risk = ['screen_capture', 'screen_capture_api', 'webrtc_screen_share',
                      'recording_extension', 'watermark_tamper', 'watermark_hidden',
                      'devtools_opened', 'tamper'];

        foreach ($lines as $line) {
            $ev = json_decode($line, true);
            if (!$ev) continue;

            $eventname = $ev['event'] ?? '';
            $isrisk = in_array($eventname, $high_risk);
            $evrow = $isrisk
                ? '<span class="badge badge-danger">' . s($eventname) . '</span>'
                : '<span class="badge badge-secondary">' . s($eventname) . '</span>';

            $evtable->data[] = [
                s($ev['ts'] ?? ''),
                s($ev['user'] ?? ''),
                '<code>' . s($ev['code'] ?? '') . '</code>',
                $evrow,
                s(isset($ev['details']) ? $ev['details'] : (isset($ev['event']) ? '' : '')),
            ];
        }
        echo html_writer::table($evtable);
        if (count($lines) === 100) {
            echo '<p class="text-muted small">Showing most recent 100 events.</p>';
        }
    }
}

echo $OUTPUT->footer();
