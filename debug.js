// Paystack Integration Debugging Tool
console.log('Debug script loaded');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded - starting debug');
    
    // Check if payButton exists
    const payBtn = document.getElementById('paySubscriptionBtn');
    console.log('Pay Button element:', payBtn);
    
    if (payBtn) {
        console.log('Adding direct click handler to payment button');
        
        // Add a direct click handler
        payBtn.onclick = function(event) {
            console.log('Payment button clicked directly');
            event.preventDefault();
            
            // Get user and subscription data
            console.log('User ID:', window.userId);
            console.log('User Email:', window.userEmail);
            console.log('Subscription Data:', window.subscriptionData);
            
            // Get payment amount
            let totalAmount = 0;
            const totalAmountElement = document.getElementById('totalSubscriptionAmount');
            if (totalAmountElement) {
                console.log('Total amount element found:', totalAmountElement);
                console.log('Total amount text:', totalAmountElement.innerText);
                
                // Extract numeric value
                const amountText = totalAmountElement.innerText.replace(/[^0-9.]/g, '');
                console.log('Extracted amount text:', amountText);
                
                totalAmount = parseFloat(amountText);
                console.log('Parsed amount:', totalAmount);
            } else {
                console.error('Total amount element not found!');
            }
            
            if (isNaN(totalAmount) || totalAmount <= 0) {
                console.error('Invalid amount:', totalAmount);
                alert('Invalid payment amount');
                return;
            }
            
            // Create payment data
            const paymentData = {
                amount: totalAmount * 100, // Convert to cents/kobo
                email: window.userEmail || 'test@example.com',
                reseller_id: window.userId || 1,
                payment_type: 'subscription'
            };
            
            console.log('Payment data:', paymentData);
            
            // Send direct fetch request
            console.log('Sending payment initialization request...');
            
            // Show loading state
            payBtn.textContent = 'Processing...';
            payBtn.disabled = true;
            
            fetch('paystack_initialize.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(paymentData)
            })
            .then(response => {
                console.log('Raw response:', response);
                return response.json();
            })
            .then(data => {
                console.log('Payment initialization response:', data);
                
                if (data.success) {
                    console.log('Payment initialized successfully! Redirecting to:', data.authorization_url);
                    window.location.href = data.authorization_url;
                } else {
                    console.error('Payment initialization failed:', data.message);
                    alert('Payment error: ' + (data.message || 'Failed to initialize payment'));
                    payBtn.textContent = 'Pay Subscription (Testing Mode)';
                    payBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error: ' + error.message);
                payBtn.textContent = 'Pay Subscription (Testing Mode)';
                payBtn.disabled = false;
            });
        };
    } else {
        console.error('Payment button not found in DOM!');
    }
});

