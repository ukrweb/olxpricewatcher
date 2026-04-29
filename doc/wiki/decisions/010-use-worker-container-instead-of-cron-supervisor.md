# Use Worker Container Instead Of Cron Or Supervisor

## Decision

Run periodic price checks in a dedicated Docker `worker` container using `docker/worker/run.sh`.

## Rationale

Cron was not chosen because environment handling, logging, and debugging are less convenient in Docker.

Supervisor was not chosen because the project does not need multiple long-running processes in one container.

A separate worker container is simpler and Docker-native: the HTTP app and background worker each have one main process, and their logs can be inspected independently.
