# Voucher Retrieval System Documentation

## Overview
This document explains the new voucher retrieval system that allows users to view their vouchers by entering their phone number on the portal.

## System Flow

### 1. User Journey
1. User makes a payment via M-Pesa
2. Payment is recorded in `mpesa_transactions` table
3. User clicks "View Voucher" button on portal
4. User enters their phone number
5. System retrieves and displays their voucher

### 2. Technical Flow

#### Step 1: Find Latest Transaction
- Query `mpesa_transactions` table for the phone number
- Order by `updated_at DESC, created_at DESC` to get the most recent transaction
- If no transaction found → Show error: "No payment found"

#### Step 2: Verify Payment Status
- Check `result_code` field:
  - `NULL` → Payment pending, show: "Payment is being processed"
  - `0` → Payment successful, proceed
  - Other values → Payment failed, show error with `result_description`

#### Step 3: Check for Existing Voucher
- If `voucher_code` field is NOT NULL:
  - Return existing voucher immediately
  - This prevents duplicate voucher assignment

#### Step 4: Fetch New Voucher (if needed)
- Query `vouchers` table:
  - Match `package_id` from transaction
  - Match `reseller_id` from transaction
  - Filter by `status = 'active'`
  - Order by `created_at ASC` (oldest first)
  - Limit 1
- If no voucher available → Show error: "No vouchers available"

#### Step 5: Update Voucher Status
- Update the voucher record:
  - Set `status = 'used'`
  - Set `customer_phone = [user's phone]`
  - Set `used_at = NOW()`

#### Step 6: Update Transaction
- Update `mpesa_transactions` record:
  - Set `voucher_id = [voucher id]`
  - Set `voucher_code = [voucher code]`
  - Set `updated_at = NOW()`

#### Step 7: Return Voucher
- Return JSON response with:
  - `voucher_code`
  - `package_name`
  - `amount`
  - `transaction_date`

## Files Modified/Created

### 1. `fetch_update_voucher.php` (NEW)
**Purpose:** Backend script that handles voucher retrieval and assignment

**Key Features:**
- Phone number normalization (handles 07XX, 2547XX, etc.)
- Transaction validation
- Voucher assignment logic
- Error handling and logging
- JSON response format

**Security:**
- Prepared statements to prevent SQL injection
- Input validation
- Error logging (not displayed to user)

### 2. `portal.php` (MODIFIED)
**Changes Made:**

#### HTML Changes:
- Changed modal title from "Connect with Mobile" to "View Your Voucher"
- Removed PIN field (not needed)
- Added voucher display area (green box)
- Added error display area (red box)
- Changed button text to "View Voucher"
- Changed button icon to receipt icon

#### JavaScript Changes:
- Replaced form submission with AJAX call
- Added loading state for button
- Added success/error display logic
- Fetch API integration
- Dynamic content display

## Database Schema Requirements

### `mpesa_transactions` Table
Required columns:
```sql
- id (INT, PRIMARY KEY)
- phone_number (VARCHAR)
- package_id (INT)
- package_name (VARCHAR)
- reseller_id (INT)
- result_code (INT) -- 0 = success, NULL = pending
- result_description (VARCHAR)
- voucher_id (VARCHAR) -- Can be NULL initially
- voucher_code (VARCHAR) -- Can be NULL initially
- amount (DECIMAL)
- transaction_date (VARCHAR)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

### `vouchers` Table
Required columns:
```sql
- id (INT, PRIMARY KEY)
- code (VARCHAR, UNIQUE)
- package_id (INT)
- reseller_id (INT)
- customer_phone (VARCHAR)
- status (ENUM: 'active', 'used', 'expired')
- used_at (TIMESTAMP)
- created_at (TIMESTAMP)
```

## Usage Examples

### Example 1: First Time Voucher Retrieval
**Scenario:** User paid, voucher not yet assigned

**Request:**
```
POST fetch_update_voucher.php
phone_number=0712345678
```

**Response:**
```json
{
  "success": true,
  "voucher_code": "ABC123XYZ",
  "package_name": "Daily 1GB",
  "amount": "50.00",
  "transaction_date": "2025-10-04 10:30:00",
  "message": "Voucher generated successfully!"
}
```

### Example 2: Subsequent Retrieval
**Scenario:** User already retrieved voucher before

**Request:**
```
POST fetch_update_voucher.php
phone_number=0712345678
```

**Response:**
```json
{
  "success": true,
  "voucher_code": "ABC123XYZ",
  "package_name": "Daily 1GB",
  "amount": "50.00",
  "transaction_date": "2025-10-04 10:30:00",
  "message": "Voucher retrieved successfully!"
}
```

### Example 3: No Payment Found
**Request:**
```
POST fetch_update_voucher.php
phone_number=0799999999
```

**Response:**
```json
{
  "success": false,
  "message": "No payment found for this phone number. Please make a payment first."
}
```

### Example 4: Payment Pending
**Request:**
```
POST fetch_update_voucher.php
phone_number=0712345678
```

**Response:**
```json
{
  "success": false,
  "message": "Your payment is still being processed. Please wait a moment and try again."
}
```

### Example 5: Payment Failed
**Request:**
```
POST fetch_update_voucher.php
phone_number=0712345678
```

**Response:**
```json
{
  "success": false,
  "message": "Your payment was not successful. Result: Insufficient funds"
}
```

### Example 6: No Vouchers Available
**Request:**
```
POST fetch_update_voucher.php
phone_number=0712345678
```

**Response:**
```json
{
  "success": false,
  "message": "No vouchers available for your package. Please contact support."
}
```

## Testing Checklist

### Manual Testing Steps:
1. ✅ Test with valid phone number that has successful payment
2. ✅ Test with phone number that has no payment
3. ✅ Test with phone number that has pending payment
4. ✅ Test with phone number that has failed payment
5. ✅ Test retrieving same voucher twice (should return same code)
6. ✅ Test with different phone number formats (07XX, 2547XX, +2547XX)
7. ✅ Test when no vouchers are available in database
8. ✅ Test modal open/close functionality
9. ✅ Test loading state during AJAX call
10. ✅ Test error display

### Database Testing:
1. Verify voucher status changes from 'active' to 'used'
2. Verify customer_phone is updated in vouchers table
3. Verify voucher_code is updated in mpesa_transactions table
4. Verify updated_at timestamp is updated
5. Verify used_at timestamp is set

## Error Handling

### Frontend Errors:
- Network errors → "An error occurred. Please try again."
- Empty phone number → Browser validation (required field)

### Backend Errors:
- Database connection failed → "Database connection failed. Please try again later."
- No transaction found → "No payment found for this phone number."
- Payment pending → "Your payment is still being processed."
- Payment failed → "Your payment was not successful. Result: [description]"
- No vouchers available → "No vouchers available for your package."
- Database query errors → "An error occurred while processing your request."

## Logging

All operations are logged to PHP error log:
- Script start/end
- Phone number being processed
- Transaction found/not found
- Voucher assignment
- Errors and exceptions

**Log Location:** Check your PHP error log (usually in `/var/log/php/error.log` or configured in `php.ini`)

## Security Considerations

1. **SQL Injection Prevention:** All queries use prepared statements
2. **Input Validation:** Phone numbers are sanitized and validated
3. **Error Disclosure:** Detailed errors logged, generic errors shown to users
4. **No Authentication Required:** Users only need their phone number (same as payment)

## Future Enhancements

Potential improvements:
1. Add SMS notification when voucher is retrieved
2. Add rate limiting to prevent abuse
3. Add voucher expiry date display
4. Add option to resend voucher via SMS
5. Add transaction history view
6. Add copy-to-clipboard button for voucher code

## Support

If users encounter issues:
1. Check PHP error logs for detailed error messages
2. Verify database connection is working
3. Verify mpesa_transactions table has the payment record
4. Verify vouchers table has available vouchers for the package
5. Check that result_code is set to 0 for successful payments

