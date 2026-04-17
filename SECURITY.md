# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 2.x     | Yes       |
| 1.x     | No        |

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

If you discover a security vulnerability, please report it privately:

1. Go to the **Security** tab of this repository
2. Click **"Report a vulnerability"**
3. Provide a clear description, steps to reproduce, and impact assessment

We will acknowledge within 48 hours and aim to release a fix within 7 days for critical issues.

## Credential Handling

This plugin handles sensitive credentials (Zoom OAuth). **Critical rules:**

- **Never** commit credentials to version control
- Zoom credentials (`zoom_account_id`, `zoom_client_id`, `zoom_client_secret`) must only be stored via the Moodle admin settings UI — they are stored in Moodle's database, never in files
- The HMAC token secret is auto-generated on first use and stored in Moodle's `mdl_config_plugins` table
- HLS encryption keys are stored in `$CFG->dataroot/securevideo/hls/` which must **not** be web-accessible

## Known Security Limitations

- **Screen recording cannot be fully blocked** at the software level. The 22 protection layers create strong deterrence and forensic traceability, but a determined attacker with hardware capture can bypass software-level restrictions.
- The invisible CSS/canvas watermarks may not survive aggressive H.264 compression. The **visible watermark** is the primary forensic identification tool.
- Sequential mode unlock is enforced client-side (via progress data from the server). A user with browser dev tools could theoretically manipulate local state; however, the server-side progress table is the authoritative record.

## Security Architecture Overview

```
User request → require_login() → HMAC token validation
                                         ↓
                              PHP proxy (serve.php)
                                         ↓
                       AES-128 encrypted .ts segments
                                         ↓
                            Browser (HLS.js decrypts)
                                         ↓
                   22-layer JS protection in player
                                         ↓
                      Watermark with user identity
```

All security events are logged as JSON to `$CFG->dataroot/securevideo/security_events.log`.
