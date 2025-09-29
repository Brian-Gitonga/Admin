# Super Admin Remote Access Approval System

## Overview
This is a standalone system for the super admin (codebase owner) to manage remote access requests submitted through the main SaaS hotspot payment gateway system.

## Features
- **Standalone Operation**: No login required, completely independent from main system authentication
- **Request Management**: View, approve, and reject remote access requests
- **Real-time Statistics**: Dashboard showing pending, approved, and rejected request counts
- **Filtering**: Filter requests by status (All, Pending, Approved, Rejected)
- **Approval Workflow**: Provide remote access credentials when approving requests
- **Rejection Workflow**: Provide rejection reasons when declining requests
- **Responsive Design**: Works on desktop and mobile devices

## File Structure
```
super-admin/
├── index.php              # Main dashboard interface
├── process_approval.php   # Backend processing for approve/reject actions
├── config.php            # Database connection and helper functions
├── style.css             # Styling for the admin interface
└── README.md             # This documentation file
```

## Installation & Setup

### 1. Database Setup
Ensure the remote access tables exist in your database by running the SQL from `remote_access_schema.sql`:

```sql
-- The main table for storing remote access requests
CREATE TABLE IF NOT EXISTS `remote_access_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reseller_id` int(11) NOT NULL,
  `router_id` int(11) NOT NULL,
  `request_status` enum('ordered','rejected','approved') NOT NULL DEFAULT 'ordered',
  `admin_comments` text DEFAULT NULL,
  `remote_username` varchar(100) DEFAULT NULL,
  `remote_password` varchar(255) DEFAULT NULL,
  `dns_name` varchar(255) DEFAULT NULL,
  `remote_port` int(11) DEFAULT 8291,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
```

### 2. Database Configuration
Update the database connection settings in `config.php`:

```php
$servername = "localhost";
$username = "root";        // Your database username
$password = "";            // Your database password
$dbname = "billing_system"; // Your database name
```

### 3. Access the System
Navigate to: `http://your-domain.com/super-admin/`

## Usage Guide

### Dashboard Overview
- **Statistics Cards**: Shows counts of pending, approved, rejected, and total requests
- **Filter Tabs**: Click to filter requests by status
- **Requests Table**: Displays all requests with reseller info, router details, and actions

### Approving Requests
1. Click the "Approve" button for a pending request
2. Fill in the required remote access credentials:
   - **Username**: Remote access username for the router
   - **Password**: Remote access password
   - **DNS Name**: Optional DNS name (e.g., router1.company.com)
   - **Port**: Remote access port (default: 8291)
   - **Comments**: Optional admin notes
3. Click "Approve Request"

### Rejecting Requests
1. Click the "Reject" button for a pending request
2. Provide a clear rejection reason
3. Click "Reject Request"

### Request Status Flow
- **Ordered** (Pending): Initial state when user submits request
- **Approved**: Admin has approved and provided credentials
- **Rejected**: Admin has rejected with reason

## Security Considerations

### Important Notes
- This system has **NO AUTHENTICATION** by design for standalone operation
- **Restrict access** to this folder using web server configuration
- Consider IP whitelisting for additional security
- Use HTTPS in production environments

### Apache .htaccess Example
```apache
# Restrict access to specific IP addresses
<RequireAll>
    Require ip 192.168.1.100
    Require ip 203.0.113.0/24
</RequireAll>
```

### Nginx Configuration Example
```nginx
location /super-admin/ {
    allow 192.168.1.100;
    allow 203.0.113.0/24;
    deny all;
}
```

## API Endpoints

### POST /super-admin/process_approval.php
Handles approval and rejection actions.

**Request Body (Approval):**
```json
{
    "action": "approve",
    "request_id": 123,
    "credentials": {
        "username": "admin",
        "password": "secure_password",
        "dns_name": "router1.company.com",
        "port": 8291
    },
    "admin_comments": "Approved for maintenance access"
}
```

**Request Body (Rejection):**
```json
{
    "action": "reject",
    "request_id": 123,
    "admin_comments": "Insufficient documentation provided"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Request approved successfully",
    "action": "approved"
}
```

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `config.php`
   - Ensure database server is running
   - Verify database name exists

2. **No Requests Showing**
   - Ensure `remote_access_requests` table exists
   - Check if requests are being submitted from main system
   - Verify foreign key relationships

3. **JavaScript Errors**
   - Check browser console for errors
   - Ensure all files are accessible
   - Verify AJAX requests are not blocked

### Debug Mode
To enable debug mode, add this to the top of `config.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Integration with Main System

This super admin system reads from the same database tables that the main SaaS system writes to:
- `remote_access_requests`: Main requests table
- `resellers`: For reseller information
- `hotspots`: For router information

The main system's `linkrouter.php` creates requests that appear in this admin system.

## Customization

### Adding New Fields
1. Update database schema
2. Modify `config.php` helper functions
3. Update `index.php` display
4. Adjust `process_approval.php` processing

### Styling Changes
All styles are in `style.css` using CSS custom properties for easy theming.

## Support

For technical support or questions about this system, contact the development team or refer to the main system documentation.
