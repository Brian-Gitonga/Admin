# Portal Router ID Priority Fix - CRITICAL SECURITY & FUNCTIONALITY FIX

## Problem Identified

The portal was using `business_name` parameter as the PRIMARY method to determine which reseller's packages to display. This created a **critical security and functionality issue**:

### The Bug
When accessing the portal with a URL like:
```
http://localhost/SAAS/Wifi%20Billiling%20system/Admin/portal.php?router_id=26&business=delta
```

If you changed the `business` parameter to another business name (e.g., `business=alpha`), the portal would display **Alpha's packages** even though `router_id=26` belongs to **Delta**. This is wrong because:

1. **Security Issue**: Any reseller could see another reseller's packages by manipulating the URL
2. **Functionality Issue**: The wrong packages would be displayed for a given router
3. **Data Integrity Issue**: Payments and vouchers could be assigned to the wrong reseller

### Root Cause

The original code flow was:
1. Get `business_name` from URL
2. Look up `reseller_id` from `business_name`
3. Then try to validate `router_id` against that `reseller_id`
4. If router doesn't match, just reset `router_id` to 0

This is **backwards**! The router should determine the reseller, not the business name.

## Solution Implemented

### New Priority System

The corrected code now follows this logic:

**PRIORITY 1: Use router_id (PRIMARY METHOD)**
```php
if ($router_id > 0) {
    // Get router from hotspots table
    // Extract reseller_id from the router record
    // This is the AUTHORITATIVE source of truth
}
```

**PRIORITY 2: Fall back to business_name (ONLY if no router_id)**
```php
if ($resellerId == 0) {
    // Only use business_name if router_id wasn't provided or was invalid
    // This maintains backward compatibility for direct portal access
}
```

### Code Changes

**Before (WRONG):**
```php
// Get business name from URL parameter
$businessName = isset($_GET['business']) ? $_GET['business'] : 'Qtro Wifi';

// Get reseller ID from business name
$resellerId = getResellerIdByBusinessName($conn, $businessName);

// Get router details if router_id is provided
if ($router_id > 0) {
    $routerQuery = "SELECT * FROM hotspots WHERE id = ? AND reseller_id = ? AND is_active = 1";
    // This validates router AGAINST the business name - WRONG!
}
```

**After (CORRECT):**
```php
// PRIORITY 1: If router_id is provided, get reseller_id from the hotspots table
if ($router_id > 0) {
    $routerQuery = "SELECT h.*, h.reseller_id FROM hotspots h WHERE h.id = ? AND h.is_active = 1";
    // Get reseller_id FROM the router - CORRECT!
    $resellerId = $routerInfo['reseller_id'];
}

// PRIORITY 2: If no valid router_id, fall back to business_name
if ($resellerId == 0) {
    $resellerId = getResellerIdByBusinessName($conn, $businessName);
}
```

## How It Works Now

### Scenario 1: Portal accessed from Captive Portal (WITH router_id)
```
URL: portal.php?router_id=26&business=delta
```

**Flow:**
1. ✅ Extract `router_id=26` from URL
2. ✅ Query hotspots table: `SELECT * FROM hotspots WHERE id = 26`
3. ✅ Get `reseller_id` from the router record (e.g., reseller_id=5)
4. ✅ Display packages for reseller_id=5 (Delta's packages)
5. ✅ Ignore the `business` parameter completely

**Result:** Even if someone changes `business=alpha` in the URL, it will STILL show Delta's packages because the router belongs to Delta.

### Scenario 2: Portal accessed directly (WITHOUT router_id)
```
URL: portal.php?business=delta
```

**Flow:**
1. ✅ No router_id provided
2. ✅ Fall back to business_name lookup
3. ✅ Query resellers table: `SELECT id FROM resellers WHERE business_name = 'delta'`
4. ✅ Get reseller_id from business name
5. ✅ Display packages for that reseller (all packages, not router-specific)

**Result:** Backward compatible for direct portal access without router_id.

## Security Benefits

1. **URL Manipulation Protection**: Changing the `business` parameter in the URL no longer affects which packages are displayed when `router_id` is present
2. **Correct Package Display**: Each router always shows its owner's packages
3. **Payment Integrity**: Payments are always assigned to the correct reseller
4. **Voucher Integrity**: Vouchers are generated for the correct reseller

## Database Relationships

The fix properly respects the database relationships:

```
hotspots table
├── id (router_id)
├── reseller_id (FOREIGN KEY to resellers.id)
└── name, router_ip, etc.

resellers table
├── id (reseller_id)
├── business_name
└── other reseller info

packages table
├── id
├── reseller_id (FOREIGN KEY to resellers.id)
└── package details
```

**Correct Flow:**
```
router_id → hotspots.reseller_id → packages.reseller_id → packages
```

**Wrong Flow (before fix):**
```
business_name → resellers.id → packages.reseller_id → packages
(router_id was only used for validation, not as primary identifier)
```

## Error Logging

The fix includes comprehensive logging:

```php
error_log("Portal: Attempting to load portal using router_id: $router_id");
error_log("Portal: Found router '$router_id' belonging to reseller_id: $resellerId");
error_log("Portal: No valid router_id, falling back to business_name: $businessName");
error_log("Portal: Final reseller_id: $resellerId, router_id: $router_id");
```

Check your error logs to verify the correct flow is being followed.

## Testing

### Test Case 1: Verify router_id takes priority
```
URL: portal.php?router_id=26&business=wrongbusiness
Expected: Shows packages for the reseller who owns router 26
Actual: ✅ PASS - Shows correct reseller's packages
```

### Test Case 2: Verify business_name fallback works
```
URL: portal.php?business=delta
Expected: Shows packages for Delta reseller
Actual: ✅ PASS - Shows Delta's packages
```

### Test Case 3: Verify invalid router_id falls back
```
URL: portal.php?router_id=999999&business=delta
Expected: Falls back to business_name since router doesn't exist
Actual: ✅ PASS - Shows Delta's packages
```

## Migration Notes

**No database changes required!** This is purely a logic fix in the PHP code.

**Backward Compatibility:** ✅ Maintained
- Old URLs without router_id still work
- Old URLs with router_id work better (more secure)

## Files Modified

- **portal.php** (lines 9-62): Rewrote reseller identification logic to prioritize router_id

## Conclusion

This fix ensures that:
1. ✅ The `router_id` is the PRIMARY and AUTHORITATIVE source for determining which reseller's packages to display
2. ✅ The `business_name` is only used as a FALLBACK when no router_id is provided
3. ✅ Security is improved by preventing URL manipulation
4. ✅ Data integrity is maintained across payments and vouchers
5. ✅ The system properly respects database foreign key relationships

**This is a CRITICAL fix that should be deployed immediately to production.**