# Keep Email On Subscription

## Decision

Keep `subscriptions.email` as the email identity for this assignment. Do not add users, accounts, authentication, or a separate subscribers table.

## Rationale

The service only needs to know which email subscribed to which listing. A duplicated email across multiple subscription rows is acceptable because each row represents one relationship between an email and a listing.

The current `unique(listing_id, email)` constraint is enough to prevent duplicate subscriptions for the same listing and email.

A future version could introduce a `subscribers` table with unique email and `subscriptions.subscriber_id`, but that is a future extension. Adding it now would turn the assignment into user-management work that is outside the current scope.
