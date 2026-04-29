# Use SQL Storage

## Decision

SQL storage is chosen over file/Redis storage because subscriptions, listings, statuses, and price history are relational and need constraints.

## Consequences

The project remains small while still showing clean engineering decisions.
