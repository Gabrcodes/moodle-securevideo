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
 * Forensic Watermark Analyzer
 * Upload a screenshot or video clip — reveals invisible forensic watermarks
 * by applying progressively stronger contrast/curve filters via FFmpeg.
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
require_capability('local/securevideo:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/securevideo/forensic.php'));
$PAGE->set_title('Forensic Watermark Analyzer');
$PAGE->set_heading('Forensic Watermark Analyzer');
$PAGE->set_pagelayout('admin');

$ffmpeg  = get_config('local_securevideo', 'ffmpegpath') ?: '/usr/bin/ffmpeg';
$ffprobe = dirname($ffmpeg) . '/ffprobe';

// Temp directory for this session — cleaned up on each visit.
$tmpdir  = $CFG->dataroot . '/securevideo/forensic_tmp';
if (!is_dir($tmpdir)) {
    mkdir($tmpdir, 0770, true);
}

// Clean up files older than 30 minutes.
foreach (glob($tmpdir . '/*') as $f) {
    if (is_file($f) && filemtime($f) < time() - 1800) {
        unlink($f);
    }
}

$results   = [];
$error     = '';
$inputtype = ''; // 'image' or 'video'

// =========================================================
// HANDLE UPLOAD
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    if (empty($_FILES['forensic_file']) || $_FILES['forensic_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';
    } else {
        $f    = $_FILES['forensic_file'];
        $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);

        $imageexts = ['png','jpg','jpeg','bmp','tiff','webp'];
        $videoexts = ['mp4','mov','webm','avi','mkv','ts'];

        if ($f['size'] > 200 * 1024 * 1024) {
            $error = 'File too large. Max 200 MB.';
        } elseif (in_array($ext, $imageexts) || strpos($mime, 'image/') === 0) {
            $inputtype = 'image';
            $infile    = $tmpdir . '/input_' . uniqid() . '.' . $ext;
            move_uploaded_file($f['tmp_name'], $infile);

            // Apply 5 levels using colorlevels filter.
            // colorlevels=rimin/gimin/bimin controls the black point —
            // rimax/gimax/bimax controls the white point.
            // Shrinking the input range (e.g. 0–0.05) stretches faint content to full brightness.
            $levels = [
                // label, rimin, rimax, gimin, gimax, bimin, bimax
                ['Original (no processing)',    0,    1,    0,    1,    0,    1   ],
                ['Mild — stretch 0–30%',        0,    0.30, 0,    0.30, 0,    0.30],
                ['Medium — stretch 0–15%',      0,    0.15, 0,    0.15, 0,    0.15],
                ['Strong — stretch 0–8%',       0,    0.08, 0,    0.08, 0,    0.08],
                ['Forensic — stretch 0–3%',     0,    0.03, 0,    0.03, 0,    0.03],
            ];

            foreach ($levels as $l) {
                [$label, $rimin, $rimax, $gimin, $gimax, $bimin, $bimax] = $l;
                $filter = sprintf(
                    'colorlevels=rimin=%s:rimax=%s:gimin=%s:gimax=%s:bimin=%s:bimax=%s',
                    $rimin, $rimax, $gimin, $gimax, $bimin, $bimax
                );
                $outfile = $tmpdir . '/result_' . uniqid() . '.png';
                $cmd = sprintf(
                    '%s -y -i %s -vf %s -update 1 %s 2>&1',
                    escapeshellarg($ffmpeg),
                    escapeshellarg($infile),
                    escapeshellarg($filter),
                    escapeshellarg($outfile)
                );
                $cmdout = [];
                exec($cmd, $cmdout, $ret);
                if (file_exists($outfile) && filesize($outfile) > 0) {
                    $results[] = ['label' => $label, 'file' => basename($outfile)];
                } else {
                    // Store error for debugging.
                    $results[] = ['label' => $label . ' [FAILED: ' . implode(' | ', array_slice($cmdout, -3)) . ']', 'file' => null];
                }
            }
            unlink($infile);

        } elseif (in_array($ext, $videoexts) || strpos($mime, 'video/') === 0) {
            $inputtype = 'video';
            $infile    = $tmpdir . '/input_' . uniqid() . '.' . $ext;
            move_uploaded_file($f['tmp_name'], $infile);

            // Get video duration.
            $durationcmd = sprintf(
                '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
                escapeshellarg($ffprobe),
                escapeshellarg($infile)
            );
            $duration = (float)trim(shell_exec($durationcmd));
            if ($duration <= 0) $duration = 60;

            // Extract frames at 5 timestamps, each with a different contrast level.
            $timestamps = [0.15, 0.3, 0.5, 0.7, 0.85];
            $videomaxes = [1, 0.15, 0.08, 0.03, 0.03];

            foreach ($timestamps as $i => $pct) {
                $ts      = round($duration * $pct, 2);
                $mx      = $videomaxes[$i];
                $filter  = sprintf('colorlevels=rimin=0:rimax=%s:gimin=0:gimax=%s:bimin=0:bimax=%s', $mx, $mx, $mx);
                $outfile = $tmpdir . '/frame_' . uniqid() . '.png';
                $cmd = sprintf(
                    '%s -y -ss %s -i %s -frames:v 1 -vf %s %s 2>&1',
                    escapeshellarg($ffmpeg),
                    escapeshellarg((string)$ts),
                    escapeshellarg($infile),
                    escapeshellarg($filter),
                    escapeshellarg($outfile)
                );
                $cmdout = [];
                exec($cmd, $cmdout, $ret);
                if (file_exists($outfile) && filesize($outfile) > 0) {
                    $results[] = [
                        'label' => 'At ' . gmdate('i:s', (int)$ts) . ($mx < 1 ? ' — boost ' . round((1/$mx)) . 'x' : ' — original'),
                        'file'  => basename($outfile),
                    ];
                }
            }
            unlink($infile);

        } else {
            $error = 'Unsupported file type. Upload PNG, JPG, MP4, MOV, or similar.';
        }
    }
}

// Serve a processed image file directly.
$serveimg = optional_param('img', '', PARAM_FILE);
if ($serveimg !== '') {
    $filepath = $tmpdir . '/' . basename($serveimg);
    if (file_exists($filepath) && strpos(realpath($filepath), realpath($tmpdir)) === 0) {
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-store');
        readfile($filepath);
        exit;
    }
    http_response_code(404);
    die();
}

// =========================================================
// RENDER PAGE
// =========================================================
echo $OUTPUT->header();

$manageurl = (new moodle_url('/local/securevideo/manage.php'))->out(false);
echo '<p><a href="' . $manageurl . '" class="btn btn-sm btn-outline-secondary">&larr; Back to Manage</a></p>';

echo '<div class="card mb-4"><div class="card-body">';
echo '<h5 class="card-title">&#128247; Upload Screenshot or Video Clip</h5>';
echo '<p class="text-muted small">Upload a screenshot or short clip from a leaked recording. ';
echo 'The tool boosts contrast to reveal invisible forensic watermarks embedded in every video session.</p>';

echo '<form method="post" enctype="multipart/form-data" action="' . $PAGE->url->out(false) . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<div class="form-row align-items-end">';
echo '<div class="form-group col-md-8 mb-2">';
echo '<label class="font-weight-bold small text-uppercase">File (image or video clip, max 200 MB)</label>';
echo '<input type="file" name="forensic_file" class="form-control" accept="image/*,video/*" required>';
echo '</div>';
echo '<div class="form-group col-md-4 mb-2">';
echo '<button type="submit" class="btn btn-danger btn-block" id="analyzeBtn">';
echo '&#128270; Analyze for Watermarks</button>';
echo '</div>';
echo '</div></form>';

if ($error) {
    echo '<div class="alert alert-danger mt-3">' . s($error) . '</div>';
}
echo '</div></div>';

// =========================================================
// RESULTS
// =========================================================
if (!empty($results)) {
    echo '<h5 class="mb-3">Results — ' . ($inputtype === 'video' ? 'Frames extracted with contrast boost' : 'Contrast levels applied') . '</h5>';
    echo '<p class="text-muted small mb-3">';
    if ($inputtype === 'image') {
        echo 'Look at the stronger boost levels — faint text and dot patterns should become visible. ';
        echo 'The code you see is the access code. Cross-reference it in the investigation panel on the Manage page.';
    } else {
        echo 'Frames extracted at 5 points throughout the video with strong contrast boost. ';
        echo 'Look for faint text overlaid across the frame — that text contains the access code.';
    }
    echo '</p>';

    echo '<div class="row">';
    $selfurl = (new moodle_url('/local/securevideo/forensic.php'))->out(false);
    foreach ($results as $r) {
        echo '<div class="col-md-6 col-lg-4 mb-4">';
        echo '<div class="card h-100">';
        echo '<div class="card-header py-2"><strong>' . s($r['label']) . '</strong></div>';
        echo '<div class="card-body p-1">';
        if ($r['file']) {
            echo '<a href="' . $selfurl . '?img=' . urlencode($r['file']) . '" target="_blank">';
            echo '<img src="' . $selfurl . '?img=' . urlencode($r['file']) . '" '
                . 'class="img-fluid" style="width:100%;cursor:zoom-in" '
                . 'title="Click to open full size">';
            echo '</a>';
        } else {
            echo '<div class="alert alert-warning m-2 small">Processing failed for this level.</div>';
        }
        echo '</div>';
        echo '<div class="card-footer py-1 text-muted small">Click image to open full size in new tab</div>';
        echo '</div></div>';
    }
    echo '</div>';

    echo '<div class="alert alert-info mt-2 small">';
    echo '<strong>How to read the results:</strong> ';
    echo 'The <strong>visible watermark</strong> shows as semi-transparent text (e.g. "Ahmed Al-Rashid | A3F2B1C8"). ';
    echo 'The <strong>invisible forensic grid</strong> appears as a faint repeating pattern of the same code across the entire frame. ';
    echo 'The <strong>canvas pixel watermark</strong> appears as subtle dot clusters — zoom in on the full-size image to see them. ';
    echo 'Take the 8-character code and paste it into the <a href="' . $manageurl . '#investigation">Investigate</a> panel.';
    echo '</div>';
}

echo '<script>
document.querySelector("form").addEventListener("submit", function() {
    var btn = document.getElementById("analyzeBtn");
    btn.textContent = "Analyzing...";
    btn.disabled = true;
});
</script>';

echo $OUTPUT->footer();
