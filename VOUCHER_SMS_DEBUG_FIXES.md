# Voucher SMS Delivery Debug - COMPREHENSIVE FIXES

## 🎯 **Issues Being Debugged**

### **Issue 1: Paid Packages (Paystack Payments)**
- Payment completes successfully but voucher SMS not delivered
- Need to debug why SMS sending fails after payment verification

### **Issue 2: Free Trial Vouchers**
- New customers not receiving free trial vouchers via SMS
- Getting error: "An error occurred while processing your request. Please try again later."

## ✅ **Debugging Enhancements Implemented**

### **1. Fixed Database Connection Issues**

**Problem:** Free trial SMS function was using wrong database connection

**Fix Applied:**
```php
// Before (WRONG)
function sendFreeTrialVoucherSMS($phoneNumber, $voucherCode, $username, $password, $packageName, $resellerId) {
    global $conn;

// After (CORRECT)
function sendFreeTrialVoucherSMS($phoneNumber, $voucherCode, $username, $password, $packageName, $resellerId) {
    global $conn, $portal_conn;
    // Use portal_conn if available, fallback to conn
    $db_conn = $portal_conn ?: $conn;
```

### **2. Added Comprehensive Logging to Both SMS Functions**

#### **Enhanced paystack_verify.php SMS Function:**
- ✅ **Entry logging:** Logs when SMS sending starts
- ✅ **Settings validation:** Logs SMS settings retrieval and validation
- ✅ **Phone formatting:** Logs phone number formatting
- ✅ **Message preparation:** Logs template and final message
- ✅ **Provider selection:** Logs which SMS provider is being used
- ✅ **API results:** Logs detailed API responses
- ✅ **Exception handling:** Logs full exception details and stack traces

#### **Enhanced process_free_trial.php SMS Function:**
- ✅ **Entry logging:** Logs when free trial SMS sending starts
- ✅ **Settings validation:** Logs SMS settings retrieval and validation
- ✅ **Phone formatting:** Logs phone number formatting
- ✅ **Message preparation:** Logs template and final message
- ✅ **Provider selection:** Logs which SMS provider is being used
- ✅ **API results:** Logs detailed API responses
- ✅ **Exception handling:** Logs full exception details and stack traces

### **3. Enhanced Main Workflow Logging**

#### **In paystack_verify.php (Paid Packages):**
```php
log_debug("=== INITIATING VOUCHER SMS DELIVERY ===");
log_debug("Voucher details - Code: " . $voucher['code'] . ", Username: " . ($voucher['username'] ?: $voucher['code']));
// ... SMS function call ...
log_debug("SMS function returned: " . json_encode($smsResult));
```

#### **In process_free_trial.php (Free Trials):**
```php
error_log("=== INITIATING FREE TRIAL SMS DELIVERY ===");
error_log("Voucher details - Code: $voucher_code, Username: $voucher_username, Password: $voucher_password");
// ... SMS function call ...
error_log("Free Trial SMS function returned: " . json_encode($smsResult));
```

## 🧪 **Testing Tools Created**

### **1. debug_voucher_sms.php**
**Purpose:** Comprehensive SMS delivery debugging

**Features:**
- ✅ Database connection verification
- ✅ SMS settings analysis
- ✅ Function availability testing
- ✅ Recent transaction analysis
- ✅ Error log monitoring
- ✅ Step-by-step recommendations

### **2. test_voucher_workflows.php**
**Purpose:** Interactive testing of both SMS workflows

**Features:**
- ✅ **Prerequisites check:** Verifies all requirements are met
- ✅ **Paid package SMS test:** Simulates post-payment SMS sending
- ✅ **Free trial SMS test:** Simulates free trial SMS sending
- ✅ **Real-time logging:** Shows detailed logs during testing
- ✅ **Interactive interface:** Click buttons to run tests
- ✅ **JSON responses:** Detailed test results with error information

### **3. Enhanced Existing Tools**
- ✅ **test_sms_send.php:** Basic SMS API testing (already working)
- ✅ **debug_paystack_payment.php:** Payment debugging
- ✅ **test_payment_recording.php:** Payment recording verification

## 🔍 **How to Debug the Issues**

### **Step 1: Use the Interactive Test Tool**
1. Access `test_voucher_workflows.php` in your browser
2. Check that all prerequisites are met
3. Click "Test Paid Package SMS" to test the payment workflow
4. Click "Test Free Trial SMS" to test the free trial workflow
5. Monitor the results and logs

### **Step 2: Check Comprehensive Logs**
The enhanced logging will show exactly where each workflow fails:

#### **For Paid Packages (check paystack_verify.log):**
```
[2024-01-01 12:00:00] === INITIATING VOUCHER SMS DELIVERY ===
[2024-01-01 12:00:00] Voucher details - Code: ABC123, Username: user123
[2024-01-01 12:00:00] === VOUCHER SMS SENDING STARTED ===
[2024-01-01 12:00:00] Phone: 254700123456, Voucher: ABC123, Package: Premium, Reseller: 1
[2024-01-01 12:00:00] SMS Settings retrieved: Found
[2024-01-01 12:00:00] SMS Provider: textsms
[2024-01-01 12:00:00] Phone formatted from 254700123456 to 254700123456
[2024-01-01 12:00:00] SMS Template: Thank you for your purchase...
[2024-01-01 12:00:00] SMS Message prepared: Thank you for your purchase of Premium...
[2024-01-01 12:00:00] Sending SMS via provider: textsms
[2024-01-01 12:00:00] TextSMS result: {"success":true,"message":"SMS sent successfully"}
```

#### **For Free Trials (check error_log or PHP error log):**
```
[2024-01-01 12:00:00] === INITIATING FREE TRIAL SMS DELIVERY ===
[2024-01-01 12:00:00] Voucher details - Code: FREE123, Username: free123, Password: free123
[2024-01-01 12:00:00] === FREE TRIAL SMS SENDING STARTED ===
[2024-01-01 12:00:00] Phone: 254700123456, Voucher: FREE123, Package: Free Trial, Reseller: 1
[2024-01-01 12:00:00] SMS Settings retrieved: Found
[2024-01-01 12:00:00] SMS Provider: textsms
[2024-01-01 12:00:00] Phone formatted from 254700123456 to 254700123456
[2024-01-01 12:00:00] SMS Template: Thank you for your free trial...
[2024-01-01 12:00:00] SMS Message prepared: Thank you for your free trial of Free Trial...
[2024-01-01 12:00:00] Sending SMS via provider: textsms
[2024-01-01 12:00:00] TextSMS result: {"success":true,"message":"SMS sent successfully"}
```

### **Step 3: Analyze the Results**
Based on the logs, you can identify:
- ✅ **If SMS functions are being called** (look for "INITIATING" messages)
- ✅ **If SMS settings are found** (look for "SMS Settings retrieved")
- ✅ **If phone formatting works** (look for "Phone formatted")
- ✅ **If message preparation works** (look for "SMS Message prepared")
- ✅ **If API calls succeed** (look for provider results)
- ✅ **Any exceptions or errors** (look for error messages)

## 🎯 **Expected Debugging Outcomes**

### **Scenario 1: SMS Functions Not Being Called**
**Symptoms:** No "INITIATING" messages in logs
**Possible Causes:**
- Voucher assignment failing before SMS
- Exception thrown before SMS function call
- Workflow not reaching SMS sending code

### **Scenario 2: SMS Settings Issues**
**Symptoms:** "SMS Settings retrieved: Not found" or "SMS is disabled"
**Possible Causes:**
- SMS settings not configured for the reseller
- SMS disabled in settings
- Database connection issues

### **Scenario 3: SMS Provider Issues**
**Symptoms:** SMS function called but API returns errors
**Possible Causes:**
- Invalid API credentials
- SMS provider account issues
- Network connectivity problems
- Incorrect phone number format

### **Scenario 4: Exception Handling**
**Symptoms:** Exception messages in logs
**Possible Causes:**
- Database connection failures
- Missing functions or files
- PHP errors or syntax issues

## 🚀 **Next Steps After Debugging**

### **1. Run the Tests**
1. Access `test_voucher_workflows.php`
2. Run both paid package and free trial tests
3. Check if you receive SMS on your phone
4. Review the detailed logs

### **2. Analyze the Results**
1. If tests succeed but real workflows fail, compare the differences
2. If tests fail, fix the identified issues
3. If SMS API works but voucher SMS doesn't, check the workflow integration

### **3. Fix Identified Issues**
Based on the debugging results:
- Fix SMS configuration issues
- Resolve database connection problems
- Correct API credential issues
- Address any workflow integration problems

### **4. Test Real Workflows**
1. Try an actual payment with a small amount
2. Try a free trial request with a new phone number
3. Monitor the enhanced logs during real transactions
4. Verify SMS delivery to your phone

## 📋 **Debugging Checklist**

- [ ] **Prerequisites met:** Database connections, SMS settings, test data
- [ ] **Interactive tests run:** Both paid and free trial workflows tested
- [ ] **Logs analyzed:** Detailed logs reviewed for errors
- [ ] **SMS delivery verified:** Actual SMS received on phone
- [ ] **Real workflows tested:** Actual payment and free trial processes tested
- [ ] **Issues identified:** Specific problems pinpointed
- [ ] **Fixes applied:** Solutions implemented based on findings

## 🎉 **Conclusion**

With these comprehensive debugging enhancements:

✅ **Complete visibility** into both SMS workflows
✅ **Detailed logging** at every step of the process
✅ **Interactive testing tools** for immediate feedback
✅ **Database connection fixes** for consistency
✅ **Exception handling** with full stack traces
✅ **Step-by-step debugging guide** for systematic troubleshooting

**You now have all the tools needed to identify and fix the exact cause of the voucher SMS delivery issues! 🔍**

The enhanced logging will show you exactly where each workflow fails, allowing you to pinpoint and resolve the specific problems preventing SMS delivery for both paid packages and free trials.
