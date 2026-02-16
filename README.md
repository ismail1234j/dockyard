# Dockyard
Small PHP + SQLite dashboard to view, start, stop, and inspect Docker containers with per-user permissions.

## Features
- Container list with status and logs (auto-refresh)
- Start / stop controls
- Per-user permissions (view/start/stop)
- Admin can force password resets

## Dev quick start
- Prereqs: PHP 8+ with SQLite extension; Docker CLI available.
- Make script executable once: `chmod +x dev.sh`
- Run `./dev.sh` (from repo root). It:
  - Deletes and recreates `src/data/db.sqlite`
  - Runs schema/setup (creates default admin `admin/pass`)
  - Starts PHP built-in server at http://localhost:8000 serving `src/`
- Login at http://localhost:8000 with `admin/pass`, then change the password.

## Notes
- Stop the dev server with Ctrl+C; rerun `./dev.sh` to reset DB and restart.