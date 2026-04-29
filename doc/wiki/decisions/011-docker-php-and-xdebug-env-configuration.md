# Docker PHP And Xdebug Env Configuration

## Decision

PHP runtime settings, PostgreSQL connection settings, and Xdebug settings are controlled through `.env`.

The PHP image renders `docker/php/php.ini` and `docker/php/xdebug.ini` from template variables during build. Docker Compose passes the same Xdebug values into the `app` and `worker` containers for PhpStorm-friendly debugging.

## Consequences

Developers can adjust memory limits, upload sizes, timezone, PostgreSQL credentials, and Xdebug behavior without editing Docker image files. Xdebug defaults to `trigger` mode so normal requests stay lighter unless debugging is requested.
