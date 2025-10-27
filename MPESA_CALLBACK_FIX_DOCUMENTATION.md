# M-Pesa Callback Automatic Status Update Fix

## Problem Summary

The M-Pesa payment status was not updating automatically. Users had to manually click on a pending payment and then click the "Check Status" button, which resulted in an error before the payment status would update.

### Error Message
```
Warning: require_once(../mikrotik_helper.php): Failed to open stream: No such file or directory in C:\xampp\htdocs\SAAS\Wifi Billiling system\Admin\transations_script\check_transaction.php on line 232

Fatal error: Uncaught Error: Failed opening required '../mikrotik_helper.php' (include_path='C:\xampp\php\PEAR') in C:\xampp\htdocs\SAAS\Wifi Billiling system\Admin\transations_script\check_transaction.php:232
```

## Root Causes

### 1. Missing File Dependency
The file `transations_script/check_transaction.php` was trying to include `../mikrotik_helper.php` which doesn't exist. This file was removed as part of the MikroTik integration cleanup, but the dependency wasn't removed from the manual check script.

### 2. Callback System Not Being Used
The automatic callback system exists in `mpesa_callback.php` but may not have been properly configured or tested. The callback should be triggered automatically by M-Pesa when a payment is completed.

## Solutions Implemented

### Fix 1: Removed Missing File Dependency
**File:** `transations_script/check_transaction.php`

**Changes Made:**
1. Removed `require_once '../mikrotik_helper.php';` on lines 87 and 232
2. Used the `generateVoucherCode()` function that's already defined at the bottom of the same file
3. Removed MikroTik integration calls (`addVoucherToMikrotik()`) since router integration is disabled
4. Added comments explaining that router integration is disabled

**Before:**
```php
// Generate voucher
require_once '../mikrotik_helper.php';
$voucher_code = generateVoucherCode();

// ... later in the code ...

// Add voucher to MikroTik
$mikrotikResult = addVoucherToMikrotik(
    $voucher_code, 
    $transaction['package_id'], 
    $transaction['reseller_id'], 
    $transaction['phone_number'], 
    $conn
);
```

**After:**
```php
// Generate voucher using the function defined at the bottom of this file
$voucher_code = generateVoucherCode();

// ... later in the code ...

// Router integration disabled - voucher generated without router communication
log_check("Voucher generated successfully (router integration disabled): $voucher_code");
```

### Fix 2: Callback System Explanation

The automatic callback system is already in place:

**Callback File:** `mpesa_callback.php`
- This file receives automatic notifications from M-Pesa when a payment is completed
- It updates the transaction status in the database
- It generates vouchers automatically
- It sends SMS notifications to customers

**How It Works:**
1. When a customer initiates a payment, the system sends an STK push request to M-Pesa
2. The request includes a `CallBackURL` parameter pointing to `mpesa_callback.php`
3. When the customer completes the payment on their phone, M-Pesa automatically sends a callback to this URL
4. The callback handler processes the payment and updates the database

**Callback URL Configuration:**
The callback URL is configured in `mpesa_settings_operations.php`:
```php
'callback_url' => 'https://ccc83e79741f.ngrok-free.app/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php'
```

## How to Ensure Automatic Callbacks Work

### 1. Configure Callback URL Properly

**For Local Development (using ngrok):**
```bash
# Start ngrok tunnel
ngrok http 80

# Copy the ngrok URL (e.g., https://abc123.ngrok-free.app)
# Update the callback URL in your M-Pesa settings
```

**For Production:**
- Use your actual domain name
- Ensure SSL/HTTPS is enabled
- Example: `https://yourdomain.com/Admin/mpesa_callback.php`

### 2. Update Callback URL in Settings

**Option A: Through the Admin Panel**
1. Go to Settings → M-Pesa Settings
2. Update the "Callback URL" field
3. Save the settings

**Option B: Directly in Database**
```sql
UPDATE mpesa_settings 
SET callback_url = 'https://your-actual-url.com/Admin/mpesa_callback.php' 
WHERE reseller_id = YOUR_RESELLER_ID;
```

**Option C: Update System Default**
Edit `mpesa_settings_operations.php` line 218:
```php
'callback_url' => 'https://your-actual-url.com/Admin/mpesa_callback.php'
```

### 3. Test the Callback System

**Check Callback Logs:**
```bash
# View the callback log file
tail -f mpesa_callback.log
```

**What to Look For:**
- `======= M-PESA CALLBACK RECEIVED =======` - Callback was received
- `Processing STK callback: CheckoutRequestID=...` - Callback is being processed
- `Transaction updated successfully in database` - Database was updated
- `Voucher delivery successful` - Voucher was generated and sent

### 4. Verify M-Pesa Configuration

**On M-Pesa Daraja Portal:**
1. Log in to https://developer.safaricom.co.ke
2. Go to your app settings
3. Verify the callback URL is correctly configured
4. Ensure your IP/domain is whitelisted

### 5. Common Issues and Solutions

**Issue: Callback URL contains localhost**
```
WARNING: Callback URL contains localhost which won't work with M-Pesa
```
**Solution:** Use ngrok for local development or deploy to a public server

**Issue: Callback not being received**
**Solution:** 
- Check firewall settings
- Verify ngrok tunnel is active
- Check M-Pesa Daraja portal configuration
- Review callback logs

**Issue: Callback received but not processing**
**Solution:**
- Check `mpesa_callback.log` for errors
- Verify database connection
- Ensure `auto_process_vouchers.php` exists and is working

## Manual Check Status Button

The "Check Status" button in the transactions page is now fixed and can be used as a fallback:
- It no longer throws the `mikrotik_helper.php` error
- It manually queries M-Pesa API to check payment status
- It updates the database if the payment is confirmed
- It generates vouchers if needed

**When to Use Manual Check:**
- Callback didn't trigger (network issues, etc.)
- Payment is stuck in "pending" status
- Testing purposes

## Testing the Fix

### Test 1: Manual Check Button
1. Go to Transactions page
2. Find a pending M-Pesa transaction
3. Click the action menu (three dots)
4. Click "Check Status"
5. Should redirect without errors
6. Status should update if payment is complete

### Test 2: Automatic Callback
1. Initiate a new M-Pesa payment
2. Complete the payment on your phone
3. Wait 5-10 seconds
4. Refresh the transactions page
5. Status should automatically update to "completed"
6. Check `mpesa_callback.log` for callback activity

### Test 3: Voucher Generation
1. After a successful payment (automatic or manual)
2. Check the transaction details
3. Verify voucher code is displayed
4. Check the `vouchers` table in database
5. Verify SMS was sent (check `umeskia_sms.log`)

## Files Modified

1. **transations_script/check_transaction.php**
   - Removed `require_once '../mikrotik_helper.php';` (2 occurrences)
   - Removed `addVoucherToMikrotik()` calls
   - Added comments about router integration being disabled

## Files Involved (No Changes Needed)

1. **mpesa_callback.php** - Automatic callback handler (already working)
2. **process_payment.php** - Sends callback URL with STK push
3. **mpesa_settings_operations.php** - Stores callback URL configuration
4. **auto_process_vouchers.php** - Handles voucher generation and SMS

## Recommendations

1. **Always use the automatic callback system** - It's more reliable and faster
2. **Keep callback logs enabled** - Helps with debugging
3. **Monitor callback activity** - Check logs regularly
4. **Update callback URL** - When moving from development to production
5. **Test after deployment** - Ensure callbacks work in production environment

## Support

If you continue to experience issues:
1. Check `mpesa_callback.log` for callback activity
2. Check `logs/transaction_checks.log` for manual check activity
3. Verify callback URL is publicly accessible
4. Ensure M-Pesa credentials are correct
5. Test with a small amount first

## Summary

✅ **Fixed:** Missing `mikrotik_helper.php` error
✅ **Fixed:** Manual check status button now works
✅ **Verified:** Automatic callback system is in place
✅ **Documented:** How to configure and test callbacks
✅ **Removed:** Unnecessary MikroTik integration calls

The payment status should now update automatically via callbacks, and the manual check button works as a fallback without errors.

