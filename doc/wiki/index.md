# Project Wiki Index

This wiki is the maintained knowledge layer for the `olx-price-watcher` project.

The canonical project wiki lives in `doc/wiki`. Keep this index updated when pages are added, moved, or removed.

Human-readable single-file documentation lives in `doc/olxpricewatcher-en.md` (English) and `doc/olxpricewatcher.md` (Ukrainian).

## Research and architecture notes

- [Research and architecture notes](research-and-architecture.md) documents the OLX data extraction decision and compact architecture notes.

## Core docs

- [Project goals](project-goals.md)
- [Architecture](architecture.md)
- [API](api.md)
- [Data model](data-model.md)
- [Invariants](invariants.md)
- [Quality attributes](quality-attributes.md)
- [Patterns and principles](patterns-and-principles.md)

## Flows

- [Subscription flow](flows/subscription-flow.md)
- [Email confirmation flow](flows/email-confirmation-flow.md)
- [Price tracking flow](flows/price-tracking-flow.md)
- [Notification flow](flows/notification-flow.md)

## Modules

- [Listing module](modules/listing.md)
- [Subscription module](modules/subscription.md)
- [Price fetching module](modules/price-fetching.md)
- [Price tracking module](modules/price-tracking.md)
- [Notification module](modules/notification.md)

## Decisions

- [001 Use Symfony](decisions/001-use-symfony.md)
- [002 Use SQL storage](decisions/002-use-sql-storage.md)
- [003 Use JSON-LD price extraction fallback](decisions/003-use-json-ld-price-extraction.md)
- [004 Use HTML fallback extraction](decisions/004-use-html-fallback-extraction.md)
- [005 Use lightweight CQRS](decisions/005-use-lightweight-cqrs.md)
- [006 Avoid overengineering](decisions/006-avoid-overengineering.md)
- [007 Add Mailpit](decisions/007-add-mailpit.md)
- [008 Add static OpenAPI](decisions/008-add-openapi.md)
- [009 Docker layout](decisions/009-docker-layout.md)
- [010 Use worker container instead of cron or supervisor](decisions/010-use-worker-container-instead-of-cron-supervisor.md)
- [011 Docker PHP and Xdebug env configuration](decisions/011-docker-php-and-xdebug-env-configuration.md)
- [012 Keep email on subscription](decisions/012-keep-email-on-subscription.md)

## Maintenance

- [Change log](log.md)
