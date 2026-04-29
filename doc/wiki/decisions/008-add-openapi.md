# Add Static OpenAPI

## Decision

The project exposes a lightweight static OpenAPI file at `public/openapi.yaml` instead of adding a heavier API documentation bundle.

## Consequences

API documentation stays readable and cheap to maintain while avoiding extra framework complexity.
