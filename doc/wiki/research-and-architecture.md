# Research and Architecture Notes

This page collects investigation notes that support the current `olx-price-watcher` architecture.

## OLX Data Extraction Strategy

Final decision: use `window.__PRERENDERED_STATE__` from the server-rendered OLX listing HTML as the primary data source for listing price extraction.

During the investigation, multiple approaches for retrieving listing prices were evaluated.

## Evaluated approaches

### GraphQL API

Although OLX uses GraphQL internally, it was determined that:

- Public queries for a single listing are not exposed.
- Only indirect queries, such as `getOtherAdsOfUser`, are available.
- These queries require `sellerId` and return paginated data.
- There is no guarantee that a specific listing will be included in the response.

Conclusion: GraphQL API is not suitable for reliable price extraction.

### Next.js data endpoint

The following endpoint shape was identified:

```text
/_next/data/{buildId}/...ID.json
```

However:

- `buildId` changes on every deployment.
- Dynamic discovery would be required.
- The endpoint is not stable enough for backend usage.

Conclusion: this endpoint is not reliable for production usage.

## Final approach: `PRERENDERED_STATE`

The HTML response contains a serialized state object:

```js
window.__PRERENDERED_STATE__
```

This object includes:

- full listing data;
- price information;
- structured JSON.

Example structure:

```json
{
  "ad": {
    "ad": {
      "price": {
        "regularPrice": {
          "value": 300,
          "currencyCode": "UAH"
        }
      }
    }
  }
}
```

Advantages:

- Does not require authentication.
- Uses a stable server-rendered structure.
- Is present in the HTML response.
- Does not depend on internal API endpoint stability.

Conclusion: `window.__PRERENDERED_STATE__` is the primary and most reliable data source.

## Fallback strategy

1. `window.__PRERENDERED_STATE__` as the primary source.
2. JSON-LD as a fallback source.
3. HTML parsing as the last fallback.

## Architecture overview

The project keeps HTTP subscription handling, email confirmation, worker-driven price checks, price extraction, and notifications as separate application paths while sharing the same `listings` and `subscriptions` model.

Key runtime paths:

- `POST /api/subscriptions` validates the request, checks duplicate/throttle cases before OLX fetch, persists the listing/subscription only after a successful price extraction, and sends a confirmation email.
- `GET /api/subscriptions/confirm/{token}` activates a pending subscription or reports `already_confirmed` for an already used active token.
- `app:check-prices` loads due listings that have at least one active subscription, checks each listing once, stores price history, and notifies active subscribers only when needed.
