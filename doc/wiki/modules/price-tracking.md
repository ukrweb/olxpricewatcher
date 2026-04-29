# Price Tracking Module

The price tracking module coordinates periodic listing checks.

It must:
- check only listings with active subscriptions
- detect actual price changes
- update listing state
- write price history
- trigger notifications

The implementation loads distinct due listings with active subscriptions. A first observed price is stored and added to history, but price-change email is sent only after there is a previous known price.
