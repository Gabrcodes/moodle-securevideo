# Moodle Secure Video Player

A professional video protection plugin for Moodle that delivers encrypted HLS streams with forensic watermarking, anti-piracy detection, and full access audit logging.

![Moodle](https://img.shields.io/badge/Moodle-4.0%2B-orange)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue)
![License](https://img.shields.io/badge/license-GPL--3.0-green)
![Security Layers](https://img.shields.io/badge/security%20layers-22-red)

---

## Features

### Video Protection
- **AES-128 encrypted HLS** — videos are segmented and encrypted at upload time
- **Session-authenticated proxy** — no direct file URLs; every request validates a signed HMAC token tied to the logged-in user
- **Forensic watermarking** — every session gets a unique 8-character code embedded in a visible watermark (name + code) plus an invisible CSS/canvas layer

### Anti-Piracy (22 Security Layers)
- Screen capture API interception (`getDisplayMedia` blocked)
- WebRTC screen share detection
- Recording extension detection (Loom, Screencastify, Nimbus, etc.)
- DevTools detection (3 independent methods)
- Tab visibility monitoring (pauses on switch-away)
- PrintScreen detection + clipboard overwrite
- MutationObserver anti-tamper (kills video if watermark removed)
- Keyboard shortcut blocking (Ctrl+S, Ctrl+U, F12, etc.)
- Right-click, drag, PiP, and text selection all disabled

### Moodle Integration
- **Native Activity Module** (`mod_securevideo`) — add it to any course like a Quiz or Assignment
- **Completion tracking** — auto-marks complete when student watches X% of each video (configurable)
- **Course playlists** — group videos into playlists with sequential unlock mode
- **Single iframe embed** — one embed code per playlist, paste once and it auto-updates
- **Progress tracking** — per-user per-video watch percentage, survives page reload
- **Anti-seek** — completion requires actual watch time, not just seeking to the end

### Management
- Drag-and-drop playlist reordering
- Chunked parallel upload (Dropzone.js)
- **Zoom import** — connect your Zoom account and import cloud recordings directly to the server
- URL import — paste any direct download URL
- Forensic investigation panel — look up leaked recording by watermark code
- Forensic analyzer — upload a screenshot/clip and contrast-boost to reveal invisible watermarks

---

## Requirements

| Requirement | Version |
|-------------|---------|
| Moodle | 4.0+ |
| PHP | 8.0+ |
| FFmpeg | Any recent version |
| PHP `exec()` | Must be enabled |

---

## Installation

### 1. Install FFmpeg

**Ubuntu / Debian:**
```bash
sudo apt update && sudo apt install ffmpeg -y
```

**Amazon Linux / RHEL:**
```bash
sudo amazon-linux-extras install epel -y
sudo yum install ffmpeg -y
```

### 2. Copy plugins to Moodle

```bash
# Copy both plugins
cp -r local/securevideo  /path/to/moodle/local/
cp -r mod/securevideo    /path/to/moodle/mod/

# Set permissions
sudo chown -R www-data:www-data /path/to/moodle/local/securevideo
sudo chown -R www-data:www-data /path/to/moodle/mod/securevideo
```

### 3. Run Moodle upgrade

```bash
php /path/to/moodle/admin/cli/upgrade.php --non-interactive
```

Or via the browser: **Site Administration → Notifications → Upgrade Moodle database now**

### 4. Create storage directories

```bash
sudo mkdir -p /path/to/moodledata/securevideo/{original,hls,thumbs}
sudo chown -R www-data:www-data /path/to/moodledata/securevideo
sudo chmod -R 770 /path/to/moodledata/securevideo
```

---

## Configuration

Go to: **Site Administration → Local plugins → Secure Video Player → Settings**

| Setting | Description | Default |
|---------|-------------|---------|
| FFmpeg path | Full path to the `ffmpeg` binary | `/usr/bin/ffmpeg` |
| Token secret | HMAC signing secret (auto-generated on first use) | Auto |
| Token expiry | How long video tokens remain valid (seconds) | `14400` (4h) |
| Watermark opacity | Opacity of the visible watermark (5–50%) | `15` |

### PHP Upload Limits

Edit your `php.ini`:
```ini
upload_max_filesize = 500M
post_max_size = 512M
max_execution_time = 600
```

---

## Usage

### For Teachers / Admins

1. Go to **Site Administration → Secure Video Player → Video Manager**
2. Upload videos (drag & drop, multiple files, parallel upload)
3. Create a playlist and add videos to it
4. In a course: **Turn editing on → Add activity → Secure Video**
5. Select a playlist, set completion percentage, save

### For Students

Students click the activity in the course — it opens a native Moodle page with the video grid. Clicking a video plays it full-screen with all protections active.

---

## Zoom Integration

1. Go to [Zoom App Marketplace](https://marketplace.zoom.us/develop/create)
2. Create a **Server-to-Server OAuth** app
3. Add scope: `cloud_recording:read:list_user_recordings:admin`
4. Activate the app
5. In Moodle: **Site Administration → Secure Video Player → Settings**, enter your Account ID, Client ID, and Client Secret
6. Open **Video Manager → Open Zoom Recordings** to browse and import

> ⚠️ Never commit Zoom credentials to version control. Store them only via the Moodle admin settings UI.

---

## Forensic Investigation

When a video is leaked, look at the watermark for the 8-character code (e.g. `A3F2B1C8`).

**Look up the leaker:**

```sql
SELECT u.firstname, u.lastname, u.email, a.ip, FROM_UNIXTIME(a.timecreated) AS watched_at
FROM mdl_local_securevideo_access a
JOIN mdl_user u ON u.id = a.userid
WHERE a.accesscode = 'A3F2B1C8';
```

Or use the built-in **Forensic Investigation** panel in the Video Manager.

---

## Security Architecture

```
Request → require_login() → HMAC token validation → capability check
                                      ↓
                         PHP proxy (serve.php)
                                      ↓
                    AES-128 encrypted .ts segments
                                      ↓
                         Browser (HLS.js)
                                      ↓
              22-layer protection in player (JS)
                                      ↓
                      Watermark with user identity
```

All security events (screen capture attempts, DevTools open, watermark tampering, etc.) are logged as JSON to `$CFG->dataroot/securevideo/security_events.log`.

---

## Deploy Icons (Optional)

To apply the Nancy Academy icon set to your Moodle theme:

```bash
./deploy_icons.sh /path/to/your/moodle
```

This deploys custom SVG icons for all standard Moodle activity modules matching the plugin's visual style. Tested with the **Moove** theme.

---

## License

This project is licensed under the **GNU General Public License v3.0** — see [LICENSE](LICENSE) for details.

This aligns with Moodle's own GPL-3.0 licensing. Any derivative works must also be released under GPL-3.0.

---

## Contributing

Pull requests welcome. Please:
- Follow Moodle coding standards
- Test against Moodle 4.0+
- Never commit credentials, media files, or server-specific paths
