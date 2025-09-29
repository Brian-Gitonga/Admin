// Debug script to troubleshoot modal issue
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - starting debug');
    
    // Get references to key elements
    const modal = document.getElementById('subscriptionModal');
    const expiryBtn = document.getElementById('expiryDateBtn');
    
    console.log('Modal element:', modal);
    console.log('Expiry button element:', expiryBtn);
    
    if (expiryBtn) {
        console.log('Adding click listener to expiry button');
        // Add an explicit click handler for testing
        expiryBtn.onclick = function() {
            console.log('Expiry button clicked');
            
            if (modal) {
                console.log('Setting modal display to block');
                modal.style.display = 'block';
                
                // Force show class after a short delay
                setTimeout(() => {
                    console.log('Adding show class to modal');
                    modal.classList.add('show');
                }, 10);
            } else {
                console.error('Modal element not found!');
            }
        };
    } else {
        console.error('Expiry button not found in DOM!');
    }
});

