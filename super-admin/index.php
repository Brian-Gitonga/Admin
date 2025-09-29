<?php
/**
 * Super Admin Remote Access Approval Dashboard
 * Standalone system for managing remote access requests
 */

// Include database configuration
require_once 'config.php';

// Get request statistics
$stats = getRequestStats($conn);

// Get filter parameter
$filter = $_GET['filter'] ?? 'all';
$valid_filters = ['all', 'ordered', 'approved', 'rejected'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'all';
}

// Get requests based on filter
if ($filter === 'all') {
    $requests = getRemoteAccessRequests($conn);
} else {
    $requests = getRemoteAccessRequests($conn, $filter);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - Remote Access Approval</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1>
                <i class="fas fa-shield-alt"></i>
                Remote Access Approval System
            </h1>
            <div style="color: #6b7280; font-size: 0.875rem;">
                Super Admin Dashboard
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <h3>Pending Requests</h3>
                <div class="number"><?php echo $stats['ordered']; ?></div>
            </div>
            <div class="stat-card approved">
                <h3>Approved</h3>
                <div class="number"><?php echo $stats['approved']; ?></div>
            </div>
            <div class="stat-card rejected">
                <h3>Rejected</h3>
                <div class="number"><?php echo $stats['rejected']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Requests</h3>
                <div class="number"><?php echo $stats['total']; ?></div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                    onclick="filterRequests('all')">
                All Requests
            </button>
            <button class="filter-tab <?php echo $filter === 'ordered' ? 'active' : ''; ?>" 
                    onclick="filterRequests('ordered')">
                Pending (<?php echo $stats['ordered']; ?>)
            </button>
            <button class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>" 
                    onclick="filterRequests('approved')">
                Approved (<?php echo $stats['approved']; ?>)
            </button>
            <button class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>" 
                    onclick="filterRequests('rejected')">
                Rejected (<?php echo $stats['rejected']; ?>)
            </button>
        </div>

        <!-- Requests Table -->
        <div class="requests-container">
            <?php if ($requests && $requests->num_rows > 0): ?>
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Reseller</th>
                            <th>Router</th>
                            <th>Status</th>
                            <th>Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = $requests->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo $request['id']; ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['reseller_business_name'] ?? 'N/A'); ?></strong>
                                        <br>
                                        <small style="color: #6b7280;">
                                            <?php echo htmlspecialchars($request['reseller_email'] ?? ''); ?>
                                        </small>
                                        <?php if ($request['reseller_phone']): ?>
                                            <br>
                                            <small style="color: #6b7280;">
                                                <?php echo htmlspecialchars($request['reseller_phone']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($request['router_name'] ?? 'N/A'); ?></strong>
                                        <br>
                                        <small style="color: #6b7280;">
                                            IP: <?php echo htmlspecialchars($request['router_ip'] ?? 'N/A'); ?>
                                        </small>
                                        <?php if ($request['router_location']): ?>
                                            <br>
                                            <small style="color: #6b7280;">
                                                <?php echo htmlspecialchars($request['router_location']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $request['request_status']; ?>">
                                        <?php 
                                        switch($request['request_status']) {
                                            case 'ordered': echo 'Pending'; break;
                                            case 'approved': echo 'Approved'; break;
                                            case 'rejected': echo 'Rejected'; break;
                                            default: echo ucfirst($request['request_status']);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                        <br>
                                        <small style="color: #6b7280;">
                                            <?php echo date('g:i A', strtotime($request['created_at'])); ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($request['request_status'] === 'ordered'): ?>
                                            <button class="btn btn-approve" 
                                                    onclick="showApprovalModal(<?php echo $request['id']; ?>)">
                                                <i class="fas fa-check"></i>
                                                Approve
                                            </button>
                                            <button class="btn btn-reject" 
                                                    onclick="showRejectionModal(<?php echo $request['id']; ?>)">
                                                <i class="fas fa-times"></i>
                                                Reject
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary" 
                                                    onclick="showDetailsModal(<?php echo $request['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                                View Details
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No requests found</h3>
                    <p>
                        <?php 
                        if ($filter === 'all') {
                            echo "No remote access requests have been submitted yet.";
                        } else {
                            echo "No " . ($filter === 'ordered' ? 'pending' : $filter) . " requests found.";
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Approve Remote Access Request</h3>
                <button class="modal-close" onclick="closeModal('approvalModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="approvalForm">
                    <input type="hidden" id="approvalRequestId" name="request_id">

                    <div class="form-group">
                        <label class="form-label">Remote Access Username *</label>
                        <input type="text" class="form-input" id="remoteUsername" name="username" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Remote Access Password *</label>
                        <input type="password" class="form-input" id="remotePassword" name="password" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">DNS Name (Optional)</label>
                        <input type="text" class="form-input" id="dnsName" name="dns_name"
                               placeholder="e.g., router1.company.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Remote Port</label>
                        <input type="number" class="form-input" id="remotePort" name="port" value="8291" min="1" max="65535">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Admin Comments</label>
                        <textarea class="form-textarea" id="approvalComments" name="admin_comments"
                                  placeholder="Optional notes about this approval..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('approvalModal')">Cancel</button>
                <button class="btn btn-approve" onclick="processApproval()">
                    <i class="fas fa-check"></i>
                    Approve Request
                </button>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Remote Access Request</h3>
                <button class="modal-close" onclick="closeModal('rejectionModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="rejectionForm">
                    <input type="hidden" id="rejectionRequestId" name="request_id">

                    <div class="form-group">
                        <label class="form-label">Rejection Reason *</label>
                        <textarea class="form-textarea" id="rejectionReason" name="admin_comments" required
                                  placeholder="Please provide a reason for rejecting this request..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('rejectionModal')">Cancel</button>
                <button class="btn btn-reject" onclick="processRejection()">
                    <i class="fas fa-times"></i>
                    Reject Request
                </button>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Request Details</h3>
                <button class="modal-close" onclick="closeModal('detailsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="detailsContent">
                    <!-- Details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('detailsModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <div id="alertContainer" style="position: fixed; top: 20px; right: 20px; z-index: 1001;"></div>

    <script>
        // Filter requests by status
        function filterRequests(status) {
            const url = new URL(window.location);
            if (status === 'all') {
                url.searchParams.delete('filter');
            } else {
                url.searchParams.set('filter', status);
            }
            window.location.href = url.toString();
        }

        // Show approval modal
        function showApprovalModal(requestId) {
            document.getElementById('approvalRequestId').value = requestId;
            document.getElementById('approvalModal').classList.add('show');

            // Clear form
            document.getElementById('remoteUsername').value = '';
            document.getElementById('remotePassword').value = '';
            document.getElementById('dnsName').value = '';
            document.getElementById('remotePort').value = '8291';
            document.getElementById('approvalComments').value = '';
        }

        // Show rejection modal
        function showRejectionModal(requestId) {
            document.getElementById('rejectionRequestId').value = requestId;
            document.getElementById('rejectionModal').classList.add('show');

            // Clear form
            document.getElementById('rejectionReason').value = '';
        }

        // Show details modal
        function showDetailsModal(requestId) {
            // For now, just show a placeholder
            document.getElementById('detailsContent').innerHTML = '<p>Loading request details...</p>';
            document.getElementById('detailsModal').classList.add('show');

            // In a real implementation, you would fetch details via AJAX
            // For now, we'll just show basic info
            setTimeout(() => {
                document.getElementById('detailsContent').innerHTML =
                    '<p>Request ID: #' + requestId + '</p>' +
                    '<p>This feature can be enhanced to show full request details.</p>';
            }, 500);
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Process approval
        async function processApproval() {
            const requestId = document.getElementById('approvalRequestId').value;
            const username = document.getElementById('remoteUsername').value.trim();
            const password = document.getElementById('remotePassword').value.trim();
            const dnsName = document.getElementById('dnsName').value.trim();
            const port = document.getElementById('remotePort').value;
            const comments = document.getElementById('approvalComments').value.trim();

            if (!username || !password) {
                showAlert('Username and password are required', 'error');
                return;
            }

            try {
                const response = await fetch('process_approval.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'approve',
                        request_id: parseInt(requestId),
                        credentials: {
                            username: username,
                            password: password,
                            dns_name: dnsName || null,
                            port: parseInt(port)
                        },
                        admin_comments: comments || 'Approved by super admin'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message, 'success');
                    closeModal('approvalModal');
                    // Reload page after short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred while processing the request', 'error');
                console.error('Error:', error);
            }
        }

        // Process rejection
        async function processRejection() {
            const requestId = document.getElementById('rejectionRequestId').value;
            const reason = document.getElementById('rejectionReason').value.trim();

            if (!reason) {
                showAlert('Rejection reason is required', 'error');
                return;
            }

            try {
                const response = await fetch('process_approval.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'reject',
                        request_id: parseInt(requestId),
                        admin_comments: reason
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message, 'success');
                    closeModal('rejectionModal');
                    // Reload page after short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('An error occurred while processing the request', 'error');
                console.error('Error:', error);
            }
        }

        // Show alert message
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;

            alertContainer.appendChild(alertDiv);

            // Remove alert after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    openModal.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>
