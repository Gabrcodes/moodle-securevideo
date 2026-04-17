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
 * Secure video player with 22 security layers.
 * Nancy Academy theme, real watch-time tracking, autoplay, mobile-friendly.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login(null, false);

$videoid = required_param('id', PARAM_INT);
$playlistid = optional_param('playlist', 0, PARAM_INT);
$autoplay = optional_param('autoplay', 0, PARAM_INT);
$context = context_system::instance();

if (isguestuser()) {
    throw new moodle_exception('nopermission', 'local_securevideo');
}

$video = $DB->get_record('local_securevideo_videos', ['id' => $videoid, 'status' => 'ready']);
if (!$video) {
    throw new moodle_exception('novideofound', 'local_securevideo');
}

$token = local_securevideo_generate_token($videoid, $USER->id);
$accesscode = local_securevideo_generate_access_code($videoid, $USER->id);

$m3u8url = (new moodle_url('/local/securevideo/serve.php', [
    'id' => $videoid, 'file' => 'stream.m3u8', 'token' => $token,
]))->out(false);

$progressurl = (new moodle_url('/local/securevideo/progress.php'))->out(false);
$reporturl = (new moodle_url('/local/securevideo/report.php'))->out(false);

$watermarkopacity = (int)(get_config('local_securevideo', 'watermarkopacity') ?: 15);
$watermarkopacity = max(5, min(50, $watermarkopacity));

$userfullname = fullname($USER);
$watermarktext = $userfullname . ' | ' . $accesscode;
$invisiblehex = substr(hash_hmac('sha256', $USER->id . ':' . $accesscode . ':' . $videoid, sesskey()), 0, 16);

// Get resume position.
$resumepos = 0;
if ($playlistid) {
    $prog = $DB->get_record('local_securevideo_progress', [
        'userid' => $USER->id, 'playlistid' => $playlistid, 'videoid' => $videoid,
    ]);
    if ($prog) {
        $resumepos = (int)$prog->lastposition;
    }
}

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' blob: data: https://fonts.googleapis.com https://fonts.gstatic.com;");
header('Permissions-Policy: display-capture=()');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo s($video->name); ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap');
:root{--nb:#00AEEF;--ny:#FFED00;--nr:#FF1F1F;--ng:#39B54A;--nk:#000;--nw:#fff}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html,body{width:100%;height:100%;overflow:hidden;background:#000;font-family:'Poppins',sans-serif}
.c{position:relative;width:100%;height:100%;user-select:none;-webkit-user-select:none;-webkit-touch-callout:none}
video{width:100%;height:100%;object-fit:contain;background:#000}
video::-webkit-media-controls-enclosure{overflow:hidden}
video::-internal-media-controls-download-button{display:none}
video::-webkit-media-controls-panel{width:calc(100% + 30px)}
.wl{position:absolute;inset:0;pointer-events:none;z-index:10;overflow:hidden}
.wm{position:absolute;color:rgba(255,255,255,<?php echo $watermarkopacity/100; ?>);font-family:'Poppins',monospace;font-size:14px;font-weight:800;letter-spacing:1.5px;white-space:nowrap;text-shadow:2px 2px 0 rgba(0,0,0,0.4);transition:all 3s;text-transform:uppercase}
@media(max-width:500px){.wm{font-size:10px}}
.fl{position:absolute;inset:0;pointer-events:none;z-index:9;overflow:hidden;mix-blend-mode:overlay}
.fm{position:absolute;font-family:monospace;font-size:10px;letter-spacing:2px;white-space:nowrap;color:rgba(128,128,128,0.02)}
.cv{position:absolute;inset:0;pointer-events:none;z-index:8;opacity:0.03;mix-blend-mode:difference}
.warn{position:absolute;inset:0;z-index:100;display:none;justify-content:center;align-items:center;flex-direction:column;background:rgba(0,0,0,0.92);background-image:radial-gradient(rgba(255,255,255,0.03) 1px,transparent 1px);background-size:20px 20px;text-align:center;padding:20px}
.warn.on{display:flex}
.warn h2{font-size:20px;font-weight:900;text-transform:uppercase;background:var(--ny);color:#000;padding:6px 24px;border:3px solid #000;box-shadow:4px 4px 0 #000;transform:skew(-3deg)}
.warn p{color:#aaa;font-size:13px;max-width:380px;line-height:1.6;margin-top:12px}
.warn .wc{font-family:'Poppins',monospace;font-size:14px;font-weight:900;background:var(--nr);color:#fff;padding:4px 16px;border:3px solid #000;box-shadow:3px 3px 0 #000;transform:skew(-3deg);margin-top:12px;text-shadow:1px 1px 0 #000}
.ctrl{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,0.85) 40%);padding:12px 14px 8px;display:flex;align-items:center;gap:10px;z-index:20;opacity:0;transition:opacity .3s;border-top:3px solid var(--ny);flex-wrap:wrap}
.c:hover .ctrl,.c.touch .ctrl{opacity:1}
.cb{background:#000;border:2px solid var(--ny);color:var(--ny);cursor:pointer;font-size:14px;font-weight:900;padding:5px 8px;font-family:'Poppins',sans-serif;transition:all .1s;flex-shrink:0}
.cb:hover{background:var(--ny);color:#000}
@media(max-width:500px){.cb{font-size:12px;padding:4px 6px}}
.pw{flex:1;height:8px;background:rgba(255,255,255,0.15);cursor:pointer;border:2px solid rgba(255,255,255,0.3);min-width:60px}
.pb{height:100%;background:var(--ny);width:0%;transition:width .1s}
.tm{color:var(--ny);font-size:11px;font-family:'Poppins',monospace;min-width:70px;text-align:center;font-weight:700;flex-shrink:0}
@media(max-width:400px){.tm{font-size:9px;min-width:50px}}
.vw{display:flex;align-items:center;gap:4px}
.vs{width:50px;accent-color:var(--ny)}
@media(max-width:500px){.vs{width:35px}}
.sh{position:absolute;inset:0 0 46px 0;z-index:5;cursor:pointer}
.bn{position:absolute;top:8px;left:50%;transform:translateX(-50%) skew(-3deg);background:var(--ny);color:#000;padding:4px 16px;border:3px solid #000;box-shadow:3px 3px 0 #000;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:1px;z-index:50;pointer-events:none;transition:opacity 2s;white-space:nowrap}
@media(max-width:500px){.bn{font-size:9px;padding:3px 10px}}
.pct{position:absolute;bottom:52px;right:14px;color:var(--ny);font-size:12px;font-weight:900;font-family:'Poppins',monospace;z-index:15;text-shadow:2px 2px 0 #000;pointer-events:none}
</style>
</head>
<body>
<div class="c" id="C">
<video id="V" playsinline controlslist="nodownload nofullscreen noremoteplayback" disablepictureinpicture oncontextmenu="return false" <?php if($autoplay) echo 'autoplay'; ?>></video>
<div class="sh" id="SH"></div>
<canvas class="cv" id="CV"></canvas>
<div class="fl" id="FL"></div>
<div class="wl" id="WL"><div class="wm"></div><div class="wm"></div><div class="wm"></div><div class="wm"></div><div class="wm"></div><div class="wm"></div><div class="wm"></div></div>
<div class="warn" id="W"><h2 id="WT"></h2><p id="WM"></p><div class="wc" id="WC"></div></div>
<div class="bn" id="BN"><?php echo s($video->name); ?></div>
<div class="pct" id="PCT">0% watched</div>
<div class="ctrl">
<button class="cb" id="PB">&#9654;</button>
<div class="pw" id="PW"><div class="pb" id="PF"></div></div>
<span class="tm" id="TM">0:00/0:00</span>
<div class="vw"><button class="cb" id="MB">&#128266;</button><input type="range" class="vs" id="VS" min="0" max="1" step="0.05" value="1"></div>
<button class="cb" id="FB">&#x26F6;</button>
</div>
</div>

<script src="<?php echo $CFG->wwwroot; ?>/local/securevideo/js/hls.min.js"></script>
<script>
(function(){
'use strict';
var v=document.getElementById('V'),c=document.getElementById('C');
var pb=document.getElementById('PB'),pw=document.getElementById('PW'),pf=document.getElementById('PF'),tm=document.getElementById('TM');
var mb=document.getElementById('MB'),vs=document.getElementById('VS'),fb=document.getElementById('FB'),sh=document.getElementById('SH');
var wms=document.querySelectorAll('.wm'),warn=document.getElementById('W'),wt=document.getElementById('WT'),wm_el=document.getElementById('WM'),wc=document.getElementById('WC');
var pctEl=document.getElementById('PCT');
var wmT=<?php echo json_encode($watermarktext); ?>;
var code=<?php echo json_encode($accesscode); ?>;
var ihex=<?php echo json_encode($invisiblehex); ?>;
var vidId=<?php echo json_encode($videoid); ?>;
var plId=<?php echo json_encode($playlistid); ?>;
var m3u8Url=<?php echo json_encode($m3u8url); ?>;
var progressUrl=<?php echo json_encode($progressurl); ?>;
var reportUrl=<?php echo json_encode($reporturl); ?>;
var viol=0,lastReport=0,resumePos=<?php echo (int)$resumepos; ?>;

// === HLS SETUP ===
if (Hls.isSupported()) {
    var hls = new Hls({ enableWorker:true, xhrSetup:function(xhr){ xhr.withCredentials=true; } });
    hls.loadSource(m3u8Url);
    hls.attachMedia(v);
} else if (v.canPlayType('application/vnd.apple.mpegurl')) {
    v.src = m3u8Url;
}

// === REAL WATCH TIME TRACKING ===
var watchedSet = new Set();
var lastPlayTime = -1;
var isPlaying = false;
var totalDuration = 0;

function getRealPercent() {
    if (totalDuration <= 0) return 0;
    return Math.min(100, Math.round((watchedSet.size / Math.floor(totalDuration)) * 100));
}

c.addEventListener('touchstart',function(){c.classList.add('touch');clearTimeout(c._tt);c._tt=setTimeout(function(){c.classList.remove('touch')},4000);},{passive:true});
setTimeout(function(){document.getElementById('BN').style.opacity='0';},3000);

v.addEventListener('loadedmetadata',function(){
    totalDuration = v.duration;
    if(resumePos>5 && resumePos<v.duration-5) v.currentTime=resumePos;
});
v.addEventListener('play',function(){isPlaying=true;lastPlayTime=Math.floor(v.currentTime);pb.innerHTML='&#9646;&#9646;';});
v.addEventListener('pause',function(){isPlaying=false;pb.innerHTML='&#9654;';});
v.addEventListener('seeking',function(){lastPlayTime=-1;});
v.addEventListener('seeked',function(){lastPlayTime=Math.floor(v.currentTime);});

function report(t,d){viol++;try{navigator.sendBeacon(reportUrl,JSON.stringify({videoid:vidId,event:t,details:d||'',code:code,violations:viol,ts:Date.now()}));}catch(e){}}
function showW(t,m){v.pause();wt.textContent=t;wm_el.textContent=m;wc.textContent='Session: '+code;warn.classList.add('on');}
function hideW(){warn.classList.remove('on');}

function trackWatchTime(){
    if(!isPlaying||!v.duration||v.paused) return;
    var sec=Math.floor(v.currentTime);
    if(lastPlayTime>=0 && Math.abs(sec-lastPlayTime)<=2){
        var from=Math.min(lastPlayTime,sec),to=Math.max(lastPlayTime,sec);
        for(var s=from;s<=to;s++){if(s>=0&&s<Math.floor(v.duration))watchedSet.add(s);}
    }
    lastPlayTime=sec;
}

function reportProgress(){
    if(!v.duration) return;
    trackWatchTime();
    var realPct=getRealPercent();
    pctEl.textContent=realPct+'% watched';
    var now=Date.now();
    if(now-lastReport<5000) return;
    lastReport=now;
    try{navigator.sendBeacon(progressUrl,JSON.stringify({videoId:vidId,playlistId:plId,watchedSeconds:watchedSet.size,totalSeconds:Math.floor(v.duration),currentTime:v.currentTime}));}catch(e){}
    if(window.parent!==window) window.parent.postMessage({type:'videoProgress',percent:realPct},window.location.origin);
}

v.addEventListener('ended',function(){
    trackWatchTime();
    var realPct=getRealPercent();
    try{navigator.sendBeacon(progressUrl,JSON.stringify({videoId:vidId,playlistId:plId,watchedSeconds:watchedSet.size,totalSeconds:Math.floor(v.duration),currentTime:v.duration}));}catch(e){}
    pctEl.textContent=realPct+'% watched';
    if(realPct>=90&&window.parent!==window) window.parent.postMessage({type:'videoComplete'},window.location.origin);
});

/* SCREEN CAPTURE */
if(navigator.mediaDevices){navigator.mediaDevices.getDisplayMedia=function(){report('screen_capture','');showW('Screen Capture Blocked','Detected and logged.');return Promise.reject(new DOMException('Not allowed','NotAllowedError'));};var oGUM=navigator.mediaDevices.getUserMedia;navigator.mediaDevices.getUserMedia=function(con){if(con&&con.video&&typeof con.video==='object'&&(con.video.mediaSource||con.video.displaySurface)){report('screen_capture','getUserMedia');showW('Screen Capture Blocked','Detected.');return Promise.reject(new DOMException('Not allowed','NotAllowedError'));}return oGUM.call(navigator.mediaDevices,con);};}
if(navigator.permissions&&navigator.permissions.query){navigator.permissions.query({name:'display-capture'}).then(function(s){if(s.state==='granted')report('display_perm','granted');s.addEventListener('change',function(){if(s.state==='granted'){report('display_perm','granted');showW('Screen Sharing Detected','Permission granted.');}});}).catch(function(){});}

/* VISIBILITY */
document.addEventListener('visibilitychange',function(){if(document.hidden){v.pause();report('tab_hidden','');}else hideW();});
var bc=0,bt=null;window.addEventListener('blur',function(){bc++;if(bc>=3)report('blur','x'+bc);clearTimeout(bt);bt=setTimeout(function(){bc=0;},30000);});

/* DEVTOOLS */
// NOTE: window size comparison (outerWidth vs innerWidth) is NOT used here because
// this player runs inside an iframe — the iframe's innerWidth is always much smaller
// than the browser's outerWidth, which would cause constant false positives.
// We use only the console object getter trick, which works correctly in iframes.
var dt=false;var dtE=new Image();Object.defineProperty(dtE,'id',{get:function(){if(!dt){dt=true;v.pause();report('devtools','');showW('DevTools Detected','Video paused. Logged.');}}});
setInterval(function(){console.log('%c',dtE);},1500);

/* WATERMARKS */
var pos=[{top:'8%',left:'5%',transform:'rotate(-3deg)'},{top:'20%',right:'8%',left:'auto',transform:'rotate(2deg)'},{top:'35%',left:'20%',transform:'rotate(-1deg)'},{top:'50%',left:'50%',transform:'rotate(1.5deg)'},{bottom:'30%',top:'auto',left:'60%',transform:'rotate(3deg)'},{top:'65%',left:'10%',transform:'rotate(-2deg)'},{top:'75%',right:'15%',left:'auto',transform:'rotate(-4deg)'},{top:'12%',left:'45%',transform:'rotate(2deg)'},{top:'42%',left:'72%',transform:'rotate(-1deg)'},{bottom:'12%',top:'auto',left:'3%',transform:'rotate(3deg)'},{top:'58%',left:'35%',transform:'rotate(-2.5deg)'},{top:'88%',left:'50%',transform:'rotate(1deg)'},{top:'5%',left:'75%',transform:'rotate(-1.5deg)'}];
function shuf(){var s=pos.slice().sort(function(){return Math.random()-0.5;});wms.forEach(function(w,i){var p=s[i%s.length];w.textContent=wmT;w.style.top=p.top||'';w.style.bottom=p.bottom||'';w.style.left=p.left||'';w.style.right=p.right||'';w.style.transform=p.transform||'';w.style.fontSize=(10+Math.random()*4)+'px';});}
shuf();setInterval(shuf,8000);

/* FORENSIC */
var fl=document.getElementById('FL'),ft=code+'-'+ihex.substring(0,8);
for(var r=0;r<6;r++)for(var cl=0;cl<6;cl++){var m=document.createElement('div');m.className='fm';m.textContent=ft;m.style.top=(10+r*13.3)+'%';m.style.left=(5+cl*15)+'%';m.style.transform='rotate('+((r+cl)%7-3)+'deg)';fl.appendChild(m);}

/* CANVAS */
function dCW(){var cv=document.getElementById('CV'),cx=cv.getContext('2d');cv.width=cv.offsetWidth||800;cv.height=cv.offsetHeight||450;cx.clearRect(0,0,cv.width,cv.height);var cd=code+ihex;for(var y=20;y<cv.height;y+=40)for(var x=20;x<cv.width;x+=40){var ci=((y/40|0)*(cv.width/40|0)+(x/40|0))%cd.length;var cc=cd.charCodeAt(ci);cx.fillStyle='rgba(128,128,128,0.08)';cx.fillRect(x+(cc%7)-3,y+((cc>>3)%7)-3,2,2);}}
dCW();window.addEventListener('resize',dCW);

/* PRINTSCREEN */
document.addEventListener('keyup',function(e){if(e.key==='PrintScreen'||e.keyCode===44){report('printscreen','');wms.forEach(function(w){w.style.color='rgba(255,255,255,0.6)';w.style.fontSize='18px';});setTimeout(function(){wms.forEach(function(w){w.style.color='';w.style.fontSize='';});},2000);}});
window.addEventListener('beforeprint',function(){report('print','');document.body.style.display='none';setTimeout(function(){document.body.style.display='';},500);});

/* EXTENSIONS */
function chkE(){var s=['[data-loom-extension]','#loom-companion-mv3','#screencastify-extension','[data-screencastify]','#nimbus-screenshot','#awesome-screenshot','[data-vidyard]'];for(var i=0;i<s.length;i++)if(document.querySelector(s[i])){report('extension',s[i]);showW('Recording Extension','Disable to continue.');return;}}
chkE();setInterval(chkE,10000);

/* WEBRTC */
if(typeof RTCPeerConnection!=='undefined'){var O=RTCPeerConnection;window.RTCPeerConnection=function(){var pc=new O(arguments[0],arguments[1]);var oat=pc.addTrack.bind(pc);pc.addTrack=function(t){if(t&&t.kind==='video'&&t.label&&/screen|monitor|display/i.test(t.label)){report('webrtc','');showW('Screen Sharing','Detected.');}return oat.apply(pc,arguments);};return pc;};window.RTCPeerConnection.prototype=O.prototype;}

/* ANTI */
document.addEventListener('contextmenu',function(e){e.preventDefault();});
document.addEventListener('keydown',function(e){if((e.ctrlKey&&/^[supaUSPA]$/.test(e.key))||(e.ctrlKey&&e.shiftKey&&/^[ijckIJCK]$/.test(e.key))||e.key==='F12'){e.preventDefault();report('shortcut',e.key);return false;}});
document.addEventListener('dragstart',function(e){e.preventDefault();});
document.addEventListener('selectstart',function(e){e.preventDefault();});
if(v.disablePictureInPicture!==undefined)v.disablePictureInPicture=true;

/* TAMPER */
new MutationObserver(function(muts){for(var i=0;i<muts.length;i++)for(var j=0;j<muts[i].removedNodes.length;j++){var n=muts[i].removedNodes[j];if(n.classList&&(n.classList.contains('wl')||n.classList.contains('fl')||n.classList.contains('cv'))){report('tamper','');v.pause();v.src='';document.body.innerHTML='<div style="display:flex;justify-content:center;align-items:center;height:100vh;background:#000;color:#FF1F1F;font-family:sans-serif;text-align:center"><div><h2>Tampering Detected</h2><p style="color:#666;margin-top:10px">Session: '+code+'</p></div></div>';return;}}}).observe(c,{childList:true,subtree:true});

/* HEARTBEAT */
setInterval(function(){if(!v.paused)try{navigator.sendBeacon(reportUrl,JSON.stringify({videoid:vidId,event:'heartbeat',code:code,time:Math.floor(v.currentTime),violations:viol,ts:Date.now()}));}catch(e){}},60000);

/* CONTROLS */
sh.addEventListener('click',function(){hideW();if(v.paused)v.play();else v.pause();});
sh.addEventListener('dblclick',function(){if(!document.fullscreenElement)c.requestFullscreen().catch(function(){});else document.exitFullscreen();});
pb.addEventListener('click',function(){hideW();if(v.paused)v.play();else v.pause();});
v.addEventListener('timeupdate',function(){if(v.duration){pf.style.width=(v.currentTime/v.duration*100)+'%';tm.textContent=fmt(v.currentTime)+'/'+fmt(v.duration);reportProgress();}});
pw.addEventListener('click',function(e){var r=pw.getBoundingClientRect();v.currentTime=((e.clientX-r.left)/r.width)*v.duration;});
mb.addEventListener('click',function(){v.muted=!v.muted;mb.innerHTML=v.muted?'&#128263;':'&#128266;';vs.value=v.muted?0:v.volume;});
vs.addEventListener('input',function(){v.volume=this.value;v.muted=(this.value==0);mb.innerHTML=(this.value==0)?'&#128263;':'&#128266;';});
fb.addEventListener('click',function(){if(!document.fullscreenElement)c.requestFullscreen().catch(function(){});else document.exitFullscreen();});
function fmt(s){var m=Math.floor(s/60),sec=Math.floor(s%60);return m+':'+(sec<10?'0':'')+sec;}
})();
</script>
</body>
</html>
