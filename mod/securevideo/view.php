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
 * Secure Video activity — main view page.
 * Renders natively inside the Moodle theme (no iframe).
 * Video grid is native Moodle HTML; player opens in a full-screen modal overlay.
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/securevideo/lib.php');
require_once($CFG->libdir  . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT);   // course module ID
$n  = optional_param('n',  0, PARAM_INT);   // activity instance ID

if ($id) {
    $cm       = get_coursemodule_from_id('securevideo', $id, 0, false, MUST_EXIST);
    $course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $instance = $DB->get_record('securevideo', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $instance = $DB->get_record('securevideo', ['id' => $n], '*', MUST_EXIST);
    $course   = $DB->get_record('course', ['id' => $instance->course], '*', MUST_EXIST);
    $cm       = get_coursemodule_from_instance('securevideo', $instance->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/securevideo:view', $context);

// ── Mark as viewed (for view-based completion) ───────────────────────────────
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// ── Load playlist and videos ─────────────────────────────────────────────────
$playlist = $DB->get_record('local_securevideo_plists', ['id' => $instance->playlistid]);
$sequential = $playlist ? (bool)$playlist->sequential : false;

$plvideos = $playlist
    ? $DB->get_records('local_securevideo_plvids', ['playlistid' => $instance->playlistid], 'sortorder ASC')
    : [];

$videolist = [];
foreach ($plvideos as $plv) {
    $v = $DB->get_record('local_securevideo_videos', ['id' => $plv->videoid, 'status' => 'ready']);
    if (!$v) continue;

    $prog = $DB->get_record('local_securevideo_progress', [
        'userid'     => $USER->id,
        'playlistid' => $instance->playlistid,
        'videoid'    => $v->id,
    ]);

    $thumbpath = $CFG->dataroot . '/securevideo/thumbs/' . $v->id . '.jpg';
    $thumburl  = file_exists($thumbpath)
        ? (new moodle_url('/local/securevideo/thumb.php', ['id' => $v->id]))->out(false)
        : '';

    $playerurl = (new moodle_url('/local/securevideo/player.php', [
        'id'       => $v->id,
        'playlist' => $instance->playlistid,
        'autoplay' => 1,
    ]))->out(false);

    $videolist[] = [
        'id'           => (int)$v->id,
        'name'         => $v->name,
        'index'        => count($videolist) + 1,
        'percent'      => $prog ? (int)$prog->percent : 0,
        'completed'    => $prog ? (bool)$prog->completed : false,
        'lastPosition' => $prog ? (int)$prog->lastposition : 0,
        'thumb'        => $thumburl,
        'playerurl'    => $playerurl,
    ];
}

// ── Check and update automatic completion ────────────────────────────────────
if ($completion->is_enabled($cm) && !empty($instance->completionpercent)) {
    $state = securevideo_get_completion_state($course, $cm, $USER->id, COMPLETION_AND);
    if ($state) {
        $completion->update_state($cm, COMPLETION_COMPLETE);
    }
}

// ── Page setup ───────────────────────────────────────────────────────────────
$PAGE->set_url('/mod/securevideo/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($instance);

$progressurl = (new moodle_url('/local/securevideo/progress.php'))->out(false);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));

if (!empty($instance->intro)) {
    echo $OUTPUT->box(format_module_intro('securevideo', $instance, $cm->id), 'generalbox mod_introbox');
}

if (empty($videolist)) {
    echo $OUTPUT->notification(get_string('novideosinplaylist', 'mod_securevideo'), 'info');
    echo $OUTPUT->footer();
    exit;
}
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap');

/* ── Video grid ─────────────────────────────────────────── */
.sv-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin: 20px 0;
    font-family: 'Poppins', sans-serif;
}
@media(max-width:480px) { .sv-grid { grid-template-columns: 1fr 1fr; gap: 10px; } }

.sv-tile {
    border: 3px solid #000;
    background: #fff;
    box-shadow: 4px 4px 0 #000;
    cursor: pointer;
    transition: all .12s;
    overflow: hidden;
    position: relative;
}
.sv-tile:hover  { transform: translate(-3px,-3px); box-shadow: 8px 8px 0 #000; }
.sv-tile:active { transform: translate(3px,3px);   box-shadow: 1px 1px 0 #000; }
.sv-tile.locked { opacity: .5; cursor: not-allowed; pointer-events: none; }
.sv-tile.locked::after {
    content: 'LOCKED';
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%,-50%) skew(-3deg);
    background: #FF1F1F; color: #fff;
    padding: 4px 12px; border: 2px solid #000;
    font-size: 10px; font-weight: 900; z-index: 2;
    box-shadow: 2px 2px 0 #000;
    font-family: 'Poppins', sans-serif;
}

.sv-thumb {
    background: #111;
    height: 110px;
    display: flex; align-items: center; justify-content: center;
    position: relative;
    background-size: cover; background-position: center;
}
.sv-thumb::before { content: ''; position: absolute; inset: 0; background: rgba(0,0,0,.3); }
.sv-thumb-num {
    position: absolute; top: 6px; left: 6px;
    background: #000; color: #FFED00;
    width: 24px; height: 24px;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 900; z-index: 1;
    font-family: 'Poppins', sans-serif;
}
.sv-thumb-check {
    position: absolute; top: 6px; right: 6px;
    background: #39B54A; color: #fff; border: 2px solid #000;
    width: 24px; height: 24px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 900; z-index: 1;
}
.sv-play-icon {
    font-size: 32px; color: #FFED00;
    text-shadow: 2px 2px 0 #000; z-index: 1;
    transition: transform .15s;
}
.sv-tile:hover .sv-play-icon { transform: scale(1.2); }

.sv-tile-info { padding: 10px 12px; }
.sv-tile-name {
    font-size: 12px; font-weight: 900;
    text-transform: uppercase; line-height: 1.3;
    font-family: 'Poppins', sans-serif;
    margin-bottom: 6px;
}
.sv-prog-wrap { height: 6px; background: #eee; border: 1px solid #ccc; border-radius: 2px; }
.sv-prog-fill { height: 100%; border-radius: 2px; transition: width .3s; }
.sv-prog-txt  { font-size: 10px; color: #666; font-weight: 700; text-align: right; margin-top: 2px; }

.sv-seq-note {
    background: #fffbeb; border: 2px solid #FFED00;
    padding: 8px 14px; font-size: 13px; font-weight: 600;
    margin-bottom: 16px; display: inline-block;
    font-family: 'Poppins', sans-serif;
}

/* ── Full-screen player modal ───────────────────────────── */
#sv-modal {
    display: none;
    position: fixed; inset: 0; z-index: 99999;
    background: #000;
}
#sv-modal.open { display: flex; flex-direction: column; }
#sv-modal-bar {
    display: flex; align-items: center; gap: 12px;
    padding: 8px 16px;
    background: #000; border-bottom: 3px solid #FFED00;
    background-image: repeating-linear-gradient(
        90deg,#FF1F1F 0,#FF1F1F 15px,#FFED00 15px,#FFED00 30px,
        #00AEEF 30px,#00AEEF 45px,#39B54A 45px,#39B54A 60px
    );
    background-size: 60px 4px; background-repeat: repeat-x;
    background-position: bottom;
    padding-bottom: 12px;
    flex-shrink: 0;
}
#sv-close-btn {
    font-family: 'Poppins', sans-serif; font-size: 12px; font-weight: 900;
    text-transform: uppercase; letter-spacing: 1px;
    padding: 7px 16px; border: 3px solid #FFED00; cursor: pointer;
    background: #FFED00; color: #000;
    box-shadow: 3px 3px 0 rgba(255,237,0,.4);
    transition: all .1s; flex-shrink: 0;
    transform: skew(-3deg);
}
#sv-close-btn:hover  { transform: translate(-2px,-2px) skew(-3deg); box-shadow: 5px 5px 0 rgba(255,237,0,.4); }
#sv-close-btn:active { transform: translate(2px,2px)  skew(-3deg); box-shadow: none; }
#sv-modal-title {
    font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 900;
    text-transform: uppercase; color: #fff;
    transform: skew(-3deg); flex: 1;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
#sv-next-btn {
    font-family: 'Poppins', sans-serif; font-size: 12px; font-weight: 900;
    text-transform: uppercase; padding: 7px 16px; border: 3px solid #39B54A;
    cursor: pointer; background: #39B54A; color: #fff;
    box-shadow: 3px 3px 0 rgba(57,181,74,.4);
    transform: skew(-3deg); display: none; flex-shrink: 0;
    text-shadow: 1px 1px 0 #000;
}
#sv-next-btn:hover  { transform: translate(-2px,-2px) skew(-3deg); }
#sv-next-btn.show   { display: block; }
#sv-player-frame    { flex: 1; border: none; width: 100%; background: #000; }
</style>

<?php if ($sequential): ?>
<div class="sv-seq-note">&#9654; Complete each video to unlock the next one.</div>
<?php endif; ?>

<div class="sv-grid" id="svGrid"></div>

<!-- Full-screen player modal — inside Moodle page, no outer iframe needed -->
<div id="sv-modal">
    <div id="sv-modal-bar">
        <button id="sv-close-btn" onclick="svClose()">&larr; Back</button>
        <span id="sv-modal-title"></span>
        <button id="sv-next-btn" onclick="svNext()">Next &rarr;</button>
    </div>
    <iframe id="sv-player-frame" allowfullscreen allow="autoplay"></iframe>
</div>

<script>
(function() {
    'use strict';

    var videos     = <?php echo json_encode($videolist); ?>;
    var sequential = <?php echo $sequential ? 'true' : 'false'; ?>;
    var playlistId = <?php echo (int)$instance->playlistid; ?>;
    var progressUrl = <?php echo json_encode($progressurl); ?>;
    var currentIdx  = -1;

    var grid   = document.getElementById('svGrid');
    var modal  = document.getElementById('sv-modal');
    var frame  = document.getElementById('sv-player-frame');
    var title  = document.getElementById('sv-modal-title');
    var nextBtn = document.getElementById('sv-next-btn');

    // Build the video grid.
    function buildGrid() {
        grid.innerHTML = '';
        videos.forEach(function(v, i) {
            var locked = sequential && i > 0 && !videos[i-1].completed;
            var pct    = v.percent || 0;
            var progColor = v.completed ? '#39B54A' : (pct > 0 ? '#FFED00' : '#eee');

            var tile = document.createElement('div');
            tile.className = 'sv-tile' + (locked ? ' locked' : '');
            tile.innerHTML =
                '<div class="sv-thumb"' + (v.thumb ? ' style="background-image:url(' + v.thumb + ')"' : '') + '>' +
                    '<div class="sv-thumb-num">' + v.index + '</div>' +
                    (v.completed ? '<div class="sv-thumb-check">&#10003;</div>' : '') +
                    '<div class="sv-play-icon">' + (locked ? '&#128274;' : '&#9654;') + '</div>' +
                '</div>' +
                '<div class="sv-tile-info">' +
                    '<div class="sv-tile-name">' + esc(v.name) + '</div>' +
                    '<div class="sv-prog-wrap"><div class="sv-prog-fill" style="width:' + pct + '%;background:' + progColor + '"></div></div>' +
                    '<div class="sv-prog-txt">' + pct + '%</div>' +
                '</div>';

            if (!locked) {
                tile.addEventListener('click', function() { svPlay(i); });
            }
            grid.appendChild(tile);
        });
    }

    // Open the modal with a specific video.
    window.svPlay = function(idx) {
        currentIdx = idx;
        var v = videos[idx];
        title.textContent = v.name;
        frame.src = v.playerurl;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        updateNextBtn();
    };

    window.svClose = function() {
        modal.classList.remove('open');
        frame.src = '';
        document.body.style.overflow = '';
        // Refresh progress from server then rebuild grid.
        refreshProgress();
    };

    window.svNext = function() {
        if (currentIdx < videos.length - 1) svPlay(currentIdx + 1);
    };

    function updateNextBtn() {
        var hasNext = currentIdx < videos.length - 1;
        var nextLocked = sequential && currentIdx >= 0 && !videos[currentIdx].completed;
        nextBtn.classList.toggle('show', hasNext && !nextLocked);
    }

    // Listen for messages from the player iframe (progress + completion).
    window.addEventListener('message', function(e) {
        if (e.origin !== window.location.origin) return;

        if (e.data && e.data.type === 'videoProgress' && currentIdx >= 0) {
            videos[currentIdx].percent = Math.round(e.data.percent);
        }
        if (e.data && e.data.type === 'videoComplete' && currentIdx >= 0) {
            videos[currentIdx].completed = true;
            videos[currentIdx].percent   = 100;
            updateNextBtn();
            // Notify Moodle completion system via AJAX.
            checkMoodleCompletion();
        }
    });

    // Refresh progress data from server without page reload.
    function refreshProgress() {
        var embedUrl = <?php echo json_encode(
            (new moodle_url('/local/securevideo/embed.php', ['playlist' => $instance->playlistid, 'json' => 1]))->out(false)
        ); ?>;
        fetch(embedUrl)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                data.forEach(function(v) {
                    var found = videos.find(function(x) { return x.id === v.id; });
                    if (found) {
                        found.percent   = v.percent;
                        found.completed = v.completed;
                    }
                });
                buildGrid();
            })
            .catch(function() { buildGrid(); });
    }

    // Tell Moodle to re-check completion state for this activity.
    function checkMoodleCompletion() {
        var cmId = <?php echo (int)$cm->id; ?>;
        // Use Moodle's AJAX service to update completion.
        fetch(<?php echo json_encode((new moodle_url('/lib/ajax/service.php', ['sesskey' => sesskey()]))->out(false)); ?>, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify([{
                index: 0,
                methodname: 'core_completion_update_activity_completion_status_manually',
                args: { cmid: cmId, completed: true }
            }])
        }).catch(function() {});
    }

    // Keyboard shortcut: Escape closes modal.
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) svClose();
    });

    buildGrid();
})();

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>

<?php
echo $OUTPUT->footer();
