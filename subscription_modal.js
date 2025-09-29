// Subscription Modal Functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing subscription modal functionality');
    
    // Get references to modal elements
    const modal = document.getElementById('subscriptionModal');
    const contactModal = document.getElementById('contactAdminModal');
    const expiryBtn = document.getElementById('expiryDateBtn');
    const closeBtn = document.querySelector('#subscriptionModal .modal-close');
    const contactCloseBtn = document.getElementById('contactModalClose');
    const contactCloseBtn2 = document.querySelector('.contact-close-btn');
    const payBtn = document.getElementById('paySubscriptionBtn');
    
    // Add direct event listener to close button as soon as possible
    document.querySelectorAll('#subscriptionModal .modal-close').forEach(btn => {
        btn.onclick = function() {
            console.log('Direct close button click detected');
            if (modal) {
                modal.style.display = 'none';
            }
        };
    });
    
    console.log('Modal elements:', {
        modal: modal,
        expiryBtn: expiryBtn,
        closeBtn: closeBtn,
        payBtn: payBtn
    });
    
    // Make sure elements exist before adding event listeners
    if (!modal || !expiryBtn) {
        console.error('Critical modal elements not found!');
        return;
    }
    
    // Get subscription data from global variable
    let subscriptionData = window.subscriptionData || {};
    console.log("Loaded subscription data:", subscriptionData);
    console.log("User ID:", window.userId);
    console.log("User Email:", window.userEmail);
    
    // Update the expiry date button text
    function updateExpiryButtonText() {
        if (expiryBtn && subscriptionData) {
            if (subscriptionData.expiry_date) {
                const date = new Date(subscriptionData.expiry_date);
                expiryBtn.textContent = "Expiry Date " + date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            } else {
                expiryBtn.textContent = "No Active Subscription";
            }
        }
    }
    
    // Update modal data from subscription data
    function updateModalData() {
        if (subscriptionData) {
            // Format dates for display
            const formatDate = (dateString) => {
                if (!dateString) return "N/A";
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            };
            
            // Update modal fields with subscription data
            if (document.getElementById('lastRenewalDate')) {
                document.getElementById('lastRenewalDate').textContent = formatDate(subscriptionData.start_date);
            }
            
            if (document.getElementById('subscriptionUpdatedDate')) {
                document.getElementById('subscriptionUpdatedDate').textContent = formatDate(subscriptionData.last_payment_date);
            }
            
            if (document.getElementById('nextBillingDate')) {
                document.getElementById('nextBillingDate').textContent = formatDate(subscriptionData.next_billing_date);
            }
            
            // Update payment amount
            if (document.getElementById('lastPaymentAmount')) {
                const amount = subscriptionData.amount_paid > 0 ? 
                    `Ksh ${parseFloat(subscriptionData.amount_paid).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}` : 
                    "Ksh 0.00";
                document.getElementById('lastPaymentAmount').textContent = amount;
            }
        }
    }
    
    // Function to open the modal
    function openModal(modalElement) {
        console.log('Opening modal:', modalElement);
        if (!modalElement) {
            console.error('Attempted to open undefined modal');
            return;
        }
        
        // Set display to block first
        modalElement.style.display = 'block';
        
        // Add the show class after a small delay to trigger the animation
        setTimeout(() => {
            modalElement.classList.add('show');
        }, 10);
    }
    
    // Function to close the modal
    function closeModal(modalElement) {
        console.log('Closing modal:', modalElement);
        if (!modalElement) {
            console.error('Attempted to close undefined modal');
            return;
        }
        
        // First remove the show class to trigger the fade-out animation
        modalElement.classList.remove('show');
        
        // Then hide the modal after the animation completes
        setTimeout(() => {
            modalElement.style.display = 'none';
        }, 300);
    }
    
    // Notification system
    function showNotification(message, type = 'info') {
        // Remove any existing notification
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        // Icon based on notification type
        let icon = 'info-circle';
        if (type === 'success') icon = 'check-circle';
        if (type === 'error') icon = 'times-circle';
        if (type === 'warning') icon = 'exclamation-circle';
        
        notification.innerHTML = `
            <i class="fas fa-${icon} notification-icon"></i>
            <div class="notification-message">${message}</div>
            <button class="notification-close">&times;</button>
        `;
        
        // Add to document
        document.body.appendChild(notification);
        
        // Add close button functionality
        const closeNotification = () => {
            notification.classList.add('notification-hiding');
            setTimeout(() => {
                notification.remove();
            }, 500);
        };
        
        notification.querySelector('.notification-close').addEventListener('click', closeNotification);
        
        // Auto close after 5 seconds
        setTimeout(closeNotification, 5000);
    }
    
    // Clear any existing event listeners by cloning and replacing the button
    const oldExpiryBtn = expiryBtn;
    if (oldExpiryBtn) {
        const newExpiryBtn = oldExpiryBtn.cloneNode(true);
        oldExpiryBtn.parentNode.replaceChild(newExpiryBtn, oldExpiryBtn);
        
        // Now add our event listener to the new button
        newExpiryBtn.addEventListener('click', function(event) {
            event.preventDefault();
            console.log('Expiry button clicked');
            
            // Update modal data before showing
            updateModalData();
            
            // Open the modal
            openModal(modal);
        });
        
        // Update the expiryBtn reference to point to the new button
        expiryBtn = newExpiryBtn;
    }
    
    // Close modal when X is clicked - use a more direct approach
    // First remove any existing event listeners by cloning and replacing
    if (closeBtn) {
        const newCloseBtn = closeBtn.cloneNode(true);
        closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
        
        // Add event listener to the new button
        newCloseBtn.addEventListener('click', function(event) {
            event.preventDefault();
            console.log('Close button clicked');
            closeModal(modal);
        });
        
        // Update the closeBtn reference
        closeBtn = newCloseBtn;
    } else {
        // If closeBtn wasn't found, try to get it again and add the listener
        console.log('Close button not found initially, trying again');
        const closeButton = document.querySelector('#subscriptionModal .modal-close');
        if (closeButton) {
            console.log('Close button found on second attempt');
            closeButton.addEventListener('click', function(event) {
                event.preventDefault();
                console.log('Close button clicked (second attempt)');
                closeModal(modal);
            });
        } else {
            console.error('Could not find modal close button');
        }
    }
    
    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal(modal);
        }
        if (event.target === contactModal) {
            closeModal(contactModal);
        }
    });
    
    // Close contact modal when X is clicked
    if (contactCloseBtn) {
        contactCloseBtn.addEventListener('click', function() {
            closeModal(contactModal);
        });
    }
    
    // Close contact modal when Close button is clicked
    if (contactCloseBtn2) {
        contactCloseBtn2.addEventListener('click', function() {
            closeModal(contactModal);
        });
    }
    
    // Handle payment button click - use Paystack regardless of button class
    if (payBtn) {
        payBtn.addEventListener('click', function() {
            console.log('Payment button clicked');
            
            // Get the payment amount from the total amount displayed
            let totalAmount = 0;
            const totalAmountElement = document.getElementById('totalSubscriptionAmount');
            if (totalAmountElement) {
                // Extract the numeric value from something like "Ksh 1,234.56"
                const amountText = totalAmountElement.innerText.replace(/[^0-9.]/g, '');
                totalAmount = parseFloat(amountText);
            }
            
            // Ensure we have a valid amount
            if (isNaN(totalAmount) || totalAmount <= 0) {
                showNotification("Invalid payment amount", "error");
                return;
            }
            
            // Call the initiate payment function with the necessary data
            initiatePaystackPayment(totalAmount);
        });
    }
    
    // Function to initialize Paystack payment
    function initiatePaystackPayment(amount) {
        console.log('Initiating Paystack payment for amount:', amount);
        
        // Show loading state on the payment button
        if (payBtn) {
            const originalText = payBtn.innerText;
            payBtn.innerText = "Processing...";
            payBtn.disabled = true;
        }
        
        // Prepare payment data
        const paymentData = {
            amount: amount * 100, // Convert to kobo/cents
            email: subscriptionData.email || window.userEmail || 'subscriber@example.com',
            reseller_id: subscriptionData.reseller_id || window.userId,
            payment_type: 'subscription'
        };
        
        // Send request to initialize payment
        fetch('paystack_initialize.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(paymentData)
        })
        .then(response => response.json())
        .then(data => {
            console.log('Paystack initialization response:', data);
            
            if (data.success) {
                // Redirect to Paystack checkout page
                window.location.href = data.authorization_url;
            } else {
                // Show error message
                showNotification(data.message || "Failed to initialize payment", "error");
                
                // Reset payment button
                if (payBtn) {
                    payBtn.innerText = originalText || "Pay Subscription";
                    payBtn.disabled = false;
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification("An error occurred while processing your payment request", "error");
            
            // Reset payment button
            if (payBtn) {
                payBtn.innerText = originalText || "Pay Subscription";
                payBtn.disabled = false;
            }
        });
    }
});
