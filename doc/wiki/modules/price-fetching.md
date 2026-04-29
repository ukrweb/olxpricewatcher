# Price Fetching Module

The price fetching module extracts current price from OLX public listing page.

Extraction order:
1. `window.__PRERENDERED_STATE__` from the server-rendered OLX HTML.
2. JSON-LD structured data from `application/ld+json`.
3. Visible HTML selectors.

The module must return a structured result with:
- price
- currency
- optional title
- source

`POST /api/subscriptions` uses the fetcher for an initial price check only after validation, duplicate-active detection, and confirmation-email throttling pass. The worker uses the same fetcher for later periodic checks.

`OlxCompositePriceFetcher` uses `OlxHttpClient`, then tries `OlxPrerenderedStatePriceExtractor`, `OlxJsonLdPriceExtractor`, and `OlxHtmlPriceExtractor` in that order. `PRERENDERED_STATE` is primary because OLX already embeds structured listing data in the HTML response. The module does not implement proxy rotation or bot-protection bypasses.

HTTP `404` and `410` are mapped to listing status `not_found`. Missing parsed price is mapped to `no_price`. Other failures, including HTTP `5xx`, timeouts, and parser failures, are mapped to `parse_error` and do not stop the worker run.
