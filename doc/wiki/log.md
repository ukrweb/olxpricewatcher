# Wiki Change Log

## 2026-04-29 production polish pass

Added `PRERENDERED_STATE` extractor as primary OLX price source, optimized subscription throttling before OLX fetch, added explicit mail transport error handling, and introduced safer operational logging.

## 2026-04-29 English documentation parity

Expanded `README-en.md` and `doc/olxpricewatcher-en.md` so they match the Ukrainian documents by content and structure while keeping English text and the English single-file documentation link.

## 2026-04-29 documentation image and artifact cleanup

Removed unused documentation images, deleted the tracked PHPUnit result cache artifact, ignored future PHPUnit result cache files, and repeated artifact scans for old localization remnants, empty directories, and `.gitignore`-only directories.

## 2026-04-29 Symfony email translation refactor

Replaced custom email localization with Symfony Translation, moved email copy to `translations/emails.ua.yaml` and `translations/emails.en.yaml`, injected the site name from `PROJECT_NAME`, removed the old custom renderer and locale enum, and kept `EmailFactory` focused on Symfony `Email` creation.

## 2026-04-29 throttled subscription side-effect cleanup

Stopped creating new pending subscriptions when a recipient email address is inside `EMAIL_RATE_LIMIT_SECONDS`, kept existing pending subscriptions reusable without token refresh, and aligned the `429 confirmation_throttled` response text and documentation.

## 2026-04-29 global recipient confirmation throttling

Changed confirmation email throttling from per subscription to per recipient email address, documented `429 confirmation_throttled`, and clarified that `EMAIL_RATE_LIMIT_SECONDS` protects user-triggered confirmation emails only, not all outgoing worker notifications.

## 2026-04-29 locale and email throttling cleanup

Replaced the email locale environment variable with `LOCALE`, added `EMAIL_RATE_LIMIT_SECONDS` for simple per-recipient confirmation email throttling, normalized Ukrainian and English documentation files under `doc/`, removed the old Ukrainian documentation mirror directory, added `README-en.md`, and fixed documentation links.

## 2026-04-29 SOLID/GRASP mail rendering refactor

Split Symfony email creation from localized email text generation, added locale support for Ukrainian and English plain-text messages, documented the SOLID/GRASP self-review rule, normalized single-file documentation paths, and recorded the production anti-spam note.

## 2026-04-29 wiki image cleanup

Removed presentation screenshots and image references from the LLM wiki. The wiki now keeps compact text-only architecture memory, while human-facing screenshots remain in the standalone documentation.

## Initial seed

Created initial wiki structure for Codex-assisted implementation.

## 2026-04-26 implementation

Generated the Symfony project structure, Doctrine entities and migration, subscription API, confirmation flow, OLX price extraction, price-check console command, mailer integration, Docker setup, QA tooling, README, and unit tests. Updated module and architecture docs to match the implemented boundaries.

Aligned Doctrine configuration with the snake_case migration schema and kept listing selection distinct so each due listing is checked once per worker cycle.

Added repository hygiene for local environment files, Composer dependencies, runtime cache, and coverage artifacts.

Kept Docker dependency installation tolerant of the initial state where no `composer.lock` has been generated yet.

## 2026-04-26 refactor

Removed the seed directory, moved the PHP Dockerfile to `docker/php/Dockerfile`, added Mailpit, added static OpenAPI docs, added full confirmation URLs via `APP_BASE_URL`, improved OLX HTTP error mapping, expanded unit/application/functional tests, and updated README plus wiki decisions.

Cleaned Composer recipe side effects so the root Compose file remains authoritative. CodeSniffer and PHPStan pass; PHPUnit and full QA require the PHP 8.3 Docker runtime because the local WSL PHP is 8.1.

## 2026-04-26 documentation hygiene

Restored CLAUDE.md, reinforced doc-first workflow, normalized internal wiki links, kept PhpStorm files ignored but not deleted, and adjusted Composer lock handling for reproducible application installs.

## 2026-04-26 Docker PHP configuration

Added template-driven PHP and Xdebug ini files, passed PostgreSQL/PHP/Xdebug settings through `.env` and Docker Compose, normalized wiki links to cleaner relative paths, and documented the Docker configuration decision.

## 2026-04-26 Swagger UI and worker cleanup

Fixed /api/doc routing by moving ApiDocController into src/UI/Http, added a simple home page, documented Swagger UI/OpenAPI URLs, clarified worker container behavior, and removed unused default Symfony directories if empty.

## 2026-04-26 Docker database and API error cleanup

Fixed Docker PostgreSQL connection to use the `database` service host, improved API error handling to return JSON instead of Symfony HTML 500 pages, and removed unused default Symfony directories.

## 2026-04-26 Subscription initial tracking validation

Changed subscription creation to fetch the initial OLX price before creating listing or subscription records, added JSON error mapping for not found, no price, and fetch failures, covered duplicate subscription behavior, and documented the new invariant that untrackable URLs create no records.

## 2026-04-27 API documentation and relational model hygiene

Documented the `404` subscription response in OpenAPI, added indexes for confirmation tokens, subscription lookup, and listing worker scheduling, clarified that digit-leading emails are valid, and recorded the decision to keep email directly on subscriptions without adding users or accounts.

## 2026-04-27 Listing availability counters and notifications

Added listing availability counters, unavailable notification tracking, and thresholded unavailable-listing emails for active subscribers. Clarified that listing status and subscription status are separate, and that worker checks are driven only by active subscriptions.

## 2026-04-27 listing availability and email content

Added listing availability counters, unavailable-listing notification flow, clarified worker behavior for active subscriptions only, improved confirmation and price-change email text, and documented subscription-scoped email confirmation.

## 2026-04-27 test coverage improvement

Added focused unit, application, and functional tests for URL normalization, listing/subscription state transitions, worker error branches, controller error responses, and OLX price extractor edge cases. Coverage target raised toward 90%+.

## 2026-04-27 documentation coverage evidence

Added test coverage evidence to the research and architecture notes, showing PHPUnit passing with more than 80% line coverage.

## 2026-04-27 README coverage command

Documented the Docker command for running the Composer coverage script from the app container.

## 2026-04-28 confirmation UX, randomized worker intervals, PHPDoc, and Ukrainian docs

Improved confirmation endpoint behavior for already used tokens, added JSON 500 handling, replaced fixed worker interval with configurable random interval range, added randomized delay between OLX requests, improved PHPDoc comments, and translated email text to Ukrainian.

## 2026-04-28 persistent Docker database volume

Made the PostgreSQL Docker volume name explicit so database data persists across `docker compose down`, container recreation, and Docker restarts unless the volume is intentionally removed.

## 2026-04-29 wiki directory consolidation

Recorded that the maintained wiki now lives under `doc/wiki`, removed the old Ukrainian mirror workflow, and fixed PHP_CodeSniffer spacing issues.

## 2026-04-29 project name and environment cleanup

Added `PROJECT_NAME` as the single configurable service name for Docker object names, removed duplicated `DATABASE_URL` and `COMPOSE_PROJECT_NAME` examples from `.env.example`, and simplified shared Docker Compose app/worker configuration.

## 2026-04-29 small code refactoring pass

Centralized API status/error JSON responses, reused one static-file response helper for Swagger UI and OpenAPI YAML endpoints, and removed the obsolete pending-token repository lookup now that confirmation tokens are resolved regardless of subscription status.

## 2026-04-29 README and project documentation SMTP troubleshooting

Updated the Ukrainian README and standalone project documentation with reliable Docker restart commands after env changes, added Mailer DSN inspection commands for Mailpit/live SMTP debugging, and refreshed Mailpit evidence screenshots in `doc/images`.

## 2026-04-29 migration ordering and Mailtrap evidence

Renamed migrations to keep their execution order on separate days, made the migration index test independent from one exact filename, and documented live SMTP verification with Mailtrap screenshots.

## 2026-04-29 standalone documentation coverage evidence refresh

Updated the standalone Ukrainian project documentation with the latest coverage evidence: 95 tests, 312 assertions, and 93.76% line coverage.

## 2026-04-29 Docker Git safe directory note

Documented the `docker compose exec app git config --global --add safe.directory /app` fix for Git dubious ownership warnings when running quality commands inside the PHP container.

## 2026-05-15 HTTP 410 unavailable detection

Mapped OLX HTTP `410` responses to `not_found` alongside `404`, added focused regression coverage for counter and unavailable-notification behavior, and clarified in README plus wiki docs that fetch errors do not trigger unavailable notifications.
