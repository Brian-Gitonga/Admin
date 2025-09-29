// Enhanced voucher.js file with Ajax functionality
// This will replace or enhance the existing voucher.js when loaded

document.addEventListener('DOMContentLoaded', function() {
    // Basic UI setup
    setupTabs();
    setupFormValidation();
    setupTableFilters();
    setupPagination();
    
    // Create Voucher Modal functionality
    setupCreateVoucherModal();
    
    // Print and Delete buttons
    setupActionButtons();
});

// Tab switching functionality
function setupTabs() {
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and add to clicked tab
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Hide all tab contents and show the selected one
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            document.getElementById(`${tabName}-tab`).classList.add('active');
            
            // If we're switching to a different tab, refresh the data
            loadVouchers(tabName);
        });
    });
    
    // Load the default (active) tab data
    const activeTab = document.querySelector('.tab.active');
    if (activeTab) {
        loadVouchers(activeTab.getAttribute('data-tab'));
    }
}

// Load vouchers from the server via Ajax
function loadVouchers(status, page = 1, search = '') {
    const perPage = document.querySelector(`#${status}-tab .per-page select`).value || 10;
    const offset = (page - 1) * perPage;
    
    console.log(`Loading ${status} vouchers: page ${page}, search "${search}", limit ${perPage}, offset ${offset}`);
    
    // Show loading indicator
    const tableBody = document.querySelector(`#${status}-tab tbody`);
    tableBody.innerHTML = '<tr><td colspan="8" class="text-center">Loading vouchers...</td></tr>';
    
    // Construct the URL with proper encoding
    const url = `vouchers_script/get_vouchers.php?status=${encodeURIComponent(status)}&limit=${encodeURIComponent(perPage)}&offset=${encodeURIComponent(offset)}&search=${encodeURIComponent(search)}`;
    console.log('Fetch URL:', url);
    
    // Fetch vouchers via Ajax
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Voucher data received:', data);
            
            if (data.success) {
                // Update the table with vouchers
                updateVouchersTable(status, data.vouchers);
                
                // Update pagination
                updatePagination(status, data.total, page, perPage);
                
                // Update results count
                updateResultsCount(status, data.vouchers.length, data.total);
                
                // Update tab count badge
                document.querySelector(`.tab[data-tab="${status}"] .tab-count`).textContent = data.total;
            } else {
                // Show error message
                console.warn('Error loading vouchers:', data.message);
                tableBody.innerHTML = `<tr><td colspan="8" class="text-center">${data.message || 'Failed to load vouchers'}</td></tr>`;
            }
        })
        .catch(error => {
            console.error('Error loading vouchers:', error);
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center">An error occurred while loading vouchers</td></tr>';
        });
}

// Update the vouchers table with data
function updateVouchersTable(status, vouchers) {
    const tableBody = document.querySelector(`#${status}-tab tbody`);
    
    // Clear the table
    tableBody.innerHTML = '';
    
    if (vouchers.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="8" class="text-center" style="text-align: center; color: var(--text-secondary);">No ${status} vouchers found</td></tr>`;
        return;
    }
    
    // Populate the table with voucher data
    vouchers.forEach(voucher => {
        const row = document.createElement('tr');
        row.dataset.id = voucher.id;
        
        // Format the date properly
        const createdDate = new Date(voucher.created_at);
        const formattedCreatedDate = createdDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        const usedDate = voucher.used_at ? new Date(voucher.used_at) : null;
        const formattedUsedDate = usedDate ? usedDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        }) : '-';
        
        // Determine if the voucher has been sent via SMS
        const hasSentSMS = voucher.customer_phone && voucher.customer_phone !== 'admin' && voucher.customer_phone !== 'bulk-admin';
        
        // Format username and password
        const username = voucher.username || voucher.code;
        const password = voucher.password || voucher.code;
        
        // Create the row HTML
        row.innerHTML = `
            <td class="checkbox-cell">
                <div class="custom-checkbox"></div>
            </td>
            <td>${voucher.code}</td>
            <td>${voucher.package_name || 'Unknown Package'}</td>
            <td><span class="credential-field">${username}</span></td>
            <td><span class="credential-field">${password}</span></td>
            <td>${formattedUsedDate}</td>
            <td>${formattedCreatedDate}</td>
            <td>
                <button class="sms-btn${hasSentSMS ? ' sent' : ''}" data-code="${voucher.code}" data-phone="${voucher.customer_phone || ''}">
                    <i class="fas fa-${hasSentSMS ? 'redo' : 'sms'}"></i>
                    <span>${hasSentSMS ? 'Resend' : 'Send'}</span>
                </button>
                ${status === 'unused' ? `
                <button class="delete-btn" data-id="${voucher.id}">
                    <i class="fas fa-trash-alt"></i>
                </button>` : ''}
            </td>
        `;
        
        tableBody.appendChild(row);
    });
    
    // Add CSS for the credential fields
    if (!document.getElementById('credential-styles')) {
        const styleSheet = document.createElement('style');
        styleSheet.id = 'credential-styles';
        styleSheet.textContent = `
            .credential-field {
                font-family: monospace;
                font-weight: 500;
                padding: 2px 6px;
                border-radius: 4px;
                background-color: var(--bg-accent);
            }
        `;
        document.head.appendChild(styleSheet);
    }
    
    // Reattach event listeners to the new buttons
    attachButtonListeners();
}

// Update pagination controls
function updatePagination(status, total, currentPage, perPage) {
    const tableFooter = document.querySelector(`#${status}-tab .table-footer`);
    const paginationContainer = document.querySelector(`#${status}-tab .pagination`);
    
    // If pagination container doesn't exist, create it
    if (!paginationContainer) {
        const newPaginationContainer = document.createElement('div');
        newPaginationContainer.className = 'pagination';
        tableFooter.appendChild(newPaginationContainer);
    }
    
    const container = document.querySelector(`#${status}-tab .pagination`) || newPaginationContainer;
    
    // Calculate total pages
    const totalPages = Math.ceil(total / perPage);
    
    // If only one page, hide pagination
    if (totalPages <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'flex';
    
    // Create pagination HTML
    let paginationHTML = '';
    
    // Previous button
    paginationHTML += `<button class="pagination-btn prev${currentPage === 1 ? ' disabled' : ''}" ${currentPage === 1 ? 'disabled' : ''}>
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
    // Page numbers
    const maxButtons = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
    let endPage = Math.min(totalPages, startPage + maxButtons - 1);
    
    if (endPage - startPage + 1 < maxButtons) {
        startPage = Math.max(1, endPage - maxButtons + 1);
    }
    
    if (startPage > 1) {
        paginationHTML += `<button class="pagination-btn page" data-page="1">1</button>`;
        if (startPage > 2) {
            paginationHTML += `<span class="pagination-ellipsis">...</span>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        paginationHTML += `<button class="pagination-btn page${i === currentPage ? ' active' : ''}" data-page="${i}">${i}</button>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationHTML += `<span class="pagination-ellipsis">...</span>`;
        }
        paginationHTML += `<button class="pagination-btn page" data-page="${totalPages}">${totalPages}</button>`;
    }
    
    // Next button
    paginationHTML += `<button class="pagination-btn next${currentPage === totalPages ? ' disabled' : ''}" ${currentPage === totalPages ? 'disabled' : ''}>
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    container.innerHTML = paginationHTML;
    
    // Add event listeners
    const paginationButtons = container.querySelectorAll('.pagination-btn');
    paginationButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (this.classList.contains('disabled')) return;
            
            let nextPage = currentPage;
            
            if (this.classList.contains('prev')) {
                nextPage = currentPage - 1;
            } else if (this.classList.contains('next')) {
                nextPage = currentPage + 1;
            } else if (this.classList.contains('page')) {
                nextPage = parseInt(this.getAttribute('data-page'));
            }
            
            if (nextPage !== currentPage) {
                loadVouchers(status, nextPage, document.querySelector(`#${status}-tab .search-filter input`).value);
            }
        });
    });
}

// Update results count display
function updateResultsCount(status, shown, total) {
    const resultsInfo = document.querySelector(`#${status}-tab .results-info`);
    if (resultsInfo) {
        resultsInfo.textContent = `Showing ${shown} of ${total} results`;
    }
}

// Setup form validation
function setupFormValidation() {
    const voucherForm = document.getElementById('voucher-form');
    const uploadForm = document.getElementById('upload-form');
    
    if (voucherForm) {
        voucherForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (this.checkValidity()) {
                createVouchers();
            } else {
                this.reportValidity();
            }
        });
    }
    
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (document.getElementById('voucher-file').files.length > 0) {
                uploadVouchers();
            } else {
                alert('Please select a file to upload');
            }
        });
    }
}

// Setup table filters
function setupTableFilters() {
    // Search filter
    const searchInputs = document.querySelectorAll('.search-filter input');
    searchInputs.forEach(input => {
        let typingTimer;
        const doneTypingInterval = 500; // ms
        
        input.addEventListener('keyup', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                const status = this.closest('.tab-content').id.replace('-tab', '');
                loadVouchers(status, 1, this.value);
            }, doneTypingInterval);
        });
    });
    
    // Per page selector
    const perPageSelects = document.querySelectorAll('.per-page select');
    perPageSelects.forEach(select => {
        select.addEventListener('change', function() {
            const status = this.closest('.tab-content').id.replace('-tab', '');
            loadVouchers(status, 1, document.querySelector(`#${status}-tab .search-filter input`).value);
        });
    });
}

// Setup pagination
function setupPagination() {
    // Initial pagination setup (empty containers)
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        const tableFooter = content.querySelector('.table-footer');
        if (tableFooter && !tableFooter.querySelector('.pagination')) {
            const paginationContainer = document.createElement('div');
            paginationContainer.className = 'pagination';
            tableFooter.appendChild(paginationContainer);
        }
    });
}

// Add a global phone numbers array to store previously used numbers for autofill
let recentPhoneNumbers = [];

// Load recent phone numbers
function loadRecentPhoneNumbers() {
    try {
        // Try to load from localStorage if available
        const storedNumbers = localStorage.getItem('recentPhoneNumbers');
        if (storedNumbers) {
            recentPhoneNumbers = JSON.parse(storedNumbers);
        }
    } catch (e) {
        console.error('Error loading recent phone numbers:', e);
        recentPhoneNumbers = [];
    }
    
    // If none were loaded, initialize with empty array
    if (!Array.isArray(recentPhoneNumbers)) {
        recentPhoneNumbers = [];
    }
    
    return recentPhoneNumbers;
}

// Save a phone number to the recent numbers list
function savePhoneNumber(phoneNumber) {
    if (!phoneNumber || phoneNumber.trim() === '') return;
    
    // Remove any existing instance of this number
    const index = recentPhoneNumbers.indexOf(phoneNumber);
    if (index !== -1) {
        recentPhoneNumbers.splice(index, 1);
    }
    
    // Add to the beginning of the array
    recentPhoneNumbers.unshift(phoneNumber);
    
    // Keep only the 10 most recent numbers
    if (recentPhoneNumbers.length > 10) {
        recentPhoneNumbers = recentPhoneNumbers.slice(0, 10);
    }
    
    // Save to localStorage if available
    try {
        localStorage.setItem('recentPhoneNumbers', JSON.stringify(recentPhoneNumbers));
    } catch (e) {
        console.error('Error saving recent phone numbers:', e);
    }
}

// Set up datalist for phone number autofill
function setupPhoneAutofill(inputElement) {
    // Create or get the datalist element
    let datalist = document.getElementById('phone-datalist');
    if (!datalist) {
        datalist = document.createElement('datalist');
        datalist.id = 'phone-datalist';
        document.body.appendChild(datalist);
    }
    
    // Clear existing options
    datalist.innerHTML = '';
    
    // Load recent phone numbers
    const numbers = loadRecentPhoneNumbers();
    
    // Add options to the datalist
    numbers.forEach(number => {
        const option = document.createElement('option');
        option.value = number;
        datalist.appendChild(option);
    });
    
    // Associate the datalist with the input
    inputElement.setAttribute('list', 'phone-datalist');
}

// Update the createVouchers function to include the phone number
function createVouchers() {
    const packageType = document.getElementById('package-type').value;
    const voucherCount = parseInt(document.getElementById('voucher-count').value);
    const phoneNumber = document.getElementById('customer-phone') ? document.getElementById('customer-phone').value.trim() : '';
    
    // Validate inputs
    if (!packageType) {
        alert('Please select a package');
        return;
    }
    
    if (isNaN(voucherCount) || voucherCount < 1) {
        alert('Please enter a valid number of vouchers');
        return;
    }
    
    // Show loading state
    const saveButton = document.getElementById('save-voucher-btn');
    const originalButtonText = saveButton.innerHTML;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    saveButton.disabled = true;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', voucherCount > 1 ? 'bulk_create' : 'create');
    formData.append('package_id', packageType);
    formData.append('count', voucherCount);
    
    console.log(`Submitting voucher request: action=${voucherCount > 1 ? 'bulk_create' : 'create'}, package_id=${packageType}, count=${voucherCount}`);
    
    // Send Ajax request with better error handling
    fetch('vouchers_script/voucher_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Voucher creation response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        
        return response.text().then(text => {
            // Try to parse as JSON, but handle potential parsing errors
            console.log('Raw response text:', text.substring(0, 200) + (text.length > 200 ? '...' : ''));
            
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error('Error parsing JSON response:', err);
                console.error('Raw response:', text);
                
                // Check for common issues in the response
                if (text.includes("Warning:") || text.includes("Notice:") || text.includes("Fatal error:")) {
                    throw new Error('PHP error in response: ' + text.substring(0, 100) + '...');
                }
                
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        console.log('Voucher generation response:', data);
        
        if (data.success) {
            // If we have a phone number, send the voucher via SMS
            if (phoneNumber && data.data && (data.data.voucher_code || (data.data.voucher_codes && data.data.voucher_codes.length > 0))) {
                // Save the phone number for future autofill
                savePhoneNumber(phoneNumber);
                
                if (voucherCount === 1 && data.data.voucher_code) {
                    // For single voucher, send it via SMS
                    sendVoucherSMS(data.data.voucher_code, phoneNumber);
                } else if (data.data.voucher_codes && data.data.voucher_codes.length > 0) {
                    // For multiple vouchers, send the first one or all depending on preference
                    const shouldSendAll = confirm(`Send all ${data.data.voucher_codes.length} vouchers to ${phoneNumber}?\n\nClick OK to send all vouchers to this number.\nClick Cancel to send only the first voucher.`);
                    
                    if (shouldSendAll) {
                        // Send all vouchers sequentially (could be optimized for bulk sending in the future)
                        let successCount = 0;
                        let failCount = 0;
                        let currentIndex = 0;
                        
                        function sendNextVoucher() {
                            if (currentIndex >= data.data.voucher_codes.length) {
                                // All done
                                alert(`Sent ${successCount} vouchers successfully, ${failCount} failed.`);
                                return;
                            }
                            
                            const code = data.data.voucher_codes[currentIndex];
                            currentIndex++;
                            
                            // Use a more direct approach for bulk sending
                            const smsFormData = new FormData();
                            smsFormData.append('action', 'send_voucher');
                            smsFormData.append('voucher_code', code);
                            smsFormData.append('phone_number', phoneNumber);
                            
                            fetch('../message_api/send_voucher_sms.php', {
                                method: 'POST',
                                body: smsFormData
                            })
                            .then(response => response.json())
                            .then(smsData => {
                                if (smsData.success) {
                                    successCount++;
                                } else {
                                    failCount++;
                                }
                                // Process next voucher
                                sendNextVoucher();
                            })
                            .catch(error => {
                                console.error('Error sending voucher SMS:', error);
                                failCount++;
                                // Continue with next voucher despite error
                                sendNextVoucher();
                            });
                        }
                        
                        // Start the sending process
                        sendNextVoucher();
                    } else {
                        // Send only the first voucher
                        sendVoucherSMS(data.data.voucher_codes[0], phoneNumber);
                    }
                }
            } else {
                // Show success message with details
                if (voucherCount === 1 && data.data && data.data.voucher_code) {
                    // Single voucher generated
                    const message = `Voucher generated successfully!\n\nVoucher Code: ${data.data.voucher_code}\n\n${data.message}`;
                    alert(message);
                } else if (data.data && data.data.voucher_codes) {
                    // Multiple vouchers generated
                    let message = `${data.message}\n\nGenerated ${data.data.count} vouchers:`;
                    
                    // Show up to 5 codes in the alert
                    const maxCodesToShow = Math.min(5, data.data.voucher_codes.length);
                    for (let i = 0; i < maxCodesToShow; i++) {
                        message += `\n- ${data.data.voucher_codes[i]}`;
                    }
                    
                    // If there are more codes, indicate this
                    if (data.data.voucher_codes.length > maxCodesToShow) {
                        message += `\n... and ${data.data.voucher_codes.length - maxCodesToShow} more`;
                    }
                    
                    alert(message);
                } else {
                    // Generic success message
                    alert(data.message);
                }
            }
            
            // Close the modal
            closeCreateModal();
            
            // Refresh the vouchers list
            loadVouchers('unused');
        } else {
            // Show error message
            alert(data.message || 'Failed to create vouchers');
        }
    })
    .catch(error => {
        console.error('Error creating vouchers:', error);
        alert('An error occurred while creating vouchers. Please try again later.');
    })
    .finally(() => {
        // Reset button state
        saveButton.innerHTML = originalButtonText;
        saveButton.disabled = false;
    });
}

// Upload vouchers from a file
function uploadVouchers() {
    const fileInput = document.getElementById('voucher-file');
    
    if (fileInput.files.length === 0) {
        alert('Please select a file to upload');
        return;
    }
    
    // Show loading state
    const uploadButton = document.getElementById('upload-vouchers-submit-btn');
    const originalButtonText = uploadButton.innerHTML;
    uploadButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    uploadButton.disabled = true;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('voucher_file', fileInput.files[0]);
    
    // Send Ajax request
    fetch('vouchers_script/voucher_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert(data.message);
            
            // Close the modal
            closeUploadModal();
            
            // Refresh the vouchers list
            loadVouchers('unused');
        } else {
            // Show error message
            alert(data.message || 'Failed to upload vouchers');
        }
    })
    .catch(error => {
        console.error('Error uploading vouchers:', error);
        alert('An error occurred while uploading vouchers');
    })
    .finally(() => {
        // Reset button state
        uploadButton.innerHTML = originalButtonText;
        uploadButton.disabled = false;
    });
}

// Setup action buttons (print, delete)
function setupActionButtons() {
    // Initial setup for existing buttons
    attachButtonListeners();
    
    // Select all checkboxes
    const selectAllCheckboxes = document.querySelectorAll('#select-all, #select-all-used');
    selectAllCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('click', function() {
            const isChecked = this.classList.toggle('checked');
            const tabId = this.id === 'select-all' ? 'unused-tab' : 'used-tab';
            const rowCheckboxes = document.querySelectorAll(`#${tabId} .custom-checkbox`);
            
            rowCheckboxes.forEach(rowCheckbox => {
                if (isChecked) {
                    rowCheckbox.classList.add('checked');
                } else {
                    rowCheckbox.classList.remove('checked');
                }
            });
        });
    });
}

// Attach listeners to action buttons
function attachButtonListeners() {
    // Individual row checkboxes
    const rowCheckboxes = document.querySelectorAll('.checkbox-cell .custom-checkbox');
    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('click', function() {
            this.classList.toggle('checked');
        });
    });
    
    // SMS buttons (replacing print buttons)
    const smsButtons = document.querySelectorAll('.sms-btn');
    smsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const voucherCode = this.getAttribute('data-code');
            const phoneNumber = this.getAttribute('data-phone');
            sendVoucherSMS(voucherCode, phoneNumber || null);
        });
    });
    
    // Delete buttons
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const voucherId = this.getAttribute('data-id');
            deleteVoucher(voucherId);
        });
    });
}

// Replace the existing printVoucher function with sendVoucherSMS
function sendVoucherSMS(voucherCode, phoneNumber) {
    if (!voucherCode) {
        console.error('No voucher code provided for SMS');
        return;
    }
    
    if (!phoneNumber) {
        // If no phone number is provided, show a dialog to enter one
        showPhonePrompt(voucherCode);
        return;
    }
    
    console.log(`Sending voucher with code: ${voucherCode} to phone: ${phoneNumber}`);
    
    // Show loading state
    const affectedButton = document.querySelector(`.sms-btn[data-code="${voucherCode}"]`);
    if (affectedButton) {
        const originalHTML = affectedButton.innerHTML;
        affectedButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        affectedButton.disabled = true;
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'send_voucher');
        formData.append('voucher_code', voucherCode);
        formData.append('phone_number', phoneNumber);
        
        // Send Ajax request
        fetch('../message_api/send_voucher_sms.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                alert(data.message);
                
                // Change button to "Resend" after success
                affectedButton.innerHTML = '<i class="fas fa-redo"></i><span>Resend</span>';
                affectedButton.classList.add('sent');
                
                // Refresh the vouchers list to update the customer phone number
                const activeTab = document.querySelector('.tab.active');
                if (activeTab) {
                    loadVouchers(activeTab.getAttribute('data-tab'));
                }
            } else {
                // Show error message
                alert(data.message || 'Failed to send voucher via SMS');
                affectedButton.innerHTML = originalHTML;
            }
        })
        .catch(error => {
            console.error('Error sending voucher via SMS:', error);
            alert('An error occurred while sending the voucher via SMS');
            affectedButton.innerHTML = originalHTML;
        })
        .finally(() => {
            affectedButton.disabled = false;
        });
    } else {
        // If button not found, just make the request
        const formData = new FormData();
        formData.append('action', 'send_voucher');
        formData.append('voucher_code', voucherCode);
        formData.append('phone_number', phoneNumber);
        
        fetch('../message_api/send_voucher_sms.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
            } else {
                alert(data.message || 'Failed to send voucher via SMS');
            }
        })
        .catch(error => {
            console.error('Error sending voucher via SMS:', error);
            alert('An error occurred while sending the voucher via SMS');
        });
    }
}

// Function to show a phone number input prompt
function showPhonePrompt(voucherCode) {
    // Create modal for entering phone number
    const modalOverlay = document.createElement('div');
    modalOverlay.className = 'modal-overlay active';
    
    const modalHTML = `
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">Enter Phone Number</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p>Enter the phone number to send the voucher code:</p>
                <form id="phone-form">
                    <div class="form-group">
                        <label for="recipient-phone" class="form-label">Phone Number</label>
                        <input type="tel" id="recipient-phone" class="form-input" 
                               placeholder="e.g., 0712345678" required
                               pattern="[0-9+\s]+" title="Enter a valid phone number">
                        <p class="form-hint">Include country code or start with 0</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="phone-cancel-btn">Cancel</button>
                <button class="btn btn-primary" id="phone-send-btn">
                    <i class="fas fa-paper-plane"></i>
                    <span>Send Voucher</span>
                </button>
            </div>
        </div>
    `;
    
    modalOverlay.innerHTML = modalHTML;
    document.body.appendChild(modalOverlay);
    
    // Focus the phone input
    const phoneInput = document.getElementById('recipient-phone');
    phoneInput.focus();
    
    // Add event listeners
    const closeBtn = modalOverlay.querySelector('.modal-close');
    const cancelBtn = document.getElementById('phone-cancel-btn');
    const sendBtn = document.getElementById('phone-send-btn');
    const phoneForm = document.getElementById('phone-form');
    
    // Close the modal when clicking the close button or cancel
    closeBtn.addEventListener('click', () => {
        document.body.removeChild(modalOverlay);
    });
    
    cancelBtn.addEventListener('click', () => {
        document.body.removeChild(modalOverlay);
    });
    
    // Close modal when clicking outside
    modalOverlay.addEventListener('click', function(e) {
        if (e.target === modalOverlay) {
            document.body.removeChild(modalOverlay);
        }
    });
    
    // Handle form submission
    phoneForm.addEventListener('submit', (e) => {
        e.preventDefault();
        if (phoneForm.checkValidity()) {
            const phoneNumber = phoneInput.value.trim();
            document.body.removeChild(modalOverlay);
            sendVoucherSMS(voucherCode, phoneNumber);
        } else {
            phoneForm.reportValidity();
        }
    });
    
    // Send button click
    sendBtn.addEventListener('click', () => {
        phoneForm.dispatchEvent(new Event('submit'));
    });
}

// Delete a voucher
function deleteVoucher(voucherId) {
    if (!confirm('Are you sure you want to delete this voucher?')) {
        return;
    }
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('voucher_id', voucherId);
    
    // Send Ajax request
    fetch('vouchers_script/voucher_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert(data.message);
            
            // Refresh the vouchers list
            loadVouchers('unused');
        } else {
            // Show error message
            alert(data.message || 'Failed to delete voucher');
        }
    })
    .catch(error => {
        console.error('Error deleting voucher:', error);
        alert('An error occurred while deleting the voucher');
    });
}

// Create Voucher Modal functionality
function setupCreateVoucherModal() {
    const createVoucherBtn = document.getElementById('create-voucher-btn');
    const createVoucherModal = document.getElementById('create-voucher-modal');
    const createModalClose = document.getElementById('create-modal-close');
    const createCancelBtn = document.getElementById('create-cancel-btn');
    const saveVoucherBtn = document.getElementById('save-voucher-btn');
    
    if (createVoucherBtn && createVoucherModal) {
        createVoucherBtn.addEventListener('click', openCreateModal);
    }
    
    if (createModalClose) {
        createModalClose.addEventListener('click', closeCreateModal);
    }
    
    if (createCancelBtn) {
        createCancelBtn.addEventListener('click', closeCreateModal);
    }
    
    if (saveVoucherBtn) {
        saveVoucherBtn.addEventListener('click', function() {
            document.getElementById('voucher-form').dispatchEvent(new Event('submit'));
        });
    }
    
    // Close modal when clicking outside
    if (createVoucherModal) {
        createVoucherModal.addEventListener('click', function(e) {
            if (e.target === createVoucherModal) {
                closeCreateModal();
            }
        });
    }
    
    // Add phone number field to the form if it doesn't exist
    const voucherForm = document.getElementById('voucher-form');
    if (voucherForm && !document.getElementById('customer-phone')) {
        const phoneFieldHtml = `
            <div class="form-group">
                <label for="customer-phone" class="form-label">Customer Phone (Optional)</label>
                <input type="tel" id="customer-phone" class="form-input" 
                       placeholder="Enter customer phone number" 
                       pattern="[0-9+\s]+" title="Enter a valid phone number">
                <p class="form-hint">Enter phone number to send voucher via SMS</p>
            </div>
        `;
        
        // Insert the phone field before the last form group
        const formGroups = voucherForm.querySelectorAll('.form-group');
        if (formGroups.length > 0) {
            const lastFormGroup = formGroups[formGroups.length - 1];
            lastFormGroup.insertAdjacentHTML('afterend', phoneFieldHtml);
            
            // Set up autofill for the phone number input
            setupPhoneAutofill(document.getElementById('customer-phone'));
        }
    }
    
    // Fetch packages for the dropdown
    fetchPackages();
}

// Open Create Voucher Modal
function openCreateModal() {
    const createVoucherModal = document.getElementById('create-voucher-modal');
    if (createVoucherModal) {
        createVoucherModal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
        
        // Set up autofill for the phone number input
        if (document.getElementById('customer-phone')) {
            setupPhoneAutofill(document.getElementById('customer-phone'));
        }
    }
}

// Close Create Voucher Modal
function closeCreateModal() {
    const createVoucherModal = document.getElementById('create-voucher-modal');
    const voucherForm = document.getElementById('voucher-form');
    
    if (createVoucherModal) {
        createVoucherModal.classList.remove('active');
        document.body.style.overflow = ''; // Re-enable scrolling
    }
    
    if (voucherForm) {
        voucherForm.reset(); // Reset form fields
    }
}

// Fetch packages for the dropdown
function fetchPackages() {
    const packageSelect = document.getElementById('package-type');
    
    if (!packageSelect) {
        console.error('Package select element not found');
        return;
    }
    
    console.log('Fetching packages for dropdown');
    
    // Clear existing options except the first one (placeholder)
    while (packageSelect.options.length > 1) {
        packageSelect.remove(1);
    }
    
    // Add loading option
    const loadingOption = document.createElement('option');
    loadingOption.text = 'Loading packages...';
    loadingOption.disabled = true;
    packageSelect.add(loadingOption);
    
    // Reset the save button state
    const saveButton = document.getElementById('save-voucher-btn');
    if (saveButton) {
        saveButton.disabled = true;
    }
    
    // Fetch packages from the server with a cache-busting parameter
    fetch('vouchers_script/get_packages.php?nocache=' + new Date().getTime())
        .then(response => {
            console.log('Packages response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Package data received:', data);
            
            // Always remove the loading option first
            if (packageSelect.options.length > 1) {
                packageSelect.remove(1); // Remove the "Loading packages..." option
            }
            
            // Check if we have packages to display
            if (data.success && data.packages && data.packages.length > 0) {
                // Add packages to the dropdown
                data.packages.forEach(package => {
                    const option = document.createElement('option');
                    option.value = package.id;
                    option.text = `${package.name} (${package.price})`;
                    packageSelect.add(option);
                });
                
                console.log(`Added ${data.packages.length} packages to dropdown`);
                
                // Enable the save button
                if (saveButton) {
                    saveButton.disabled = false;
                }
                
                // If there was a message but we still have packages (e.g., demo packages)
                if (data.message && data.message.toLowerCase().includes('demo')) {
                    console.warn('Using demo packages:', data.message);
                    
                    // Add a note about demo packages
                    const demoNote = document.createElement('div');
                    demoNote.className = 'demo-note';
                    demoNote.innerHTML = '<i class="fas fa-info-circle"></i> ' + data.message;
                    demoNote.style.color = '#e67e22';
                    demoNote.style.marginTop = '5px';
                    demoNote.style.fontSize = '0.8rem';
                    
                    const formGroup = packageSelect.closest('.form-group');
                    if (formGroup) {
                        // Remove any existing demo notes
                        const existingNotes = formGroup.querySelectorAll('.demo-note');
                        existingNotes.forEach(note => note.remove());
                        
                        // Add the new note
                        formGroup.appendChild(demoNote);
                    }
                }
            } else {
                // Show message if no packages available
                const noPackagesOption = document.createElement('option');
                noPackagesOption.text = data.message || 'No packages available';
                noPackagesOption.disabled = true;
                packageSelect.add(noPackagesOption);
                
                // Keep the save button disabled
                if (saveButton) {
                    saveButton.disabled = true;
                }
                
                console.warn('No packages available:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching packages:', error);
            
            // Remove loading option if it exists
            if (packageSelect.options.length > 1) {
                packageSelect.remove(1);
            }
            
            // Add error option
            const errorOption = document.createElement('option');
            errorOption.text = 'Error loading packages. Please try again.';
            errorOption.disabled = true;
            packageSelect.add(errorOption);
            
            // Keep the save button disabled
            if (saveButton) {
                saveButton.disabled = true;
            }
            
            // Add a reload button
            const reloadButton = document.createElement('button');
            reloadButton.type = 'button';
            reloadButton.className = 'btn btn-sm btn-secondary';
            reloadButton.innerHTML = '<i class="fas fa-sync-alt"></i> Reload Packages';
            reloadButton.style.marginTop = '10px';
            reloadButton.style.fontSize = '0.8rem';
            reloadButton.onclick = fetchPackages;
            
            const formGroup = packageSelect.closest('.form-group');
            if (formGroup) {
                // Remove any existing reload buttons
                const existingButtons = formGroup.querySelectorAll('button.btn-secondary');
                existingButtons.forEach(btn => btn.remove());
                
                // Add the new button
                formGroup.appendChild(reloadButton);
            }
        });
} 