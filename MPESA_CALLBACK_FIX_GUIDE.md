# M-PESA CALLBACK FIX GUIDE

## 🔍 PROBLEM IDENTIFIED

**Issue:** M-Pesa callbacks are NOT being received automatically, forcing manual status checks.

**Root Cause:** ngrok tunnel is NOT running, preventing M-Pesa from reaching your local callback URL.

---

## ✅ SOLUTION

### **Step 1: Start ngrok Tunnel**

You MUST have ngrok running for M-Pesa to send callbacks to your local server.

**Option A: Using ngrok (Recommended for Testing)**

1. Open a new terminal/command prompt
2. Navigate to where ngrok is installed
3. Run:
   ```bash
   ngrok http 80
   ```
4. ngrok will display a forwarding URL like: `https://xxxx-xxxx-xxxx.ngrok-free.app`
5. Copy this URL

**Option B: Deploy to Production Server**

If you're ready for production, deploy your application to a server with a public domain (e.g., `https://yourdomain.com`)

---

### **Step 2: Update Callback URL in Database**

Once you have your public URL, update it in the M-Pesa settings:

```sql
-- Update callback URL for all resellers
UPDATE mpesa_settings 
SET callback_url = 'https://YOUR-NGROK-URL.ngrok-free.app/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php';

-- Or for a specific reseller
UPDATE mpesa_settings 
SET callback_url = 'https://YOUR-NGROK-URL.ngrok-free.app/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php'
WHERE reseller_id = 1;
```

**Important:** Replace `YOUR-NGROK-URL` with your actual ngrok URL.

---

### **Step 3: Update Hardcoded Callback URL**

The callback URL is also hardcoded in `mpesa_settings_operations.php`. Update it:

**File:** `mpesa_settings_operations.php`
**Lines:** 73, 218

Change:
```php
'callback_url' => 'https://5f9fa7362e95.ngrok-free.app/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php'
```

To:
```php
'callback_url' => 'https://YOUR-NEW-NGROK-URL.ngrok-free.app/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php'
```

---

### **Step 4: Verify Callback URL is Accessible**

Test that M-Pesa can reach your callback:

1. Open your browser
2. Visit: `https://YOUR-NGROK-URL.ngrok-free.app/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php`
3. You should see a blank page or error (this is normal - it means the URL is accessible)
4. Check `mpesa_callback.log` - you should see a log entry

---

### **Step 5: Test Payment Flow**

1. Make a test payment through `portal.php`
2. Complete the M-Pesa payment on your phone
3. Wait 5-10 seconds
4. Check `mpesa_callback.log` - you should see:
   ```
   === CALLBACK RECEIVED ===
   ✅ Transaction found in database
   ✅ STATUS UPDATED TO 'completed'
   ```
5. Check the transaction status in `transations.php` - it should be "completed" WITHOUT clicking "Check Status"

---

## 🎯 HOW THE WORKFLOW SHOULD WORK

### **Automatic Callback Flow (CORRECT):**

```
1. User clicks package → STK Push sent
2. M-Pesa returns CheckoutRequestID
3. Transaction saved with status='pending'
4. User completes payment on phone
5. M-Pesa sends callback to your server (via ngrok)
6. Callback updates status to 'completed' INSTANTLY
7. User can view voucher immediately
```

### **Manual Check Flow (CURRENT - WRONG):**

```
1. User clicks package → STK Push sent
2. M-Pesa returns CheckoutRequestID
3. Transaction saved with status='pending'
4. User completes payment on phone
5. ❌ NO CALLBACK RECEIVED (ngrok not running)
6. Status stays 'pending'
7. User must manually click "Check Status"
8. System queries M-Pesa API
9. Status updated to 'completed'
```

---

## 📋 CHECKLIST

- [ ] ngrok is running
- [ ] Callback URL updated in database
- [ ] Callback URL updated in `mpesa_settings_operations.php`
- [ ] Callback URL is publicly accessible
- [ ] Test payment completed successfully
- [ ] Callback received automatically (check logs)
- [ ] Status updated without manual intervention

---

## 🐛 TROUBLESHOOTING

### **Problem: Callback still not received**

**Check 1: Is ngrok running?**
```bash
# Windows
Get-Process | Where-Object {$_.ProcessName -like '*ngrok*'}

# Should show ngrok process
```

**Check 2: Is callback URL correct?**
```sql
SELECT callback_url FROM mpesa_settings;
```

**Check 3: Check ngrok web interface**
- Open: `http://localhost:4040`
- This shows all requests received by ngrok
- Look for POST requests to `/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php`

**Check 4: Check callback logs**
```bash
# View last 20 lines of callback log
tail -n 20 mpesa_callback.log
```

**Check 5: Test callback manually**
```bash
# Send a test POST request
curl -X POST https://YOUR-NGROK-URL.ngrok-free.app/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php
```

---

## 🚀 PRODUCTION DEPLOYMENT

For production, you should NOT use ngrok. Instead:

1. **Deploy to a server with a public domain**
2. **Use HTTPS** (M-Pesa requires HTTPS)
3. **Update callback URL** to your production domain:
   ```
   https://yourdomain.com/mpesa_callback.php
   ```
4. **Register callback URL with Safaricom** (for production environment)

---

## 📝 NOTES

- **ngrok free tier** URLs change every time you restart ngrok
- **You must update the callback URL** every time ngrok restarts
- **For production**, use a permanent domain
- **Callback logs** are in `mpesa_callback.log`
- **Debug logs** are in `mpesa_debug.log`

---

## ✅ VERIFICATION

After fixing, verify the workflow:

1. **Start ngrok**: `ngrok http 80`
2. **Update callback URL** in database and code
3. **Make test payment**
4. **Check logs immediately** after payment:
   ```bash
   tail -f mpesa_callback.log
   ```
5. **Verify status** updates to 'completed' within seconds
6. **No manual "Check Status" needed**

---

## 🎉 SUCCESS INDICATORS

You'll know it's working when:

✅ Callback log shows "CALLBACK RECEIVED" immediately after payment
✅ Status updates to 'completed' within 5-10 seconds
✅ No need to click "Check Status" button
✅ Voucher is available immediately after payment
✅ SMS is sent automatically (if configured)

---

**Last Updated:** 2025-10-04
**Status:** Ready for Implementation

