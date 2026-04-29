# Use JSON-LD Price Extraction Fallback

## Decision

OLX listing pages expose structured data in `application/ld+json`. Reading `offers.price` and `offers.priceCurrency` is less fragile than CSS selector parsing, so JSON-LD is kept as the second extractor after `window.__PRERENDERED_STATE__`.

## Consequences

The project remains small while still showing clean engineering decisions. Current extraction order is `window.__PRERENDERED_STATE__`, JSON-LD, then HTML fallback.
