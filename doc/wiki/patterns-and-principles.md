# Patterns and Principles

## KISS

No SaaS features, dashboard, accounts, billing, queues, or microservices. OpenAPI is static to avoid unnecessary bundle complexity.

## SOLID

Interfaces are used at external boundaries:
- price fetching
- persistence
- notification
- clock/token generation

SRP is enforced at class boundaries. Controllers should not own business rules, handlers should not render copy, repositories should not make domain decisions, and mail factories should not contain localized templates.

DIP keeps application/domain code depending on replaceable interfaces where infrastructure can vary, including fetchers, notifiers, repositories, clocks, and sleepers.

## GRASP

Controllers are thin entry points.
Application handlers coordinate use cases.
Domain entities own their state.
Infrastructure adapts external tools only.

Information Expert examples:
- `Listing` owns listing availability state and counters.
- `Subscription` owns confirmation state.

Low Coupling / High Cohesion examples:
- OLX extraction, mail rendering, persistence, and use-case orchestration stay in separate classes.

## SOLID/GRASP self-review

After code changes, check SRP, DIP, GRASP Controller, Information Expert, Low Coupling / High Cohesion, and KISS. If a violation is found, fix it or document why it is acceptable.

## Lightweight DDD

Explicit domain concepts:
- Listing
- Subscription
- Price
- PriceHistory

No heavy aggregate/event-sourcing implementation.

## Lightweight CQRS

Command objects and handlers are used for write workflows:
- CreateSubscriptionCommand
- ConfirmSubscriptionCommand
- CheckPricesCommand

No separate read model.

## GoF

Used naturally:
- Strategy: price extractors
- Composite/Chain: fallback price fetcher
- Factory: Symfony email creation
- Repository: persistence abstraction

Repository implementations currently flush on `save()`. This favors clarity for the assignment; batching can be introduced later if worker volume requires it.
