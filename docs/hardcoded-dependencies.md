# Historical note: the pre-backend prototype

Before the PHP+MySQL backend existed, every dataset (`AVIC.users`, `AVIC.claims`,
`AVIC.policies`, …) was a hardcoded in-memory array in `assets/js/mock-data.js`, and
every write (submitting a claim, approving an estimate, marking a notification read)
just mutated that array and evaporated on reload. Buttons that had no real action yet
were marked `data-stub` and showed a "prototype only, nothing was saved" toast.

That stage is over. `assets/js/mock-data.js` now only holds lookups, labels and role
metadata — real data comes from `config/api/bootstrap.php` (see `assets/js/api.js`),
and every write goes to a `config/api/*.php` endpoint backed by MySQL. There are no
`data-stub` buttons left in the markup.

See `docs/porting-to-php.md` for how the endpoints are organised.
