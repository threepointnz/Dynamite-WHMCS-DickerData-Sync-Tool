# Testing Guide: Cron Email System

## Quick Test Commands

### 1. Test Cron Endpoint (with browser or curl)

```bash
# Browser
http://localhost/msdd/?cron=1

# Or with curl
curl "http://localhost/msdd/?cron=1"
```

**Expected JSON Response:**

```json
{
  "sent": true,
  "issues_found": 12,
  "success": true,
  "message": "Email sent successfully"
}
```

---

### 2. Test with No Issues (After Adding Exceptions)

Add exceptions for all current issues, then:

```bash
curl "http://localhost/msdd/?cron=1"
```

**Expected Response:**

```json
{
  "sent": false,
  "issues_found": 0,
  "message": "No issues found - email not sent"
}
```

---

### 3. Force Send Even Without Issues

Update `.env`:

```env
MAIL_ONLY_SEND_IF_ISSUES=false
```

Then test again - should send "all clear" email.

---

## Email Content Validation

### Check Email Contains:

- ✅ Summary section with counts
- ✅ Problematic clients section (if any)
- ✅ Quantity mismatches split into over/undercharging
- ✅ Unmatched subscriptions section (if any)
- ✅ Actions required section
- ✅ Dashboard URL link
- ✅ Timestamp

### Optional Sections (based on ENV):

- Exceptions applied section (if `MAIL_INCLUDE_EXCEPTIONS=true`)

---

## ENV Variable Testing

### Test MAIL_ONLY_SEND_IF_ISSUES

```env
# Test with true (default) - should NOT send if no issues
MAIL_ONLY_SEND_IF_ISSUES=true

# Test with false - should send "all clear" email
MAIL_ONLY_SEND_IF_ISSUES=false
```

### Test MAIL_INCLUDE_EXCEPTIONS

```env
# Test with false (default) - no exceptions section
MAIL_INCLUDE_EXCEPTIONS=false

# Test with true - includes exceptions section
MAIL_INCLUDE_EXCEPTIONS=true
```

### Test DASHBOARD_URL

```env
# Should appear in email footer
DASHBOARD_URL=https://your-domain.com/msdd/
```

---

## Troubleshooting

### Email Not Sending

1. Check `MAIL_TO` is set in `.env`
2. Check `MAIL_SUBJECT` is set
3. Verify PHP `mail()` function is working:
   ```php
   <?php
   $result = mail('test@example.com', 'Test', 'Test message');
   var_dump($result);
   ```

### Wrong Data in Email

1. Check dashboard shows correct filtered data
2. Verify exceptions are being applied correctly
3. Check `$report` variable structure in debug mode

### JSON Response Issues

1. Check for PHP errors in error log
2. Add `error_reporting(E_ALL)` at top of index.php
3. Check Content-Type header is `application/json`

---

## Example Cron Setup

### Linux/Mac Crontab

```bash
# Run daily at 8 AM
0 8 * * * curl -s "https://your-domain.com/msdd/?cron=1" > /dev/null 2>&1
```

### Windows Task Scheduler

```powershell
# Action: Start a program
Program: powershell.exe
Arguments: -Command "Invoke-WebRequest -Uri 'https://your-domain.com/msdd/?cron=1' -UseBasicParsing"
Trigger: Daily at 8:00 AM
```

---

## Monitoring

### Check Last Run

The JSON response includes:

- `issues_found` - Number of issues detected
- `sent` - Whether email was sent
- `success` - Whether email sent successfully
- `message` - Status message

### Log Response

```bash
# Save response to log
curl "https://your-domain.com/msdd/?cron=1" >> /var/log/o365-sync.log 2>&1
```

---

## Sample Test Scenarios

### Scenario 1: Multiple Issue Types

1. Have 2 clients with missing tenant IDs
2. Have 3 quantity mismatches (2 over, 1 under)
3. Have 1 unmatched subscription
4. Run cron - should see all in email

### Scenario 2: Apply Exceptions

1. Add exception for 1 client with missing tenant ID
2. Add exception for 1 quantity mismatch
3. Run cron - should NOT see those 2 items

### Scenario 3: All Clear

1. Add exceptions for ALL issues
2. Run cron with `MAIL_ONLY_SEND_IF_ISSUES=true`
3. Should NOT send email
4. Change to `MAIL_ONLY_SEND_IF_ISSUES=false`
5. Should send "all clear" email

---

## Expected Email Counts

### Header Summary

- Total Issues = Problematic + Discrepancies + Unmatched
- Should match dashboard counts exactly

### Exceptions Note

- If exceptions applied, shows count
- If `MAIL_INCLUDE_EXCEPTIONS=true`, shows details

### Actions Section

- Should only show actions for issue types that exist
- e.g., no "map unmatched" if unmatched count is 0
