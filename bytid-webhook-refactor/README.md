# Bytid webhook refactor — investor-wallet-automation

This is an implementation handoff for `konyerinonyen-boop/investor-s-application`, branch `investor-wallet-automation`.

## Important
The GitHub integration allowed reading but rejected writes with `403 Resource not accessible by integration`. These files are therefore **not committed to GitHub**. Review and apply them on a local checkout of the correct branch, then run the checks below.

## Planned endpoint
`POST /api/v1/webhooks/bytid`

Configure this exact URL in Bytid's webhook dashboard. Put the endpoint signing secret in `BYTID_WEBHOOK_SECRET` in `.env` and add the matching config key to `config/services.php`. Never use the API key as the webhook signing secret.

## Event handling rules
- Verify `Bytid-Signature` against the exact raw request body before parsing or trusting event data.
- Reject malformed signatures and timestamps outside the five-minute tolerance.
- Deduplicate `Bytid-Event-Id` values.
- Correlate events only with a stored provider session ID or a unique submitted document reference.
- Treat `review` / `face_unavailable` as pending review, not verified.
- A failed or expired liveness attempt must not automatically set the whole KYC application to rejected.
- A positive identity result is not final KYC approval.

## Before production
1. Confirm the exact webhook payloads and signature format in the Bytid dashboard/docs for the enabled event types.
2. Add the files and changes to the existing branch, not `main`.
3. Run `php artisan migrate`, `php artisan route:list --path=webhooks`, and the project's tests.
4. Use Bytid's test webhook and verify invalid signatures are rejected.
5. Confirm a duplicate event ID does not update the profile twice.
6. Confirm `face_unavailable` leaves identity pending and full KYC pending.
