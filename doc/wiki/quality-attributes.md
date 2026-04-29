# Quality Attributes

## Simplicity

The system must stay small and understandable.

## Testability

Domain logic, price extraction, URL normalization, and price change detection must be covered by tests.

## Replaceability

OLX price extraction is behind an interface so another source can be added later.

## Reliability

A single failed listing check must not stop processing of other listings.

## Operational Logging

Logs are emitted through Monolog and are visible with:

```bash
docker compose logs -f app
docker compose logs -f worker
```

The application logs OLX request status/latency, extractor source used, subscription failures, notification failures, worker listing checks, status transitions, and not-found/fetch-error counters. Logs must not contain `MAILER_DSN`, SMTP passwords/API keys, confirmation tokens, full OLX HTML responses, or email bodies.

## Maintainability

Architecture decisions and module responsibilities are documented in `/doc`.

## Developer Experience

Docker Compose includes PostgreSQL and Mailpit, static OpenAPI docs, and Composer scripts for tests, PHPStan, CodeSniffer, and the combined QA gate.

When a freshly cloned repository is mounted into the PHP container, Git may report `fatal: detected dubious ownership in repository at '/app'`. The fix must be executed inside the container:

```bash
docker compose exec app git config --global --add safe.directory /app
```

Running `git config --global --add safe.directory /app` on the host changes the host Git config only and does not fix Git inside the container.
