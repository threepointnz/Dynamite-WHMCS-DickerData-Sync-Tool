# Cron/Email System Refactoring Summary

## Date: 2025-11-28

## Overview

Refactored the cron mode email system to properly respect exceptions, include all issue types, and provide comprehensive reporting with summary statistics.

---

## Changes Made

### 1. **Data Flow Fix (index.php)**

**Before:**

- Used unfiltered `$problems` and wrong variable `$whmcs_data['discrepancy_report']`
- Only sent problematic clients and quantity mismatches
- Did NOT include unmatched subscriptions
- Did NOT respect exceptions (sent everything)

**After:**

- Uses filtered data: `$problems`, `$report['discrepancy_report']`, `$report['unmatched_subscriptions_report']`
- Respects ALL exceptions (client-level and quantity/subscription)
- Includes unmatched subscriptions in email
- Configurable: only sends if issues found (via ENV)

---

### 2. **Enhanced Email Template (Mail.php)**

**New Method:** `generateDailyReport()`

**Email Structure:**

```
=================================
O365 SYNC DASHBOARD - DAILY REPORT
=================================

📊 SUMMARY
- Total issues count
- Breakdown by type (problematic clients, quantity mismatches, unmatched subs)
- Exceptions applied count

🔴 CRITICAL ISSUES
1. PROBLEMATIC CLIENTS
   - Missing Tenant ID / Expiry Date

2. QUANTITY MISMATCHES
   - Overcharging (WHMCS > Dicker)
   - Undercharging (WHMCS < Dicker)

3. UNMATCHED SUBSCRIPTIONS
   - Dicker subs with no WHMCS mapping

ℹ️ EXCEPTIONS APPLIED (optional)
   - Shows filtered items if enabled

📎 ACTIONS REQUIRED
   - Clear next steps

View dashboard link
Report timestamp
```

---

### 3. **New ENV Variables**

#### `MAIL_ONLY_SEND_IF_ISSUES` (default: `true`)

Only send email when issues are found. Set to `false` to receive "all clear" emails.

#### `MAIL_INCLUDE_EXCEPTIONS` (default: `false`)

Include a section showing exceptions that were filtered out. Useful for audit trail.

#### `DASHBOARD_URL` (optional)

Full URL to dashboard included in email footer for easy access.

**Example .env:**

```env
MAIL_TO=support@company.com
MAIL_SUBJECT=O365 Sync - Daily Report
MAIL_ONLY_SEND_IF_ISSUES=true
MAIL_INCLUDE_EXCEPTIONS=false
DASHBOARD_URL=https://your-domain.com/msdd/
```

---

## Benefits

✅ **No False Positives** - Exceptions properly filter issues
✅ **Complete Coverage** - All 3 issue types included
✅ **Better Readability** - Clear sections with counts
✅ **Actionable** - Tells recipient what needs fixing
✅ **Configurable** - Can tune email behavior
✅ **Summary Statistics** - Quick overview at top
✅ **Separation of Over/Undercharging** - Easy to prioritize

---

## Example Email Output

```
======================================================================
O365 SYNC DASHBOARD - DAILY REPORT
======================================================================

📊 SUMMARY
----------------------------------------------------------------------
Total Issues Found: 12
  • Problematic Clients: 2 (missing tenant ID/expiry)
  • Quantity Mismatches: 7 (5 overcharging, 2 undercharging)
  • Unmatched Subscriptions: 3 (no WHMCS product mapped)

Exceptions Applied: 5 (filtered from this report)

🔴 CRITICAL ISSUES
======================================================================

1. PROBLEMATIC CLIENTS (2)
----------------------------------------------------------------------
  • ABC Company (ID: 123) - Missing Tenant ID
  • XYZ Corp (ID: 456) - Missing Expiry Date

2. QUANTITY MISMATCHES (7)
----------------------------------------------------------------------
⚠️ Overcharging (5):
  • Company A - Microsoft 365 Business Standard
    WHMCS: 10, Dicker: 8 (overcharging by 2)
  • Company B - Microsoft 365 Business Basic
    WHMCS: 24, Dicker: 22 (overcharging by 2)
  ...

⚠️ Undercharging (2):
  • Company C - Microsoft 365 E3
    WHMCS: 5, Dicker: 8 (undercharging by 3)
  ...

3. UNMATCHED SUBSCRIPTIONS (3)
----------------------------------------------------------------------
  • Company D - Microsoft 365 Business Premium (Qty: 5)
    MSC: P1Y:CFQ7TTC0LCHC:0001:1: - No mapping found in WHMCS
  ...

📎 ACTIONS REQUIRED
======================================================================
1. Review problematic clients - add missing tenant IDs/expiry dates or create exceptions
2. Check quantity mismatches for billing accuracy - adjust WHMCS or create exceptions
3. Map unmatched subscriptions in the dashboard mapping section

View full dashboard: https://your-domain.com/msdd/

----------------------------------------------------------------------
Report generated: 2025-11-28 12:00:00
```

---

## Testing

### Test with Issues:

```bash
curl "http://localhost/msdd/?cron=1"
```

### Test without Issues (with exceptions):

Create exceptions for all issues, then run cron. Should return:

```json
{
  "sent": false,
  "issues_found": 0,
  "message": "No issues found - email not sent"
}
```

---

## Migration Notes

### Backward Compatibility

✅ All new ENV variables have sensible defaults
✅ Old email format replaced completely (better readability)
✅ Exception system fully integrated

### What to Update

1. Add new ENV variables to production `.env`
2. Test cron endpoint to verify email format
3. Configure email sending preferences (only send if issues, include exceptions, etc.)

---

## Future Enhancements (Not Implemented)

- HTML email format (currently plain text for ticket system compatibility)
- Email grouping by client instead of by issue type
- Email digest mode (daily vs weekly summaries)
- Attachment support (CSV exports)
