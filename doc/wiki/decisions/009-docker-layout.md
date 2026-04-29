# Docker Layout

## Decision

The Compose file stays at the project root, while the PHP image definition lives at `docker/php/Dockerfile`.

## Consequences

Docker files are grouped by runtime without making the small project harder to run. Both `app` and `worker` build from the same PHP image. The `worker` service uses `docker/worker/run.sh` for its price-check loop and restarts unless stopped.
