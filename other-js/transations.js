// Checkbox functionality
const selectAllCheckbox = document.getElementById('select-all');
const checkboxes = document.querySelectorAll('.custom-checkbox');

selectAllCheckbox.addEventListener('click', () => {
    selectAllCheckbox.classList.toggle('checked');
    const isChecked = selectAllCheckbox.classList.contains('checked');
    
    checkboxes.forEach(checkbox => {
        if (checkbox !== selectAllCheckbox) {
            if (isChecked) {
                checkbox.classList.add('checked');
            } else {
                checkbox.classList.remove('checked');
            }
        }
    });
});

checkboxes.forEach(checkbox => {
    if (checkbox !== selectAllCheckbox) {
        checkbox.addEventListener('click', () => {
            checkbox.classList.toggle('checked');
            
            // Check if all checkboxes are checked
            const allChecked = Array.from(checkboxes)
                .filter(cb => cb !== selectAllCheckbox)
                .every(cb => cb.classList.contains('checked'));
            
            if (allChecked) {
                selectAllCheckbox.classList.add('checked');
            } else {
                selectAllCheckbox.classList.remove('checked');
            }
        });
    }
});

// Modal functionality
const recordPaymentBtn = document.getElementById('record-payment-btn');
const recordPaymentModal = document.getElementById('record-payment-modal');
const modalClose = document.getElementById('modal-close');
const cancelBtn = document.getElementById('cancel-btn');
const savePaymentBtn = document.getElementById('save-payment-btn');
const paymentForm = document.getElementById('payment-form');

function openModal() {
    recordPaymentModal.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent scrolling
    
    // Set default date to current date and time
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    
    document.getElementById('payment-date').value = `${year}-${month}-${day}T${hours}:${minutes}`;
}

function closeModal() {
    recordPaymentModal.classList.remove('active');
    document.body.style.overflow = ''; // Re-enable scrolling
    paymentForm.reset(); // Reset form fields
}

recordPaymentBtn.addEventListener('click', openModal);
modalClose.addEventListener('click', closeModal);
cancelBtn.addEventListener('click', closeModal);

// Close modal when clicking outside
recordPaymentModal.addEventListener('click', (e) => {
    if (e.target === recordPaymentModal) {
        closeModal();
    }
});

// Form submission
savePaymentBtn.addEventListener('click', (e) => {
    // e.preventDefault(); -- Removed to allow normal form submission
    
    // Check form validity
    if (paymentForm.checkValidity()) {
        // Get form values
        const phoneNumber = document.getElementById('phone-number').value;
        const receiptNumber = document.getElementById('receipt-number').value;
        const amount = document.getElementById('amount').value;
        const paymentDate = document.getElementById('payment-date').value;
        
        // Here you would typically send this data to your backend
        console.log({
            phoneNumber,
            receiptNumber,
            amount,
            paymentDate
        });
        
        // Show success message (in a real app, you'd wait for API response)
        alert('Payment recorded successfully!');
        
        // Close the modal
        closeModal();
    } else {
        // Trigger browser's default form validation UI
        paymentForm.reportValidity();
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize responsive behavior
    handleResize();
    
    // Initialize active nav items
    setActiveNavItem();
});

// Initialize responsive behavior
handleResize();
window.addEventListener('resize', handleResize);