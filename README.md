# Dockyard

A dead-simple, super-lightweight PHP + SQLite web UI for managing Docker containers. It does one thing well: let you view container logs and start/stop containers with per-user permissions and minimal ops overhead.

Image: `ghcr.io/10ij/dockyard:v1.0.1` (also available as `:latest`)  
Stack: PHP 8, SQLite3, HTMX, Pico CSS

## Table of contents
- [Quick start (Docker)](#quick-start-docker)
- [Docker Compose example](#docker-compose-example)
- [Features](#features)
- [Usage](#usage)
- [Security & deployment notes](#security--deployment-notes)
- [Development](#development)
- [Contributing](#contributing)
- [License](#license)
- [Changelog](#changelog)

---

## Quick start (Docker)

Run on the Docker host (requires access to the Docker socket):

```bash
docker run -d \
  --name dockyard \
  -p 8080:80 \
  -v /var/run/docker.sock:/var/run/docker.sock:ro \
  -v dockyard-data:/app/data \
  ghcr.io/10ij/dockyard:v1.0.1
```

Open: http://localhost:8080

Notes:
- The app inspects and controls containers via the Docker socket.
- Persist data by mounting `/app/data` (above uses a named volume `dockyard-data`).

## Docker Compose example

```yaml
version: "3.8"
services:
  dockyard:
    image: ghcr.io/10ij/dockyard:v1.0.1
    ports:
      - "8080:80"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - dockyard-data:/app/data
volumes:
  dockyard-data:
```

## Features
- Live container status (HTMX polling).
- Tail and refresh container logs.
- Start / stop containers from the UI.
- Per-user permissions and basic user management.
- Tiny single-repo app: PHP + SQLite, no external DB required.

## Usage
- Launch the container (see Quick start).
- Open the web UI.
- Default user combo is admin/pass
- Create users and assign container permissions via the Users pages.
- Click a container to view logs, status, and use Start / Stop controls.

## Development
- Very small codebase; HTMX endpoints handle partial updates (logs, status, actions).
- Data stored in SQLite under `/app/data` inside the container.
- Recommended for self-hosting and quick local setups; not a replacement for full-featured orchestration UIs.

## License
See LICENSE file

## Changelog
See CHANGELOG.md
