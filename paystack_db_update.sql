-- Add Paystack payment option to resellers_mpesa_settings table
ALTER TABLE resellers_mpesa_settings 
MODIFY COLUMN payment_gateway ENUM('phone', 'paybill', 'till', 'paystack') NOT NULL DEFAULT 'phone';

-- Add Paystack fields to resellers_mpesa_settings table
ALTER TABLE resellers_mpesa_settings 
ADD COLUMN paystack_secret_key VARCHAR(255) AFTER till_consumer_secret,
ADD COLUMN paystack_public_key VARCHAR(255) AFTER paystack_secret_key,
ADD COLUMN paystack_email VARCHAR(100) AFTER paystack_public_key;


