
# Payment Flow - Quick Reference Guide

## 🎯 Simple Overview

When a customer clicks on a package, here's what happens:

```
┌─────────────────────────────────────────────────────────────────┐
│                    CUSTOMER CLICKS PACKAGE                      │
└──────────────────────────────┬──────────────────────────────────┘
                               ↓
                    ┌──────────────────────┐
                    │  Check Voucher       │
                    │  Availability        │
                    └──────────┬───────────┘
                               ↓
                    ┌──────────────────────┐
                    │  Which Payment       │
                    │  Gateway?            │
                    └──────┬───────────────┘
                           │
        ┌──────────────────┴──────────────────┐
        │                                     │
        ↓                                     ↓
┌───────────────┐                    ┌───────────────┐
│    M-PESA     │                    │   PAYSTACK    │
└───────┬───────┘                    └───────┬───────┘
        │                                     │
        ↓                                     ↓
┌───────────────┐                    ┌───────────────┐
│  STK Push     │                    │  Redirect to  │
│  to Phone     │                    │  Paystack     │
└───────┬───────┘                    └───────┬───────┘
        │                                     │
        ↓                                     ↓
┌───────────────┐                    ┌───────────────┐
│  Customer     │                    │  Customer     │
│  Pays on      │                    │  Pays on      │
│  Phone        │                    │  Paystack     │
└───────┬───────┘                    └───────┬───────┘
        │                                     │
        │            ┌────────────┐           │
        └───────────→│  Callback  │←──────────┘
                     │  Received  │
                     └──────┬─────┘
                            ↓
                     ┌──────────────┐
                     │  Verify      │
                     │  Payment     │
                     └──────┬───────┘
                            ↓
                     ┌──────────────┐
                     │  Auto Assign │
                     │  Voucher     │
                     └──────┬───────┘
                            ↓
                     ┌──────────────┐
                     │  Send SMS    │
                     │  to Customer │
                     └──────┬───────┘
                            ↓
                     ┌──────────────┐
                     │  Show Success│
                     │  Page        │
                     └──────────────┘
```

---

## 📋 Detailed Steps

### **1. Package Click** (`portal.php`)
- Customer sees packages on portal
- Clicks a package card
- Modal opens showing package details and payment form

### **2. Form Submission** (`portal.php`)
- Customer enters phone number
- Clicks "Pay Now"
- System checks if vouchers are available

### **3. Payment Processing**

#### **For M-Pesa:**
- `process_payment.php` → Sends STK push to customer's phone
- Customer completes payment on phone
- `mpesa_callback.php` → Receives payment confirmation

#### **For Paystack:**
- `process_paystack_payment.php` → Initializes payment
- Customer redirected to Paystack checkout
- Customer completes payment
- `paystack_verify.php` → Verifies payment

### **4. Voucher Assignment** (`auto_process_vouchers.php`)
- After payment verified:
  1. Find available voucher for package
  2. Assign voucher to customer phone
  3. Send SMS with voucher code

### **5. Success**
- Customer redirected to portal
- Success message displayed
- SMS sent with WiFi credentials

---

## 🔑 Key Functions

| Function | File | Purpose |
|----------|------|---------|
| `showFreeTrialModal()` | `portal.php` | Handle free trial packages |
| Payment form submission | `portal.php` | Validate and submit payment |
| `check_voucher_availability.php` | - | Pre-payment voucher check |
| `process_payment.php` | - | M-Pesa STK push |
| `process_paystack_payment.php` | - | Paystack initialization |
| `paystack_verify.php` | - | Verify Paystack payment |
| `processSpecificTransaction()` | `auto_process_vouchers.php` | Auto-assign voucher |
| `findAvailableVoucher()` | `fetch_umeskia_vouchers.php` | Find unused voucher |
| `assignVoucherToCustomer()` | `fetch_umeskia_vouchers.php` | Assign voucher |
| `sendVoucherSms()` | `fetch_umeskia_vouchers.php` | Send SMS |

---

## 🗄️ Database Flow

```
payment_transactions (Paystack)
        ↓
mpesa_transactions (Compatibility layer)
        ↓
vouchers (Find & Assign)
        ↓
SMS sent to customer
```

---

## ⚠️ Important Points

1. **Voucher availability is checked BEFORE payment**
2. **Payment gateway is determined from `mpesa_settings` table**
3. **Voucher assignment is AUTOMATIC after payment**
4. **SMS is sent AUTOMATICALLY after voucher assignment**
5. **Both M-Pesa and Paystack use same voucher system**

---

## 🐛 Common Issues to Check

- ✅ Voucher availability check working?
- ✅ Payment gateway detection correct?
- ✅ Callback URLs correct for Paystack?
- ✅ Transaction status updates correctly?
- ✅ Voucher assignment happens automatically?
- ✅ SMS sending configured correctly?
- ✅ Error messages displayed to customer?

---



# WiFi Billing System - SaaS Hotspot Payment Gateway

## Overview
This system is a SaaS hotspot payment gateway that manages WiFi access through voucher-based payments. It provides payment gateway integration that automatically generates voucher codes after successful payments, designed to work with any captive portal system.

## Payment Gateway and Voucher System

### Voucher Generation Process
1. Customers select a WiFi package and make payment via M-Pesa or Paystack
2. Upon successful payment verification, a unique voucher code is generated
3. The voucher is stored in the database with status "active"
4. The voucher code is provided to the customer for WiFi access

### SaaS Multi-Tenant Architecture
The system supports multiple ISP resellers, each managing their own:
- Hotspot locations and router configurations
- WiFi packages and pricing
- Payment settings and branding
- Customer vouchers and transactions

### Payment Flow
1. `process_payment.php` initiates M-Pesa payment requests
2. `check_payment_status.php` verifies payment completion
3. Upon payment confirmation, a voucher is generated and linked to the customer
4. The voucher code is displayed to the customer for immediate use

### Package Configuration
The system supports flexible package types with different durations:
- Hourly packages: 1 hour access
- Daily packages: 1 day access
- Weekly packages: 7 days access
- Monthly packages: 30 days access

Each hotspot location can have its own set of packages and vouchers, allowing for multi-location deployments with separate captive portals.

## Excel Voucher Import Feature

This feature allows you to import vouchers from Excel files (.xlsx or .xls) in addition to CSV and PDF files.

### Requirements

- PHP 7.2 or higher
- Composer (dependency manager for PHP)
- PhpSpreadsheet library

### Installation Instructions

1. **Install Composer** (if not already installed):
   - Download and install from [getcomposer.org](https://getcomposer.org/download/)
   - Follow the installation instructions for your operating system

2. **Install Dependencies**:
   - Run the provided batch file:
     ```
     install_dependencies.bat
     ```
   - Or manually run:
     ```
     composer install
     ```
   - This will create a vendor directory with the PhpSpreadsheet library and its dependencies

3. **Excel File Format Requirements**:
   - The Excel file must contain a header row with column names
   - At minimum, there must be a column named 'code' (case-insensitive)
   - Optional columns: 'username' and 'password'
   - If username/password columns are not present, the voucher code will be used for both

### Troubleshooting

- If you encounter an error about missing PhpSpreadsheet library:
  ```
  PhpSpreadsheet library not found. Please run 'composer install' first.
  ```
  Run the installation process as described above.

- Make sure the uploads directory has write permissions

- If you encounter memory limit issues with large Excel files, you may need to increase PHP memory limits in php.ini:
  ```
  memory_limit = 256M
  ```

### Support

For any issues or questions, please contact the system administrator.

## MikroTik Router Configuration Script

This automated script helps ISP resellers configure their MikroTik routers for hotspot deployment with walled-garden access to the payment portal.

### Features
- Automated hotspot setup with captive portal
- Walled-garden configuration allowing access to:
  - Payment portal: `https://qtroispman.co.ke`
  - M-Pesa/Safaricom payment services
- Separate management and hotspot networks
- Pre-configured firewall rules for security
- API and Winbox access control

### Prerequisites
- Fresh or factory-reset MikroTik router
- WAN connection on ether1
- LAN ports: ether2-4 and wlan1 (wireless)

### Network Configuration
- **Management IP**: 192.168.88.1/24 (for Winbox/API access)
- **Hotspot Gateway**: 192.168.89.1/24 (for client connections)
- **Hotspot Pool**: 192.168.89.2-192.168.89.254

### Installation Instructions

1. **Connect to your MikroTik router** via Winbox or SSH
2. **Open a new terminal window** in the router
3. **Copy and paste the entire script** below into the terminal
4. **Press Enter** to execute
5. **Wait for completion** - you'll see a log message when done

### Configuration Script

```routeros
# --- BEGIN: Automated Hotspot + Walled-Garden Setup Script ---
# Assumptions: fresh/reset router. WAN on ether1. LAN ports ether2-4 + wlan1.

# 0. Safe defaults - remove any lingering old configs (be careful on non-fresh routers)
# (Comment out these lines if you don't want auto-clean)
# /interface bridge remove [find]
# /ip address remove [find]
# /ip dhcp-server remove [find]
# /ip hotspot remove [find]
# /ip hotspot profile remove [find]

# 1. WAN - DHCP client (gets internet from ISP)
/ip dhcp-client
add interface=ether1 use-peer-dns=yes use-peer-ntp=yes disabled=no

# 2. Bridge for LAN & Wi-Fi (management + hotspot share same physical network)
/interface bridge
add name=bridge-lan

/interface bridge port
add bridge=bridge-lan interface=ether2
add bridge=bridge-lan interface=ether3
add bridge=bridge-lan interface=ether4
# Add wireless interface to bridge (if present)
add bridge=bridge-lan interface=wlan1

# 3. Management IP (use this address for Winbox, Mikhmon, API)
/ip address
add address=192.168.88.1/24 interface=bridge-lan comment="Management IP - use for Winbox/Mikhmon"

# 4. Hotspot gateway IP (client pool will be in this subnet)
add address=192.168.89.1/24 interface=bridge-lan comment="Hotspot Gateway"

# 5. NAT masquerade (internet for authenticated users)
/ip firewall nat
add chain=srcnat out-interface=ether1 action=masquerade comment="Masquerade WAN"

# 6. Enable API and Winbox only to management/hotspot subnets
/ip service
set api disabled=no address=192.168.88.0/24,192.168.89.0/24
set winbox address=192.168.88.0/24

# 7. DNS (router will answer DNS for clients)
/ip dns
set servers=8.8.8.8,8.8.4.4 allow-remote-requests=yes

# 8. Hotspot: pool, profile, and hotspot server
/ip pool
add name=hs-pool ranges=192.168.89.2-192.168.89.254

/ip hotspot profile
add name=hs-profile hotspot-address=192.168.89.1 dns-name=wifi.login html-directory=hotspot

/ip hotspot
add name=hotspot1 interface=bridge-lan address-pool=hs-pool profile=hs-profile

# 9. Example user profiles and a test voucher (customize rates/profiles as needed)
/ip hotspot user profile
add name=1hr limit-uptime=1h rate-limit=2M/2M
add name=1day limit-uptime=1d rate-limit=2M/2M

/ip hotspot user
add name=TEST password=1234 profile=1hr comment="Test voucher - delete in prod"

# 10. Walled-garden - ALLOW these domains before login (add your real payment domain below)
/ip hotspot walled-garden
add dst-host=qtroispman.co.ke
add dst-host=*.qtroispman.co.ke
add dst-host=*.safaricom.com
add dst-host=*.mpesa.com
add dst-host=*.safaricom.co.ke
# If using ngrok for testing, add your ngrok domain:
# add dst-host=your-ngrok-id.ngrok-free.app
# add dst-host=*.ngrok-free.app

# 11. Firewall - allow router services and block guest -> WAN except allowed services
# 11.1 Accept established/related (always keep)
/ip firewall filter
add chain=forward connection-state=established,related action=accept comment="accept established"

# 11.2 Allow hotspot server traffic (so clients can reach hotspot web pages and router DNS)
add chain=forward src-address=192.168.89.0/24 dst-address=192.168.89.1 action=accept comment="allow hs clients to hotspot gateway"

# 11.3 Allow DNS from clients to router (so DNS queries go to router)
add chain=forward src-address=192.168.89.0/24 dst-port=53 protocol=udp action=accept comment="allow DNS to router"

# 11.4 Allow DNS to external servers (if you want to let clients use 8.8.8.8 directly)
add chain=forward src-address=192.168.89.0/24 dst-port=53 protocol=udp dst-address=8.8.8.8 action=accept comment="allow DNS to 8.8.8.8"

# 11.5 Allow HTTP/HTTPS to walled-garden - NOTE: hotspot walled-garden handles domain allow; we also allow connections to router and DNS.
# (We cannot reliably match domain names in raw firewall; hotspot walled-garden will permit the DSN+HTTP/HTTPS flows to those hosts.)
# 11.6 BLOCK: drop all other forwarding from hotspot subnet to WAN - force login
add chain=forward src-address=192.168.89.0/24 out-interface=ether1 action=drop comment="block hotspot clients to WAN until authenticated"

# 11.7 Router protection - allow Winbox/API only from management net
add chain=input dst-port=8291 protocol=tcp src-address=192.168.88.0/24 action=accept comment="winbox from mgmt net"
add chain=input dst-port=8728 protocol=tcp src-address=192.168.88.0/24 action=accept comment="api from mgmt net"

# 11.8 Accept related/established to input
add chain=input connection-state=established,related action=accept

# 11.9 Drop unwanted input from WAN
add chain=input in-interface=ether1 action=drop comment="drop direct router input on WAN"

# 12. Wireless basic settings (if wlan1 exists)
/interface wireless
set wlan1 disabled=no ssid="MyWiFi" mode=ap-bridge

# 13. Final housekeeping: enable hotspot (already added) and show important info
:log info "Hotspot setup completed. Management IP is 192.168.88.1. Hotspot gateway 192.168.89.1"
/ip hotspot print
/ip address print
/ip service print
# --- END: Automated Hotspot + Walled-Garden Setup Script ---
```

### Post-Installation Steps

1. **Customize WiFi Settings**:
   - Change the SSID from "MyWiFi" to your preferred network name
   - Set up wireless security (WPA2) via Winbox

2. **Remove Test Voucher**:
   - Delete the TEST voucher (username: TEST, password: 1234) before going live

3. **Customize User Profiles**:
   - Adjust bandwidth limits (rate-limit) as needed
   - Add more profiles for different package durations

4. **Upload Captive Portal**:
   - Use the captive portal generator in the admin panel
   - Upload the generated HTML files to the router's hotspot directory

5. **Verify Walled-Garden**:
   - Before logging in, test that you can access `https://qtroispman.co.ke`
   - Verify that other sites are blocked until login

### Troubleshooting

**Cannot access payment portal before login:**
- Check walled-garden rules: `/ip hotspot walled-garden print`
- Ensure DNS is working: `/ip dns print`
- Verify firewall rules are not blocking: `/ip firewall filter print`

**Clients cannot connect to hotspot:**
- Check hotspot status: `/ip hotspot print`
- Verify IP pool: `/ip pool print`
- Check bridge configuration: `/interface bridge print`

**Management access issues:**
- Connect via LAN cable to ether2-4
- Access Winbox at 192.168.88.1
- Check service restrictions: `/ip service print`

### Security Notes

- API and Winbox are restricted to management network (192.168.88.0/24)
- WAN input is blocked by default
- Only authenticated users can access the internet
- Walled-garden allows payment processing before authentication

### Customization

To add additional walled-garden domains (e.g., for other payment providers):
```routeros
/ip hotspot walled-garden
add dst-host=yourdomain.com
add dst-host=*.yourdomain.com
```

To modify bandwidth limits:
```routeros
/ip hotspot user profile
set [find name=1hr] rate-limit=5M/5M
```
