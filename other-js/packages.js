/**
 * Packages page JavaScript
 * Handles package creation, editing, and deletion
 */

console.log('packages.js loaded successfully');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded in packages.js');
    
    // Tab switching
const tabs = document.querySelectorAll('.tab');
const tabContents = document.querySelectorAll('.tab-content');

tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabType = this.getAttribute('data-tab');
        
            // Remove active class from all tabs and contents
        tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            document.getElementById(`${tabType}-tab`).classList.add('active');
        });
    });

    // Package creation modal
    const createPackageBtn = document.getElementById('create-package-btn');
    const modal = document.getElementById('create-package-modal');
    const modalClose = document.getElementById('modal-close');
    const cancelBtn = document.getElementById('cancel-btn');
    const packageForm = document.getElementById('package-form');
    const savePackageBtn = document.getElementById('save-package-btn');
    
    // Show modal when create button is clicked
    if (createPackageBtn) {
        createPackageBtn.addEventListener('click', function() {
            console.log('Create package button clicked');
            modal.style.display = 'flex';
            // Clear form
            packageForm.reset();
            
            // Add debug info
            console.log('Modal should be visible now');
        });
    } else {
        console.error('Create package button not found');
    }
    
    // Close modal when X or Cancel is clicked
    if (modalClose) {
        modalClose.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Package type selection affects form fields
    const packageType = document.getElementById('package-type');
    
    if (packageType) {
        packageType.addEventListener('change', function() {
            const selectedType = this.value;
            
            // Show data limit field only for data plans
            const dataLimitField = document.getElementById('data-limit-field');
            if (dataLimitField) {
                if (selectedType === 'data-plan') {
                    dataLimitField.style.display = 'block';
                } else {
                    dataLimitField.style.display = 'none';
                }
            }
        });
    }

    // Save package
    if (savePackageBtn) {
        savePackageBtn.addEventListener('click', function() {
            // Validate form
            if (!packageForm.checkValidity()) {
                packageForm.reportValidity();
                return;
            }
            
            // Create FormData object to collect form data
            const formData = new FormData(packageForm);
            
            // Add extra data
            formData.append('action', 'create');
            
            // Send form data via AJAX
            fetch('create_package.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showNotification('success', data.message);
                    
                    // Close modal
                    modal.style.display = 'none';
                    
                    // Refresh the page to show the new package
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
            } else {
                    // Show error message
                    showNotification('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'An error occurred. Please try again.');
            });
        });
    }
    
    // Action menu toggling
    const actionMenus = document.querySelectorAll('.action-menu');
    
    actionMenus.forEach(menu => {
        menu.addEventListener('click', function(e) {
            e.stopPropagation();
            
            // Hide any open menus
            document.querySelectorAll('.action-dropdown').forEach(dropdown => {
                dropdown.remove();
            });
            
            // Create dropdown menu
            const dropdown = document.createElement('div');
            dropdown.className = 'action-dropdown';
            
            // Get package data from the row
            const row = this.closest('tr');
            const packageId = row.getAttribute('data-id');
            const packageName = row.querySelector('td:nth-child(2)').textContent;
            const isEnabled = row.querySelector('.enabled-status').textContent === 'Yes';
            
            // Add menu items
            dropdown.innerHTML = `
                <div class="action-item edit-package" data-id="${packageId}">
                    <i class="fas fa-edit"></i> Edit
                </div>
                <div class="action-item toggle-status" data-id="${packageId}" data-enabled="${isEnabled}">
                    <i class="fas ${isEnabled ? 'fa-toggle-off' : 'fa-toggle-on'}"></i> 
                    ${isEnabled ? 'Disable' : 'Enable'}
                </div>
                <div class="action-item delete-package" data-id="${packageId}" data-name="${packageName}">
                    <i class="fas fa-trash"></i> Delete
                </div>
            `;
            
            // Position dropdown
            const rect = this.getBoundingClientRect();
            dropdown.style.position = 'absolute';
            dropdown.style.top = `${rect.bottom}px`;
            dropdown.style.left = `${rect.left - 100}px`;
            
            // Add to document
            document.body.appendChild(dropdown);
            
            // Handle dropdown actions
            dropdown.querySelector('.edit-package').addEventListener('click', function() {
                const packageId = this.getAttribute('data-id');
                editPackage(packageId);
            });
            
            dropdown.querySelector('.toggle-status').addEventListener('click', function() {
                const packageId = this.getAttribute('data-id');
                const isEnabled = this.getAttribute('data-enabled') === 'true';
                togglePackageStatus(packageId, isEnabled);
            });
            
            dropdown.querySelector('.delete-package').addEventListener('click', function() {
                const packageId = this.getAttribute('data-id');
                const packageName = this.getAttribute('data-name');
                confirmDeletePackage(packageId, packageName);
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function closeDropdown() {
                dropdown.remove();
                document.removeEventListener('click', closeDropdown);
            });
        });
    });
    
    // Select all checkboxes
    const selectAllCheckboxes = document.querySelectorAll('#select-all, #select-all-hotspot, #select-all-pppoe, #select-all-data');
    
    selectAllCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('click', function() {
            const isChecked = this.classList.toggle('checked');
            const tabId = this.id.replace('select-all-', '');
            const tabContent = document.getElementById(`${tabId === 'select-all' ? 'all' : tabId}-tab`);
            
            if (tabContent) {
                const checkboxes = tabContent.querySelectorAll('.custom-checkbox:not(#select-all):not(#select-all-hotspot):not(#select-all-pppoe):not(#select-all-data)');
                checkboxes.forEach(cb => {
                    if (isChecked) {
                        cb.classList.add('checked');
    } else {
                        cb.classList.remove('checked');
                    }
                });
            }
        });
    });
    
    // Individual checkboxes
    const individualCheckboxes = document.querySelectorAll('.packages-table .custom-checkbox:not(#select-all):not(#select-all-hotspot):not(#select-all-pppoe):not(#select-all-data)');
    
    individualCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('click', function() {
            this.classList.toggle('checked');
        });
    });
});

/**
 * Show a notification message
 * @param {string} type The notification type ('success' or 'error')
 * @param {string} message The message to display
 */
function showNotification(type, message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        </div>
        <div class="notification-content">
            <div class="notification-title">${type === 'success' ? 'Success' : 'Error'}</div>
            <div class="notification-message">${message}</div>
        </div>
        <div class="notification-close">
            <i class="fas fa-times"></i>
        </div>
    `;
    
    // Add to document
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Hide after timeout
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
    
    // Close button
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    });
}

/**
 * Edit a package
 * @param {number} packageId The ID of the package to edit
 */
function editPackage(packageId) {
    // This would be implemented in a future update
    showNotification('info', 'Edit functionality will be implemented in a future update.');
}

/**
 * Toggle a package's status
 * @param {number} packageId The ID of the package to toggle
 * @param {boolean} currentStatus The current status
 */
function togglePackageStatus(packageId, currentStatus) {
    const formData = new FormData();
    formData.append('action', 'toggle_status');
    formData.append('package_id', packageId);
    formData.append('is_enabled', currentStatus);
    
    fetch('package_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            
            // Refresh the page to show the updated status
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'An error occurred. Please try again.');
    });
}

/**
 * Confirm and delete a package
 * @param {number} packageId The ID of the package to delete
 * @param {string} packageName The name of the package
 */
function confirmDeletePackage(packageId, packageName) {
    if (confirm(`Are you sure you want to delete the package "${packageName}"?`)) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('package_id', packageId);
        
        fetch('package_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message);
                
                // Refresh the page to show the updated list
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                showNotification('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', 'An error occurred. Please try again.');
        });
    }
}