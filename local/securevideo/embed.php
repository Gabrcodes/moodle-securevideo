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
 * Single-iframe embed page: video browser + secure player.
 * Supports playlists, sequential mode, progress tracking.
 * User identity auto-fetched from Moodle session.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login(null, false);
$context = context_system::instance();

// Any logged-in non-guest user can view. Real security comes from the HLS token + session.
if (isguestuser()) {
    throw new moodle_exception('nopermission', 'local_securevideo');
}

$playlistid = optional_param('playlist', 0, PARAM_INT);
$json = optional_param('json', 0, PARAM_INT);

$playlist = null;
$sequential = false;
$playlistname = 'All Videos';
$videolist = [];

if ($playlistid) {
    $playlist = $DB->get_record('local_securevideo_plists', ['id' => $playlistid]);
}

if ($playlist) {
    $playlistname = $playlist->name;
    $sequential = (bool)$playlist->sequential;
    $plvids = $DB->get_records('local_securevideo_plvids', ['playlistid' => $playlistid], 'sortorder ASC');
    $idx = 1;
    foreach ($plvids as $plv) {
        $v = $DB->get_record('local_securevideo_videos', ['id' => $plv->videoid, 'status' => 'ready']);
        if (!$v) continue;
        $prog = $DB->get_record('local_securevideo_progress', [
            'userid' => $USER->id, 'playlistid' => $playlistid, 'videoid' => $v->id,
        ]);
        $thumbpath = local_securevideo_get_storage_path() . '/thumbs/' . $v->id . '.jpg';
        $videolist[] = [
            'id' => (int)$v->id,
            'name' => $v->name,
            'index' => $idx++,
            'percent' => $prog ? (int)$prog->percent : 0,
            'completed' => $prog ? (bool)$prog->completed : false,
            'lastPosition' => $prog ? (int)$prog->lastposition : 0,
            'thumb' => file_exists($thumbpath)
                ? (new moodle_url('/local/securevideo/thumb.php', ['id' => $v->id]))->out(false)
                : '',
        ];
    }
} else {
    $videos = $DB->get_records('local_securevideo_videos', ['status' => 'ready'], 'timecreated DESC');
    $idx = 1;
    foreach ($videos as $v) {
        $thumbpath = local_securevideo_get_storage_path() . '/thumbs/' . $v->id . '.jpg';
        $videolist[] = [
            'id' => (int)$v->id,
            'name' => $v->name,
            'index' => $idx++,
            'percent' => 0,
            'completed' => false,
            'lastPosition' => 0,
            'thumb' => file_exists($thumbpath)
                ? (new moodle_url('/local/securevideo/thumb.php', ['id' => $v->id]))->out(false)
                : '',
        ];
    }
}

// JSON endpoint for refreshing progress without full page reload.
if ($json) {
    header('Content-Type: application/json');
    echo json_encode($videolist);
    exit;
}

$userfullname = fullname($USER);
$playerurl = (new moodle_url('/local/securevideo/player.php'))->out(false);

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; frame-src 'self';");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo s($playlistname); ?> - Nancy Academy</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap');
:root{--nb:#00AEEF;--ny:#FFED00;--nr:#FF1F1F;--ng:#39B54A;--nk:#000;--nw:#fff;--bm:3px solid #000;--ss:4px 4px 0 #000;--sk:skew(-3deg)}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Poppins',sans-serif;background:#fff;color:#000;overflow-x:hidden}
.nh{background:#000;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:4px solid var(--ny);position:relative;flex-wrap:wrap;gap:6px}
.nh::after{content:'';position:absolute;bottom:-4px;left:0;right:0;height:4px;background:repeating-linear-gradient(90deg,var(--nr) 0,var(--nr) 15px,var(--ny) 15px,var(--ny) 30px,var(--nb) 30px,var(--nb) 45px,var(--ng) 45px,var(--ng) 60px)}
.nh h1{font-size:18px;font-weight:900;text-transform:uppercase;color:#fff;transform:var(--sk)}
.nh h1 span{color:var(--ny)}
.eu{color:var(--ny);font-size:11px;font-weight:700;text-transform:uppercase}
@media(max-width:500px){.nh h1{font-size:15px}.nh{padding:10px 14px}}
.view{display:none}.view.active{display:flex;flex-direction:column}
.vl{padding:16px;flex:1;overflow-y:auto}
.vl-title{font-size:14px;font-weight:900;text-transform:uppercase;transform:var(--sk);display:inline-block;background:#000;color:#fff;padding:3px 14px;box-shadow:3px 3px 0 #000;margin-bottom:14px}
.vl-sub{font-size:11px;color:#666;font-weight:600;margin-bottom:14px}
.vg{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
@media(max-width:400px){.vg{grid-template-columns:1fr 1fr;gap:8px}}
.vt{border:var(--bm);background:#fff;box-shadow:var(--ss);cursor:pointer;transition:all .12s;overflow:hidden;text-decoration:none;color:#000;display:block;position:relative}
.vt:hover{transform:translate(-3px,-3px);box-shadow:9px 9px 0 #000}
.vt:active{transform:translate(3px,3px);box-shadow:1px 1px 0 #000}
.vt.locked{opacity:.5;cursor:not-allowed;pointer-events:none}
.vt.locked::after{content:'LOCKED';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) var(--sk);background:var(--nr);color:#fff;padding:4px 12px;border:2px solid #000;font-size:10px;font-weight:900;z-index:2;box-shadow:2px 2px 0 #000}
.vt-thumb{background:#111;height:90px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;background-size:cover;background-position:center}
.vt-thumb::before{content:'';position:absolute;inset:0;background:rgba(0,0,0,0.3)}
.vt-thumb.no-thumb::before{background-image:radial-gradient(rgba(255,255,255,.05) 1px,transparent 1px);background-size:12px 12px}
.vt-play{font-size:28px;color:var(--ny);text-shadow:2px 2px 0 #000;z-index:1;transition:transform .15s}
.vt:hover .vt-play{transform:scale(1.2)}
.vt-num{position:absolute;top:6px;left:6px;background:#000;color:var(--ny);width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;z-index:1}
.vt-check{position:absolute;top:6px;right:6px;background:var(--ng);color:#fff;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;z-index:1;border:2px solid #000}
.vt-info{padding:10px 12px}
.vt-name{font-size:11px;font-weight:900;text-transform:uppercase;line-height:1.3}
.vt-prog{margin-top:6px;height:6px;background:#eee;border:1px solid #ccc}
.vt-prog-fill{height:100%;transition:width .3s}
.vt-prog-txt{font-size:9px;font-weight:800;color:#666;margin-top:2px;text-align:right}
.empty{text-align:center;padding:50px 20px}
.empty h3{font-size:16px;font-weight:900;text-transform:uppercase;color:#999}
.empty p{font-size:12px;color:#aaa;margin-top:6px}
.pt{display:flex;align-items:center;gap:10px;padding:8px 14px;background:#f4f4f4;border-bottom:var(--bm);background-image:radial-gradient(#ccc 1px,transparent 1px);background-size:15px 15px;flex-wrap:wrap}
.back{font-family:'Poppins',sans-serif;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:1px;padding:7px 14px;border:var(--bm);cursor:pointer;background:var(--ny);color:#000;box-shadow:3px 3px 0 #000;transition:all .1s;transform:var(--sk);flex-shrink:0}
.back:hover{transform:translate(-2px,-2px) var(--sk);box-shadow:5px 5px 0 #000}
.back:active{transform:translate(3px,3px) var(--sk);box-shadow:0 0 0}
.np{font-size:12px;font-weight:900;text-transform:uppercase;transform:var(--sk);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.next-btn{font-family:'Poppins',sans-serif;font-size:11px;font-weight:900;text-transform:uppercase;padding:7px 14px;border:var(--bm);cursor:pointer;background:var(--ng);color:#fff;box-shadow:3px 3px 0 #000;transition:all .1s;transform:var(--sk);flex-shrink:0;text-shadow:1px 1px 0 #000;display:none}
.next-btn:hover{transform:translate(-2px,-2px) var(--sk);box-shadow:5px 5px 0 #000}
.next-btn.show{display:block}
.pf{flex:1;border:none;width:100%;background:#000}
</style>
</head>
<body>
<div class="nh">
    <h1><span>Nancy</span> Academy</h1>
    <div class="eu"><?php echo s($userfullname); ?></div>
</div>

<div class="view active" id="vList" style="height:calc(100vh - 52px)">
    <div class="vl">
        <div class="vl-title"><?php echo s($playlistname); ?></div>
        <?php if ($sequential): ?><div class="vl-sub">Complete each video to unlock the next one.</div><?php endif; ?>
        <?php if (empty($videolist)): ?>
        <div class="empty"><h3>No Videos Yet</h3><p>Check back later.</p></div>
        <?php else: ?>
        <div class="vg" id="vGrid"></div>
        <?php endif; ?>
    </div>
</div>

<div class="view" id="vPlayer" style="height:100vh">
    <div class="pt">
        <button class="back" onclick="showList()">&larr; Back</button>
        <span class="np" id="npText"></span>
        <button class="next-btn" id="nextBtn" onclick="playNext()">Next &rarr;</button>
    </div>
    <iframe class="pf" id="pFrame" allowfullscreen allow="autoplay"></iframe>
</div>

<script>
var videos = <?php echo json_encode($videolist); ?>;
var sequential = <?php echo $sequential ? 'true' : 'false'; ?>;
var playlistId = <?php echo json_encode($playlistid); ?>;
var playerBase = <?php echo json_encode($playerurl); ?>;
var currentIdx = -1;
var grid = document.getElementById('vGrid');

function buildGrid() {
    if (!grid) return;
    grid.innerHTML = '';
    videos.forEach(function(v, i) {
        var locked = sequential && i > 0 && !videos[i-1].completed;
        var tile = document.createElement('a');
        tile.className = 'vt' + (locked ? ' locked' : '');
        tile.href = '#';
        if (!locked) tile.onclick = function(e) { e.preventDefault(); playVideo(i); };
        else tile.onclick = function(e) { e.preventDefault(); };
        var progColor = v.completed ? 'var(--ng)' : (v.percent > 0 ? 'var(--ny)' : '#ddd');
        var thumbAttr = v.thumb
            ? 'class="vt-thumb" style="background-image:url(' + v.thumb + ')"'
            : 'class="vt-thumb no-thumb"';
        tile.innerHTML = '<div ' + thumbAttr + '>'
            + '<div class="vt-num">' + v.index + '</div>'
            + (v.completed ? '<div class="vt-check">&#10003;</div>' : '')
            + '<div class="vt-play">' + (locked ? '&#128274;' : '&#9654;') + '</div>'
            + '</div>'
            + '<div class="vt-info">'
            + '<div class="vt-name">' + esc(v.name) + '</div>'
            + '<div class="vt-prog"><div class="vt-prog-fill" style="width:'+v.percent+'%;background:'+progColor+'"></div></div>'
            + '<div class="vt-prog-txt">' + v.percent + '%</div></div>';
        grid.appendChild(tile);
    });
}
buildGrid();

function playVideo(idx) {
    currentIdx = idx;
    var v = videos[idx];
    document.getElementById('vList').classList.remove('active');
    document.getElementById('vList').style.display = 'none';
    var pv = document.getElementById('vPlayer');
    pv.classList.add('active');
    pv.style.display = 'flex';
    document.getElementById('npText').textContent = v.name;
    document.getElementById('pFrame').src = playerBase + '?id=' + v.id + '&playlist=' + playlistId + '&autoplay=1';
    var nb = document.getElementById('nextBtn');
    nb.classList.toggle('show', idx < videos.length - 1 && !(sequential && !v.completed));
}

function playNext() {
    if (currentIdx < videos.length - 1) playVideo(currentIdx + 1);
}

function showList() {
    document.getElementById('vPlayer').classList.remove('active');
    document.getElementById('vPlayer').style.display = 'none';
    document.getElementById('pFrame').src = '';
    document.getElementById('vList').classList.add('active');
    document.getElementById('vList').style.display = 'flex';
    // Refresh progress.
    fetch(window.location.href + (window.location.href.includes('?') ? '&' : '?') + 'json=1')
        .then(function(r){return r.json();})
        .then(function(data){ videos = data; buildGrid(); })
        .catch(function(){});
}

window.addEventListener('message', function(e) {
    if (e.origin !== window.location.origin) return;
    if (e.data && e.data.type === 'videoComplete' && currentIdx >= 0) {
        videos[currentIdx].completed = true;
        videos[currentIdx].percent = 100;
        if (currentIdx < videos.length - 1) document.getElementById('nextBtn').classList.add('show');
    }
    if (e.data && e.data.type === 'videoProgress' && currentIdx >= 0) {
        videos[currentIdx].percent = Math.round(e.data.percent);
    }
});

function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
</script>
</body>
</html>
