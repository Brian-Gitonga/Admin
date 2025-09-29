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