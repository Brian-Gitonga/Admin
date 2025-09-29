# Subscription Expiry Check Cron Job Setup

This document explains how to set up an automatic subscription expiry check using cron jobs.

## Purpose
The subscription expiry check script (`check_subscription_expiry.php`) automatically identifies expired subscriptions and updates the reseller status to 'expired' in the database. When a reseller's status is set to 'expired', they will be unable to log in to the system, as implemented in the login.php script.

## Setup Instructions

### For Linux/Unix Systems (with cPanel/WHM)

1. Log into your cPanel account
2. Navigate to "Cron Jobs" in the "Advanced" section
3. Set up a new cron job with the following settings:
   - Common Settings: Once a day (0 0 * * *)
   - Command: `php /full/path/to/your/website/check_subscription_expiry.php`

### For Linux/Unix Systems (command line)

1. Log into your server via SSH
2. Open crontab: `crontab -e`
3. Add the following line to run the check daily at midnight:
   ```
   0 0 * * * php /full/path/to/your/website/check_subscription_expiry.php
   ```
4. Save and exit

### For Windows Server

1. Open Task Scheduler
2. Click "Create Basic Task"
3. Enter a name (e.g., "Subscription Expiry Check") and description
4. Select "Daily" for the trigger
5. Set the start time (e.g., 12:00 AM)
6. Select "Start a program" for the action
7. Browse to your PHP executable (e.g., C:\xampp\php\php.exe)
8. Add arguments: `/full/path/to/your/website/check_subscription_expiry.php`
9. Complete the wizard

## Manual Testing

To manually test the expiry check script, you can run it directly in your browser or via command line:

```
php check_subscription_expiry.php
```

This will output a message indicating how many expired subscriptions were updated.

## Verification

After setting up the cron job, you can verify it's working by:

1. Creating a test reseller account with a subscription that expires soon
2. After the subscription expires, run the script manually or wait for the cron job
3. Try to log in with the test account - you should be denied access with an "account expired" message

## Log Monitoring

The script logs any errors to your PHP error log. You should regularly monitor this log for any issues with the script execution.

## Additional Notes

- The script is also automatically called when a user accesses their dashboard, as an additional safeguard
- If you move or rename the script, be sure to update the cron job accordingly

