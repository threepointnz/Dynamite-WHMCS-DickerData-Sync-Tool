# O365 Sync (msdd) — README

This README explains how the O365 Sync script (index.php and the classes/) works, the key logic used to match WHMCS products to Dicker Data subscriptions, how reports are generated, and where to look when the reports look wrong.

## Purpose

The script compares WHMCS customer records (products/licences and custom fields) against Dicker Data (subscriptions) to:

- Identify clients with missing WHMCS custom fields required for O365 tenant mapping.
- Match Dicker subscriptions to WHMCS products using an intelligent similarity algorithm.
- Produce a matrix that allocates subscription quantities to WHMCS product quantities.
- Generate discrepancy reports (undercharging / overcharging) and lists of unmatched Dicker subscriptions.

It also provides a web dashboard for interactive inspection and a minimal JSON/email output when run with `?cron=1` for scheduled reports.

---

## Important files

- `index.php` — main entry point and dashboard. Loads env, initializes classes, prepares data for display and cron output.
- `classes/DB.php` — small mysqli wrapper used by WHMCS class.
- `classes/WHMCS.php` — contains DB queries, matching logic and matrix/report generation.
- `classes/DickerAPI.php` — wrapper for Dicker Data API requests and bearer token management.
- `classes/Mail.php` — small helper to format and send emails (also plain text table builder).

---

## Required environment variables

The script expects a `.env` file in the same directory with values exported to `$_ENV` early in `index.php`.
Required keys used by the script:

- `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_DBNAME` — WHMCS database access
- `DICKER_url` — base URL for Dicker Data API (e.g. `https://api.dickerdata/.../`)
- `DICKER_access_token` — access token used to request a temporary bearer/access key
- `MAIL_TO`, `MAIL_SUBJECT` — used when running `?cron=1` to email the report
- `MAIL_ONLY_SEND_IF_ISSUES` — (Optional, default: `true`) Only send email if issues are found
- `MAIL_INCLUDE_EXCEPTIONS` — (Optional, default: `false`) Include exceptions section in email
- `DASHBOARD_URL` — (Optional) Full URL to dashboard for email links (e.g. `https://your-domain.com/msdd/`)
- (Optional) WHMCS API keys are present in code as env variables but WHMCS API usage is not required for the current flows.

If any of the DB/Dicker env keys are missing, the dashboard will show red in the Environment panel.

---

## High-level execution flow

1. `index.php` loads `.env`, initializes `DB`, `WHMCS`, `DickerAPI` and `Mail` classes.
2. It optionally obtains a Dicker access/bearer token via `DickerAPI::getBearerToken()` (cached in `$_SESSION`).
3. It calls `DickerAPI::getSubscriptionDetails()` to retrieve current subscriptions from Dicker Data.
4. It builds `organisedSubscriptions` — an associative map keyed by TenantId containing only `ACTIVE` subscriptions.
5. It calls `WHMCS::getO365Clients($organisedSubscriptions)` to retrieve WHMCS clients with relevant custom fields and active hosting rows, and to match them to Dicker subscriptions.
6. `WHMCS::matchSubscriptions()` builds per-client objects including `products`, `estimated_365_count`, and `subscriptions` (matched by TenantID).
7. `WHMCS::buildSubscriptionNameMatrix()` attempts to match each subscription to a WHMCS product using `findBestProductMatch()` and `calculateMatchScore()`; it generates a `matrix` which pairs subscriptions with product allocation and builds `unmatched_subscriptions` when no acceptable match is found.
8. `WHMCS::generateDiscrepancyReport()` inspects the matrix and produces:
   - `discrepancy_report`: rows where allocated product quantity differs from subscription Qty (over/undercharging)
   - `unmatched_subscriptions_report`: subscriptions with no reasonable product match
9. `index.php` uses the results to build the web UI and (when `?cron=1`) send a simplified email/JSON containing problems and discrepancies.

---

## Key logic details (matching & scoring)

The matching attempts to pair each Dicker subscription to the best WHMCS product for that client.

- findBestProductMatch(): iterates WHMCS products and calls calculateMatchScore(). Only candidates with score >= 40 are accepted.

- calculateMatchScore() steps:

  - Normalizes strings, uses PHP `similar_text()` as a base similarity (0-100).
  - Extracts plan numbers (e.g. "Plan 1") and applies a large bonus (+20) for an exact plan match or a heavy penalty (-30) when the plan numbers mismatch.
  - Billing period heuristics: strong bonus (+25) when both indicate monthly/yearly; strong penalty (-40) for month/year mismatches.
  - Product-type bonuses: +10 for matching keywords like `apps`, `exchange`, `business`.
  - StockDescription bonuses: +15 for specific product family matches (M365 vs 365, Exchange Online, Power BI).
  - Tier mismatch (Basic vs Standard) is an absolute disqualifier — returns 0 (no match) if subscription tier conflicts with product tier. Correct tier matches get +35.
  - Final score clamped between 0-100.

- Thresholds in code you should know:
  - Minimum acceptance score: 40 (below that a subscription is considered unmatched).
  - UI low-confidence warning threshold: 70% similarity (used to mark low confidence matches in the dashboard).

---

## Matrix allocation (product_qty vs sub_qty)

When multiple subscriptions map to the same WHMCS product, the code groups them and allocates the total product quantity proportionally across the group's subscriptions:

- For a product with quantity P and subscriptions with quantities S1..Sn (sum = S_total), each subscription is allocated product quantity round((Si / S_total) \* P).
- This proportional allocation can produce rounding differences which will show as small discrepancies in reports.

Interpretation:

- If the allocated `product_qty` < subscription `sub_qty` → report marks `undercharging` (customer likely billed for less than supplied).
- If `product_qty` > `sub_qty` → `overcharging`.

---

## Reports produced

- `discrepancy_report` (attached into returned results by `WHMCS::generateDiscrepancyReport`) — each entry contains:

  - client_id, companyname
  - subscription_reference, stock_description
  - product_name, product_qty (allocated), sub_qty (Dicker quantity)
  - difference (sub_qty - product_qty)
  - state: `undercharging` or `overcharging`

- `unmatched_subscriptions_report` — list of subscriptions that could not be matched (score < 40 or tier mismatch).

- `getProblematicClients()` (SQL-based) — finds WHMCS clients where custom field 7 (expiry) or custom field 16 (tenant) is missing while the other is present. Returned rows are normalized to 0/1 for presence and shown on the dashboard.

---

## Cron mode (`?cron=1`)

When you request `index.php?cron=1` the script will:

- Build two plaintext sections: a list of problematic custom-field clients and the discrepancy report.
- Use `Mail::arrayToTemplate()` to format them and call `Mail::send()` to send to `MAIL_TO` with `MAIL_SUBJECT`.
- Return a JSON response describing mail delivery success.

This mode is intended for scheduled runs. It avoids the heavy dashboard UI and only constructs minimal text for email delivery.

---

## Where things commonly go wrong / debugging checklist

1. Dicker API auth failures

   - Dicker uses a two-step pattern: an access token from env + a bearer/access key returned by `AccessKeyRequest`. The returned access key is cached in `$_SESSION['bearerToken']`.
   - If bearer token is missing or invalid you get API errors or empty responses. Check Dicker API responses (the class returns structured arrays on error).
   - cURL is configured with `CURLOPT_SSL_VERIFYPEER = false` in this code — this avoids cert issues but is insecure in production.

2. Empty or malformed Dicker responses

   - DickerAPI returns `['result'=>'error', 'message'=>...]` on cURL/HTTP/JSON errors. Check that `getSubscriptionDetails()` returns a valid array and contains an `Out` key (index.php expects `$subscriptionDetails['Out']`).

3. WHMCS custom fields assumptions

   - The SQL expects custom field id `7` = expiry and `16` = tenantId. If your field IDs differ, update the SQL in `getProblematicClients()` and `getO365Clients()`.
   - `getO365Clients()` requires non-empty values for both custom fields and active hosting rows.

4. Matching surprises

   - A subscription may be `unmatched` because the similarity score < 40 or because of tier mismatches. Increase logging or dump the `matrix` for the client (dashboard `monaco` output shows raw `matrix`).
   - Low similarity matches (<70) are flagged as warnings in the UI — review `stock_description` and `product_name` strings for normalization issues.

5. Quantity rounding effects

   - The proportional allocation can create small rounding differences. If these are problematic, change allocation strategy (e.g., allocate integer quantities greedily after rounding the largest fractions).

6. DB connectivity
   - DB errors from `mysqli` will currently `die()` on connect failure. Confirm env DB credentials and that WHMCS DB is accessible.

---

## Quick manual checks / queries

- Verify problematic clients SQL directly (adapt IDs if your custom field ids differ):

  SELECT c.id, c.companyname, cf7.value AS expiry, cf16.value AS tenantId
  FROM tblclients c
  LEFT JOIN tblcustomfieldsvalues cf7 ON c.id = cf7.relid AND cf7.fieldid = 7
  LEFT JOIN tblcustomfieldsvalues cf16 ON c.id = cf16.relid AND cf16.fieldid = 16
  WHERE c.status = 'Active' AND ((cf7.value = '' AND cf16.value <> '') OR (cf16.value = '' AND cf7.value <> ''))

- Inspect one client's raw matrix (from the dashboard): the `monaco` debug editor prints per-client object with `products`, `subscriptions`, `matrix` and `unmatched_subscriptions`.

---

## Troubleshooting tips / next improvements

- Add robust logging for Dicker API request/response payloads (to a file) so you can inspect raw API responses when matching fails.
- Persist Dicker bearer tokens safely instead of session caching if running as cron without a persistent session.
- Consider replacing the proportional allocation with a deterministic integer allocation that preserves total product quantity exactly.
- Add configuration for custom field IDs (7 and 16) to avoid hard-coded constants.
- Enable real SSL verification for curl and add retry/backoff for transient API failures.

---

## How to run / generate reports

- Browser interactive dashboard: open `index.php` in a browser (server must serve the directory).
- Cron/email output: fetch `index.php?cron=1` (scheduler or curl) — this will send an email and return JSON describing send success.

Example (cron):

- Schedule a HTTP GET to `/tools/msdd/index.php?cron=1` (or whatever URL serves the script) once per day.

---

If you need, I can add a dedicated debug mode that writes raw Dicker API responses and the full per-client `matrix` to disk to simplify investigation of problematic matches.
