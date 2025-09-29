// Create Voucher Modal functionality
const createVoucherBtn = document.getElementById('create-voucher-btn');
const createVoucherModal = document.getElementById('create-voucher-modal');
const createModalClose = document.getElementById('create-modal-close');
const createCancelBtn = document.getElementById('create-cancel-btn');
const saveVoucherBtn = document.getElementById('save-voucher-btn');
const voucherForm = document.getElementById('voucher-form');

function openCreateModal() {
    createVoucherModal.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent scrolling
}

function closeCreateModal() {
    createVoucherModal.classList.remove('active');
    document.body.style.overflow = ''; // Re-enable scrolling
    voucherForm.reset(); // Reset form fields
}

createVoucherBtn.addEventListener('click', openCreateModal);
createModalClose.addEventListener('click', closeCreateModal);
createCancelBtn.addEventListener('click', closeCreateModal);

// Close modal when clicking outside
createVoucherModal.addEventListener('click', (e) => {
    if (e.target === createVoucherModal) {
        closeCreateModal();
    }
});

// Upload Vouchers Modal functionality
const uploadVouchersBtn = document.getElementById('upload-vouchers-btn');
const uploadVouchersModal = document.getElementById('upload-vouchers-modal');
const uploadModalClose = document.getElementById('upload-modal-close');
const uploadCancelBtn = document.getElementById('upload-cancel-btn');
const uploadVouchersSubmitBtn = document.getElementById('upload-vouchers-submit-btn');
const uploadForm = document.getElementById('upload-form');

function openUploadModal() {
    uploadVouchersModal.classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevent scrolling
}

function closeUploadModal() {
    uploadVouchersModal.classList.remove('active');
    document.body.style.overflow = ''; // Re-enable scrolling
    uploadForm.reset(); // Reset form fields
}

uploadVouchersBtn.addEventListener('click', openUploadModal);
uploadModalClose.addEventListener('click', closeUploadModal);
uploadCancelBtn.addEventListener('click', closeUploadModal);

// Close modal when clicking outside
uploadVouchersModal.addEventListener('click', (e) => {
    if (e.target === uploadVouchersModal) {
        closeUploadModal();
    }
});

// File upload functionality
const fileInput = document.getElementById('voucher-file');
const fileUploadBtn = document.querySelector('.file-upload-btn');

fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        fileUploadBtn.innerHTML = `<i class="fas fa-file"></i><span>${this.files[0].name}</span>`;
    } else {
        fileUploadBtn.innerHTML = `<i class="fas fa-cloud-upload-alt"></i><span>Click to select file or drag and drop</span>`;
    }
});

// Drag and drop functionality
fileUploadBtn.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.style.borderColor = 'var(--accent-blue)';
    this.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
});

fileUploadBtn.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.style.borderColor = '';
    this.style.backgroundColor = '';
});

fileUploadBtn.addEventListener('drop', function(e) {
    e.preventDefault();
    this.style.borderColor = '';
    this.style.backgroundColor = '';
    
    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        if (fileInput.files.length > 0) {
            this.innerHTML = `<i class="fas fa-file"></i><span>${fileInput.files[0].name}</span>`;
        }
    }
});

// Form submission - Create Voucher
saveVoucherBtn.addEventListener('click', (e) => {
    // e.preventDefault(); -- Removed to allow normal form submission
    
    // Check form validity
    if (voucherForm.checkValidity()) {
        // Get form values
        const packageType = document.getElementById('package-type').value;
        const voucherCount = document.getElementById('voucher-count').value;
        
        // Here you would typically send this data to your backend
        console.log({
            packageType,
            voucherCount
        });
        
        // Show success message (in a real app, you'd wait for API response)
        alert(`${voucherCount} vouchers generated successfully!`);
        
        // Close the modal
        closeCreateModal();
    } else {
        // Trigger browser's default form validation UI
        voucherForm.reportValidity();
    }
});

// Form submission - Upload Vouchers
uploadVouchersSubmitBtn.addEventListener('click', (e) => {
    // e.preventDefault(); -- Removed to allow normal form submission
    
    // Check if file is selected
    if (fileInput.files.length > 0) {
        // Here you would typically send this file to your backend
        console.log({
            file: fileInput.files[0]
        });
        
        // Show success message (in a real app, you'd wait for API response)
        alert('Vouchers uploaded successfully!');
        
        // Close the modal
        closeUploadModal();
    } else {
        alert('Please select a file to upload');
    }
});

// Print functionality
const printBtns = document.querySelectorAll('.print-btn');

printBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const voucherCode = btn.closest('tr').querySelector('td:nth-child(2)').textContent;
        const packageName = btn.closest('tr').querySelector('td:nth-child(3)').textContent;
        
        // Here you would typically handle printing the voucher
        console.log(`Printing voucher: ${voucherCode} for package: ${packageName}`);
        alert(`Printing voucher: ${voucherCode}`);
    });
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