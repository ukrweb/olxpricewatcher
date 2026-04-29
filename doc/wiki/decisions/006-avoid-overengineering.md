# Avoid Overengineering

## Decision

The assignment is a small watcher service. SaaS features, dashboards, accounts, queues, users tables, authentication, and microservices are intentionally excluded.

## Consequences

The project remains small while still showing clean engineering decisions. Subscription email duplication is acceptable because the subscription represents "this email subscribed to this listing".
