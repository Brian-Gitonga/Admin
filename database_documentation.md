# WiFi Billing System Database Documentation

## Core Tables

### Resellers
The central entity of the system. Represents hotspot owners who use the platform to manage their WiFi services. in simple terms these are users who create account and login to our system
- Stores business details, contact information, and authentication credentials
- Tracks approval status and payment intervals (weekly/monthly)

### Hotspots
Physical routers managed by resellers.
- Contains router connection details (IP, credentials, API port)
- Tracks online/offline status and location information
- Each hotspot belongs to a specific reseller, in simple terms the person who has logged in to our system

### Packages
Service plans that resellers offer to their customers.
- Defines pricing, duration, data limits, and speed
- Categorized as daily, weekly, or monthly plans
- Linked to specific resellers

### End Users
WiFi customers who purchase access from resellers.
- Stores authentication credentials and plan information. important in order to see who purchased the reseller plans there for easy record keeping
- Tracks data usage and subscription expiry
- Each user is associated with a specific hotspot

## Payment Processing

### Transactions
Records payments made by end users for WiFi access.
- Stores M-Pesa transaction details and status
- Links payments to specific users and hotspots

### MPesa Transactions
Detailed tracking of M-Pesa payment processing.
- Stores checkout request IDs, receipt numbers, and transaction dates
- Includes status tracking and result codes from M-Pesa API

### Resellers MPesa Settings
Configuration for resellers' M-Pesa payment options.
- Supports different payment gateways (phone, paybill, till)
- Stores API credentials and callback URLs
- Environment settings (sandbox/live)

## Subscription Management

### Subscription Plans
Plans offered to resellers by the system administrator.
- Defines duration and pricing for platform access
- Includes descriptions and active status

### Reseller Subscriptions
Tracks resellers' payments to use the platform. in order for someone to use qtro ISP billing system he must pay a monthl fee
- Records subscription start/end dates and payment details
- Monitors subscription status (active, pending, expired) if the user is expired the user is not able to login to our system

### Subscription Requests
Handles resellers' requests for new subscriptions.
- Tracks approval status and administrative notes
- Records processing details and timestamps. 
- Don't worry about this one. this is for the owner of Qtro Isp Billing system to be able to troublehoot in case user of our system pays but subscription doesn't update

### Payouts (not mostly used in our codebase)
Manages revenue sharing with resellers.
- Tracks amounts due, payment dates, and status
- Records transaction details and payment methods.

## System Administration

### Admin
System administrators who manage the platform.
- Stores authentication credentials and profile information
- Tracks login activity

### Notifications
System messages for admins and resellers.
- Stores message content and read status
- Categorizes by recipient type

### SMS Settings
Configuration for SMS notifications.
- Supports multiple SMS providers (Africa's Talking, TextSMS, Hostpinnacle)
- Stores API credentials and template messages
- Controls SMS enabling/disabling per reseller

### Vouchers
Pre-generated access codes for WiFi service.
- Stores unique codes and associated credentials
- Tracks usage status and expiration dates
- Links to specific packages and resellers
