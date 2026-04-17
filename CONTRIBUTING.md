# Contributing

Thank you for considering contributing to Moodle Secure Video Player.

## Development Setup

### Requirements
- Moodle 4.0+ development instance
- PHP 8.0+
- FFmpeg
- Node.js (for running the test server)

### Install for Development

```bash
# Clone into your Moodle installation
git clone https://github.com/Gabrcodes/moodle-securevideo.git
cp -r moodle-securevideo/local/securevideo  /path/to/moodle/local/
cp -r moodle-securevideo/mod/securevideo    /path/to/moodle/mod/

# Run Moodle upgrade
php /path/to/moodle/admin/cli/upgrade.php --non-interactive
```

## Coding Standards

This project follows [Moodle Coding Standards](https://moodledev.io/general/development/policies/codingstyle).

Key points:
- 4-space indentation (no tabs)
- `defined('MOODLE_INTERNAL') || die();` in all library/config files
- GPL-3.0 license header in every PHP file (see any existing file for the template)
- Moodle API functions only — no raw SQL except where unavoidable, use `$DB->get_records_sql()` with placeholders
- All user-facing strings must be in lang files, not hardcoded

## Security Requirements

- **Never commit credentials** — Zoom API keys, tokens, or secrets must never appear in code
- **Never commit media files** — `.mp4`, `.ts`, `.key` files are in `.gitignore` for a reason
- **Never commit server-specific paths** — use `$CFG->dataroot`, `$CFG->dirroot`, etc.
- All new endpoints must validate session (`require_login()`) and capability
- All POST actions must verify `confirm_sesskey()` or use `X-Requested-With` AJAX detection
- Input must be sanitized with Moodle's `required_param()` / `optional_param()` / `PARAM_*` types

## Pull Request Process

1. Fork the repository and create a feature branch: `git checkout -b feature/my-feature`
2. Make your changes following the coding standards above
3. Test against Moodle 4.0+
4. Verify no sensitive data is included: `grep -r "password\|secret\|token" . --include="*.php"`
5. Open a pull request with a clear description of what changed and why

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add support for WebVTT subtitles
fix: correct token expiry calculation
security: harden MIME validation on upload
docs: update Zoom integration guide
```

## Reporting Bugs

Please use the GitHub issue tracker. For security vulnerabilities, see [SECURITY.md](SECURITY.md).
