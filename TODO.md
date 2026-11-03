# Known issues and follow-up work

API behavior changes require a compatibility decision before implementation.

- [ ] Measure opportunities for additional query caching, especially address-history totals and block-size aggregation.
- [ ] Define and implement a JSON contract for `/api/v1/address/:addr/transactions`, which currently returns homepage HTML with HTTP 200.
- [ ] Review unknown API-path handling. Unmatched paths, including retired tag URLs, return homepage HTML. Paid tagging is absent.
- [ ] Fix the transaction fragment-link handler, which checks `txhash.trim` instead of calling `txhash.trim()`. Cover supported Coinomi/LBRY fragment formats.
- [ ] Review unconfirmed address-history timestamps: a datetime returned by `IFNULL(transaction_time, created_at)` can be interpreted as a Unix timestamp and cause HTTP 500.
- [ ] Review tiny monetary `DOUBLE` values and scientific notation. Decimal operations can reject these values; any repair needs an explicit decimal-contract decision.
- [ ] Review empty-database blocks-list failures and the empty block link shown for `MEMPOOL` transactions.
