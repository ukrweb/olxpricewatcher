# CLAUDE.md

## Project

Project name: `olx-price-watcher`

This is a small PHP/Symfony service that lets users subscribe to OLX listing price changes by email.

The service must:

1. Provide an HTTP endpoint for subscription creation.
2. Accept an OLX listing URL and an email address.
3. Confirm the email address before activating the subscription.
4. Track listing price changes.
5. Send email notifications when the price changes.
6. Avoid duplicate checks when several users subscribe to the same listing.
7. Run in Docker.
8. Include meaningful tests and quality tooling.
9. Keep architecture documentation in `doc/wiki`, `doc/olxpricewatcher-en.md`, and `doc/olxpricewatcher.md`.
10. Stay small and KISS.

Documentation maintenance:

- `doc/wiki/` is the canonical English LLM wiki.
- `doc/olxpricewatcher-en.md` is the English human-readable single-file documentation.
- `doc/olxpricewatcher.md` is the Ukrainian human-readable single-file documentation.
- Keep documentation concise and aligned with the implemented behavior.
- When adding or moving wiki pages, update `doc/wiki/index.md`.

## CRITICAL RULE: DOC-FIRST WORKFLOW

Before writing or modifying ANY code/config/test/documentation:

1. Read `CLAUDE.md` completely.
2. Read `./doc/wiki/index.md`, `./doc/olxpricewatcher-en.md`, and `./doc/olxpricewatcher.md`.
3. Read all relevant linked docs before changing files.

After ANY change:

1. Update relevant `doc/wiki`, `doc/olxpricewatcher-en.md`, and `doc/olxpricewatcher.md` pages.
2. Update `/doc/wiki/index.md` if links/pages changed.
3. Append a short entry to `/doc/wiki/log.md`.
4. Update `README.md` and `README-en.md` if setup/configuration changed.

Before finishing:

- verify `doc/wiki`, `doc/olxpricewatcher-en.md`, and `doc/olxpricewatcher.md` reflect actual code behavior;
- verify no outdated documentation remains;
- verify internal doc links work.

## CRITICAL RULE: SOLID/GRASP SELF-REVIEW

After every code change, perform a short self-review before finishing:

1. Check SRP: no class should mix orchestration, formatting, persistence, transport, and domain decisions.
2. Check DIP: application/domain code must depend on interfaces where infrastructure is replaceable.
3. Check GRASP:
   - Controllers are thin entry points.
   - Application handlers coordinate use cases.
   - Domain objects own domain state.
   - Infrastructure adapts external tools only.
4. Check KISS: do not introduce abstractions unless they remove real coupling or duplication.
5. If a violation is found, either fix it or document why it is acceptable.

## Scope

This is a test assignment, not a SaaS platform.

Do not implement:

- user accounts
- authentication
- dashboard/admin panel
- billing
- complex roles
- queues
- microservices
- event sourcing
- frontend application
- unnecessary abstractions

## Tech Stack

- PHP 8.3+
- Symfony minimal skeleton
- PostgreSQL
- Doctrine ORM and Migrations
- Symfony Mailer
- Symfony Console
- PHPUnit
- PHPStan
- PHP_CodeSniffer with PSR-12
- Docker Compose
- Mailpit for local Docker email capture

## Main Invariant

One OLX listing must be checked only once per worker cycle, regardless of how many subscribers it has.

Correct model:

```text
1 Listing -> many Subscriptions
1 price check -> N notifications if price changed
```

Incorrect model:

```text
1 Subscription -> 1 independent price check
```

## Current HTTP API

### POST /api/subscriptions

Creates or reuses a subscription and sends a confirmation email when confirmation is needed.

Request:

```json
{
  "url": "https://m.olx.ua/d/uk/obyavlenie/example-ID.html",
  "email": "user@example.com"
}
```

Responses:

- `201` for accepted subscription requests.
- `400` for invalid JSON.
- `422` for invalid email or unsupported URL.

### GET /api/subscriptions/confirm/{token}

Activates a pending subscription if the token exists and is not expired.

If the token was already used and the subscription is active, returns `already_confirmed`.
Unknown tokens return `404`; expired pending tokens return `410`.

### GET /health

Returns:

```json
{
  "status": "ok"
}
```

### GET /openapi.yaml

Static OpenAPI documentation.

## Data Model

Required tables:

- `listings`
- `subscriptions`
- `price_history`

The `subscriptions` table stores:

- `confirmation_token`
- `confirmation_token_expires_at`
- `confirmed_at`
- `status`

Do not add a separate confirmation token table unless the project scope changes.

## Price Fetching

Use `PriceFetcherInterface`.

The current OLX implementation is:

```text
OlxCompositePriceFetcher
  -> OlxPrerenderedStatePriceExtractor
  -> OlxJsonLdPriceExtractor
  -> OlxHtmlPriceExtractor
```

Rules:

- `window.__PRERENDERED_STATE__` is the primary extraction strategy.
- JSON-LD is the first fallback.
- Visible HTML parsing is the last fallback.
- Use one configured User-Agent.
- Do not implement proxy support.
- Do not attempt to bypass CAPTCHA, authentication, bot protection, or rate limits.

HTTP and parsing outcomes:

- HTTP 404 maps to listing status `not_found`.
- No parsed price maps to `no_price`.
- Network/parser failures map to `parse_error`.
- A failed listing must not stop the whole worker run.

## Email

Use Symfony Mailer through `MAILER_DSN`.

Local Docker uses Mailpit:

```env
MAILER_DSN=smtp://mailpit:1025
```

Confirmation links must be absolute and use:

```text
{APP_BASE_URL}/api/subscriptions/confirm/{token}
```

Do not commit real SMTP credentials.

Mail transport failures must be mapped to safe JSON responses and must not expose SMTP provider details, credentials, DSN, or email body content.

## Logging

Logs are emitted through Monolog and visible with:

```bash
docker compose logs -f app
docker compose logs -f worker
```

Do not log `MAILER_DSN`, SMTP passwords/API keys, confirmation tokens, full OLX HTML responses, or email bodies.

## Docker

Keep `docker-compose.yml` in the project root.

The PHP image is defined at:

```text
docker/php/Dockerfile
```

Services:

- `app`
- `worker`
- `database`
- `mailpit`

## Quality Commands

Run inside the PHP 8.3 Docker container:

```bash
composer cs-check
composer phpstan
composer test
composer coverage
composer qa
```

## Documentation

The `doc/wiki` wiki is the canonical English LLM working memory for future coding sessions.
The `doc/olxpricewatcher-en.md` file is the English human-readable single-file documentation.
The `doc/olxpricewatcher.md` file is the Ukrainian human-readable single-file documentation.

Keep wiki docs concise, text-first, and true. Do not duplicate the full human-facing documentation or document every private method.

## Final Checklist

- Performed SOLID/GRASP self-review.
- Updated doc/wiki, English docs, Ukrainian docs, and README files if behavior, setup, or architecture changed.
