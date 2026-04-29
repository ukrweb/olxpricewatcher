# API

## GET /

Returns a simple HTML home page with project links for local development.

## POST /api/subscriptions

Checks validation, duplicate-active state, and per-recipient confirmation-email throttling before fetching OLX. It then fetches the submitted listing once, creates a pending subscription only when a current price can be extracted, and sends a confirmation email with a full URL based on `APP_BASE_URL`.

Request:

```json
{
  "url": "https://m.olx.ua/d/uk/obyavlenie/example-ID.html",
  "email": "subscriber@example.com"
}
```

Response:

```json
{
  "status": "pending_confirmation",
  "message": "Subscription created. Please confirm your email."
}
```

Subscription creation validates the request, normalizes the URL, checks throttling, fetches the current price, and only then stores the listing/subscription. Failed initial price extraction or a throttled new request does not create database records.

If the same recipient address has received a confirmation email before `EMAIL_RATE_LIMIT_SECONDS` has passed, no email is sent and no OLX fetch is made. Existing pending subscriptions keep their current token; when there is no existing subscription to reuse, no new pending subscription is created. The API returns `429` with `confirmation_throttled`.

Invalid JSON returns `400`. Invalid URL or email returns `422`. Listing not found returns `404`. Price not found returns `422`. Confirmation email throttling returns `429`. OLX fetch/network failures and mail transport failures return `502`. Unexpected storage/application failures return `500`.

Email validation uses PHP `FILTER_VALIDATE_EMAIL`. Addresses such as `1subscriber@example.com` are valid and must not be rejected by a custom regex.

API errors use JSON:

```json
{
  "status": "error",
  "message": "message"
}
```

## GET /api/subscriptions/confirm/{token}

Confirms the email and activates the subscription.

A valid pending token returns `confirmed`. If the token was already used and the subscription is active, the endpoint returns `already_confirmed`.

Unknown tokens return `404` JSON. Expired pending tokens return `410` JSON. Unexpected failures return `500` JSON with `Unexpected server error.`.

Confirmation is subscription-scoped. There is no user login or account system.

## GET /health

Returns service status.

```json
{
  "status": "ok"
}
```

## OpenAPI

Swagger UI is available at `/api/doc`.

OpenAPI YAML is available at `/openapi.yaml`.
