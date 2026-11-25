# Changelog

All notable changes to ModMenuUpdater are documented here.
This project follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## 1.0.2 — 2026-11-25
### Security
- The ModMenu archive downloads over https only, redirects included, and stops at 50 MB instead of being buffered whole.
- A directory entry's `sha256` pins the archive: a mismatch installs nothing.

---

## 1.0.1 — 2026-11-17

### Fixed
- The staged ModMenu copy is checked before any of its code is loaded.

## [1.0.0] — 2026-10-08

### Added

- One-shot upgrade of ModMenu older than 1.3.0 to the self-updating release from your directory sources, using the release's own staged, verified swap. The previous copy is kept for the new ModMenu's load guard; remove ModMenuUpdater afterwards from ModMenu → Installed.
