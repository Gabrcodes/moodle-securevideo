# Changelog

All notable changes to this project will be documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [2.0.0] - 2026-04-17

### Added
- Full course playlist system — group videos into playlists, each with its own iframe embed code
- Sequential mode — lock next video until previous one is completed
- Real watch-time tracking — completion requires actually watching (seeks don't count)
- Per-user per-video progress persistence across sessions
- Native Moodle activity module (`mod_securevideo`) with completion tracking and gradebook integration
- Zoom cloud recording integration — browse and import recordings directly from Zoom account
- URL import — paste any direct download link, server downloads in background
- Chunked parallel upload with Dropzone.js (multi-file, parallel, progress per file)
- Drag-and-drop playlist reordering with SortableJS
- Import from Moodle file store — use videos already uploaded to any course
- Video rename after upload
- Duplicate name detection on upload and import
- Forensic investigation panel — look up leaked recordings by watermark code
- Forensic analyzer — upload screenshot/clip, server applies contrast boost to reveal watermarks
- Thumbnail generation at 15% into video (FFmpeg, survives server restarts)
- Status polling — live badge updates during conversion without page reload
- Mobile-responsive player and embed page
- Nancy Academy comic theme (Poppins, yellow/black/navy)
- Custom SVG icon set for all Moodle activity modules (moove theme)
- Security event log in JSON format (prevents log injection)
- Rate limiting on security event reporting endpoint
- Anti-tamper: MutationObserver kills video if watermark element is removed
- Screen capture API interception (getDisplayMedia blocked)
- WebRTC screen share track detection
- Recording extension detection (Loom, Screencastify, Nimbus, etc.)
- PrintScreen detection + clipboard overwrite

### Security
- All 22 anti-piracy layers implemented
- HMAC token signing with auto-generated secret (persisted on first use)
- AES-128 HLS encryption via FFmpeg
- MIME-type validation on uploads (magic bytes, not just extension)
- File size limit enforced server-side (500 MB)
- All delete operations use POST (prevents CSRF via browser prefetch)
- postMessage origin validation in player ↔ embed communication
- JSON-only security event logging (no string concatenation)

## [1.0.0] - 2026-03-26

### Added
- Initial private release
- AES-128 encrypted HLS streaming
- Session-authenticated PHP proxy (serve.php) with HMAC tokens
- Dynamic visible watermark (name + unique 8-char code, 7 positions)
- Invisible forensic watermark layers (CSS grid + canvas pixel pattern)
- HLS conversion via FFmpeg with stream-copy (fast) and re-encode fallback
- Upload handler with MIME validation
- Access audit log (user, IP, code, timestamp)
- Moodle local plugin structure (local_securevideo)
- Admin management page
- Basic embed iframe
