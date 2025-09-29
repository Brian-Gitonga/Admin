<?php
// Start session
session_start();

// Include database connection
require_once 'portal_connection.php';

// Check if user is logged in, redirect if not
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get reseller ID from session
$reseller_id = $_SESSION['user_id'];

// Set default values for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $perPage;

// Get search term if provided
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get filter for payment gateway if provided
$gateway = isset($_GET['gateway']) ? $_GET['gateway'] : 'all';

// Check if payment_transactions table exists
$paymentTableExists = false;
$checkPaymentTable = $conn->query("SHOW TABLES LIKE 'payment_transactions'");
if ($checkPaymentTable && $checkPaymentTable->num_rows > 0) {
    $paymentTableExists = true;
}

// Check if mpesa_transactions table exists
$mpesaTableExists = false;
$checkMpesaTable = $conn->query("SHOW TABLES LIKE 'mpesa_transactions'");
if ($checkMpesaTable && $checkMpesaTable->num_rows > 0) {
    $mpesaTableExists = true;
}

// Initialize query parts
$query = "";
$countQuery = "";
$queryParams = [];
$countQueryParams = [];
$paramTypes = "";
$countParamTypes = "";

// Add sorting if provided
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Validate sort column to prevent SQL injection
$allowedColumns = ['phone_number', 'receipt', 'amount', 'status', 'created_at', 'gateway'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'created_at';
}

// Validate sort order
if ($sortOrder != 'ASC' && $sortOrder != 'DESC') {
    $sortOrder = 'DESC';
}

// Build the query based on available tables and filters
if ($mpesaTableExists && $paymentTableExists && $gateway == 'all') {
    // Query for both tables with UNION
    $query = "(SELECT 
                id,
                package_name,
                phone_number,
                mpesa_receipt as receipt,
                amount,
                status,
                created_at,
                result_code,
                'mpesa' as gateway
              FROM mpesa_transactions 
              WHERE reseller_id = ?";
    
    $countQuery = "(SELECT COUNT(*) FROM mpesa_transactions WHERE reseller_id = ?";
    
    $paramTypes .= "i";
    $countParamTypes .= "i";
    $queryParams[] = $reseller_id;
    $countQueryParams[] = $reseller_id;
    
    // Add search filter for mpesa if provided
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $query .= " AND (phone_number LIKE ? OR mpesa_receipt LIKE ? OR package_name LIKE ?)";
        $countQuery .= " AND (phone_number LIKE ? OR mpesa_receipt LIKE ? OR package_name LIKE ?)";
        $paramTypes .= "sss";
        $countParamTypes .= "sss";
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
    }
    
    $query .= ")
              UNION
              (SELECT 
                id,
                package_name,
                phone_number,
                reference as receipt,
                amount,
                status,
                created_at,
                NULL as result_code,
                'paystack' as gateway
              FROM payment_transactions 
              WHERE reseller_id = ? AND payment_gateway = 'paystack'";
    
    $countQuery .= ")
                  + (SELECT COUNT(*) FROM payment_transactions 
                     WHERE reseller_id = ? AND payment_gateway = 'paystack'";
    
    $paramTypes .= "i";
    $countParamTypes .= "i";
    $queryParams[] = $reseller_id;
    $countQueryParams[] = $reseller_id;
    
    // Add search filter for paystack if provided
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $query .= " AND (phone_number LIKE ? OR reference LIKE ? OR package_name LIKE ? OR email LIKE ?)";
        $countQuery .= " AND (phone_number LIKE ? OR reference LIKE ? OR package_name LIKE ? OR email LIKE ?)";
        $paramTypes .= "ssss";
        $countParamTypes .= "ssss";
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
    }
    
    $query .= ")";
    $countQuery .= ")";
    
} elseif ($mpesaTableExists && ($gateway == 'all' || $gateway == 'mpesa')) {
    // Only M-Pesa transactions
    $query = "SELECT 
                id,
                package_name,
                phone_number,
                mpesa_receipt as receipt,
                amount,
                status,
                created_at,
                result_code,
                'mpesa' as gateway
              FROM mpesa_transactions 
              WHERE reseller_id = ?";
    
    $countQuery = "SELECT COUNT(*) AS total FROM mpesa_transactions WHERE reseller_id = ?";
    
    $paramTypes .= "i";
    $countParamTypes .= "i";
    $queryParams[] = $reseller_id;
    $countQueryParams[] = $reseller_id;
    
    // Add search filter if provided
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $query .= " AND (phone_number LIKE ? OR mpesa_receipt LIKE ? OR package_name LIKE ?)";
        $countQuery .= " AND (phone_number LIKE ? OR mpesa_receipt LIKE ? OR package_name LIKE ?)";
        $paramTypes .= "sss";
        $countParamTypes .= "sss";
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
    }
    
} elseif ($paymentTableExists && ($gateway == 'all' || $gateway == 'paystack')) {
    // Only Paystack transactions
    $query = "SELECT 
                id,
                package_name,
                phone_number,
                reference as receipt,
                amount,
                status,
                created_at,
                NULL as result_code,
                'paystack' as gateway
              FROM payment_transactions 
              WHERE reseller_id = ? AND payment_gateway = 'paystack'";
    
    $countQuery = "SELECT COUNT(*) AS total FROM payment_transactions WHERE reseller_id = ? AND payment_gateway = 'paystack'";
    
    $paramTypes .= "i";
    $countParamTypes .= "i";
    $queryParams[] = $reseller_id;
    $countQueryParams[] = $reseller_id;
    
    // Add search filter if provided
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $query .= " AND (phone_number LIKE ? OR reference LIKE ? OR package_name LIKE ? OR email LIKE ?)";
        $countQuery .= " AND (phone_number LIKE ? OR reference LIKE ? OR package_name LIKE ? OR email LIKE ?)";
        $paramTypes .= "ssss";
        $countParamTypes .= "ssss";
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
        $countQueryParams[] = $searchTerm;
    }
} else {
    // Fallback to empty query if no tables exist
    $query = "SELECT 1 WHERE 0";
    $countQuery = "SELECT 0 AS total";
}

// Add order and limit
$query .= " ORDER BY $sortColumn $sortOrder LIMIT $perPage OFFSET $offset";

// Get total count for pagination
$totalRows = 0;
if (!empty($countQuery)) {
    $countStmt = $conn->prepare($countQuery);
    if ($countStmt) {
        if (!empty($countParamTypes) && !empty($countQueryParams)) {
            $countStmt->bind_param($countParamTypes, ...$countQueryParams);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalRows = $countResult->fetch_assoc()['total'];
    }
}
$totalPages = ceil($totalRows / $perPage);

// Get data
$transactions = [];
if (!empty($query)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($paramTypes) && !empty($queryParams)) {
            $stmt->bind_param($paramTypes, ...$queryParams);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qtro ISP - Transactions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="other-css/transations.css">
    <style>
        .gateway-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 5px;
        }
        
        .gateway-badge.mpesa {
            background-color: #c1e7ff;
            color: #0078d4;
        }
        
        .gateway-badge.paystack {
            background-color: #d4f7dc;
            color: #0a8724;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .filter-btn {
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            background: white;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .filter-btn:hover {
            border-color: #cbd5e0;
        }
        
        .filter-btn.active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <div class="page-header">
            <div class="page-title-container">
                <h1 class="page-title">
                    Payments
                    <i class="fas fa-info-circle info-icon" title="View and manage all payment transactions"></i>
                </h1>
            </div>
        </div>
        
        <div class="transactions-table-container">
            <div class="transactions-table-header">
                <div class="search-filter">
                    <i class="fas fa-search"></i>
                    <form action="" method="GET" id="search-form">
                        <input type="text" name="search" placeholder="Search by phone, receipt or package" value="<?php echo htmlspecialchars($search); ?>">
                        <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                        <input type="hidden" name="gateway" value="<?php echo $gateway; ?>" id="gateway-input">
                    </form>
                </div>
            </div>
            
            <!-- Gateway filter buttons -->
            <div class="filter-buttons">
                <button class="filter-btn <?php echo $gateway == 'all' ? 'active' : ''; ?>" data-gateway="all">All Payments</button>
                <?php if ($mpesaTableExists): ?>
                <button class="filter-btn <?php echo $gateway == 'mpesa' ? 'active' : ''; ?>" data-gateway="mpesa">M-Pesa</button>
                <?php endif; ?>
                <?php if ($paymentTableExists): ?>
                <button class="filter-btn <?php echo $gateway == 'paystack' ? 'active' : ''; ?>" data-gateway="paystack">Paystack</button>
                <?php endif; ?>
            </div>
            
            <div class="table-responsive">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th class="checkbox-cell">
                                <div class="custom-checkbox" id="select-all"></div>
                            </th>
                            <th>Package <a href="?sort=package_name&order=<?php echo $sortColumn == 'package_name' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&gateway=<?php echo $gateway; ?>"><i class="fas fa-sort"></i></a></th>
                            <th>Phone <a href="?sort=phone_number&order=<?php echo $sortColumn == 'phone_number' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&gateway=<?php echo $gateway; ?>"><i class="fas fa-sort"></i></a></th>
                            <th>Receipt No. <a href="?sort=receipt&order=<?php echo $sortColumn == 'receipt' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&gateway=<?php echo $gateway; ?>"><i class="fas fa-sort"></i></a></th>
                            <th>Amount <a href="?sort=amount&order=<?php echo $sortColumn == 'amount' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&gateway=<?php echo $gateway; ?>"><i class="fas fa-sort"></i></a></th>
                            <th>Status <a href="?sort=status&order=<?php echo $sortColumn == 'status' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&gateway=<?php echo $gateway; ?>"><i class="fas fa-sort"></i></a></th>
                            <th>Date <a href="?sort=created_at&order=<?php echo $sortColumn == 'created_at' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&gateway=<?php echo $gateway; ?>"><i class="fas fa-sort"></i></a></th>
                            <th>Gateway <a href="?sort=gateway&order=<?php echo $sortColumn == 'gateway' && $sortOrder == 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&gateway=<?php echo $gateway; ?>"><i class="fas fa-sort"></i></a></th>
                            <th>Result</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <div class="custom-checkbox"></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['package_name']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['receipt'] ?: 'N/A'); ?></td>
                                    <td>Ksh <?php echo number_format($transaction['amount'], 2); ?></td>
                                    <td>
                                        <?php if ($transaction['status'] == 'completed'): ?>
                                            <span class="status-badge status-success">Completed</span>
                                        <?php elseif ($transaction['status'] == 'pending'): ?>
                                            <span class="status-badge status-processing">Pending</span>
                                        <?php else: ?>
                                            <span class="status-badge status-danger">Failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?></td>
                                    <td>
                                        <?php if ($transaction['gateway'] == 'mpesa'): ?>
                                            <span class="gateway-badge mpesa">M-Pesa</span>
                                        <?php elseif ($transaction['gateway'] == 'paystack'): ?>
                                            <span class="gateway-badge paystack">Paystack</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($transaction['gateway'] == 'mpesa' && !empty($transaction['result_code'])): ?>
                                            <?php if ($transaction['result_code'] == 0): ?>
                                                <span class="status-badge status-success">Success</span>
                                            <?php else: ?>
                                                <span class="status-badge status-danger">Error <?php echo $transaction['result_code']; ?></span>
                                            <?php endif; ?>
                                        <?php elseif ($transaction['status'] == 'completed'): ?>
                                            <span class="status-badge status-success">Success</span>
                                        <?php elseif ($transaction['status'] == 'pending'): ?>
                                            <span class="status-badge status-processing">Processing</span>
                                        <?php else: ?>
                                            <span class="status-badge status-danger">Failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="action-menu" data-id="<?php echo $transaction['id']; ?>" data-gateway="<?php echo $transaction['gateway']; ?>">
                                            <i class="fas fa-ellipsis-v"></i>
                                            <div class="action-dropdown">
                                                <a href="transaction_details.php?id=<?php echo $transaction['id']; ?>&gateway=<?php echo $transaction['gateway']; ?>" class="action-item">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                                <?php if ($transaction['status'] == 'pending'): ?>
                                                    <?php if ($transaction['gateway'] == 'mpesa'): ?>
                                                    <a href="transations_script/check_transaction.php?id=<?php echo $transaction['id']; ?>&gateway=mpesa" class="action-item">
                                                        <i class="fas fa-sync"></i> Check Status
                                                    </a>
                                                    <?php elseif ($transaction['gateway'] == 'paystack'): ?>
                                                    <a href="transations_script/check_paystack_transaction.php?id=<?php echo $transaction['id']; ?>&gateway=paystack" class="action-item">
                                                        <i class="fas fa-sync"></i> Check Status
                                                    </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="no-records">No transactions found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="table-footer">
                <div class="pagination">
                    <?php if ($totalPages > 1): ?>
                        <?php if ($page > 1): ?>
                            <a href="?page=1&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=<?php echo $sortColumn; ?>&order=<?php echo $sortOrder; ?>&gateway=<?php echo $gateway; ?>" class="page-link">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=<?php echo $sortColumn; ?>&order=<?php echo $sortOrder; ?>&gateway=<?php echo $gateway; ?>" class="page-link">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, min($page - 2, $totalPages - 4));
                        $endPage = min($totalPages, max($page + 2, 5));
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=<?php echo $sortColumn; ?>&order=<?php echo $sortOrder; ?>&gateway=<?php echo $gateway; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=<?php echo $sortColumn; ?>&order=<?php echo $sortOrder; ?>&gateway=<?php echo $gateway; ?>" class="page-link">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>&sort=<?php echo $sortColumn; ?>&order=<?php echo $sortOrder; ?>&gateway=<?php echo $gateway; ?>" class="page-link">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="results-info">Showing <?php echo count($transactions); ?> of <?php echo $totalRows; ?> results</div>
                
                <div class="per-page">
                    <span>Per page</span>
                    <select id="per-page-select">
                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="page-footer">
            <div class="footer-links">
                <a href="#" class="footer-link">Whatsapp Channel</a>
                <a href="#" class="footer-link">Privacy & Terms</a>
            </div>
            <div class="copyright">© 2025 Qtro ISP. All Rights Reserved.</div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Search functionality
        const searchInput = document.querySelector('input[name="search"]');
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('search-form').submit();
            }
        });
        
        // Gateway filter buttons
        const filterButtons = document.querySelectorAll('.filter-btn');
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                const gateway = this.getAttribute('data-gateway');
                document.getElementById('gateway-input').value = gateway;
                document.getElementById('search-form').submit();
            });
        });
        
        // Per page functionality
        const perPageSelect = document.getElementById('per-page-select');
        perPageSelect.addEventListener('change', function() {
            const perPage = this.value;
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('per_page', perPage);
            currentUrl.searchParams.set('page', '1'); // Reset to first page
            window.location.href = currentUrl.toString();
        });
        
        // Action menu functionality
        const actionMenus = document.querySelectorAll('.action-menu');
        actionMenus.forEach(menu => {
            menu.addEventListener('click', function() {
                // Close all other menus
                actionMenus.forEach(m => {
                    if (m !== menu) {
                        m.querySelector('.action-dropdown')?.classList.remove('active');
                    }
                });
                
                // Toggle this menu
                const dropdown = this.querySelector('.action-dropdown');
                if (dropdown) {
                    dropdown.classList.toggle('active');
                }
            });
        });
        
        // Close action menus when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.action-menu')) {
                actionMenus.forEach(menu => {
                    menu.querySelector('.action-dropdown')?.classList.remove('active');
                });
            }
        });
        
        // Select all checkbox
        const selectAllCheckbox = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.checkbox-cell .custom-checkbox:not(#select-all)');
        
        selectAllCheckbox.addEventListener('click', function() {
            this.classList.toggle('checked');
            const isChecked = this.classList.contains('checked');
            checkboxes.forEach(checkbox => {
                if (isChecked) {
                    checkbox.classList.add('checked');
                } else {
                    checkbox.classList.remove('checked');
                }
            });
        });
        
        // Individual checkboxes
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('click', function() {
                this.classList.toggle('checked');
                
                // Check if all are checked
                const allChecked = [...checkboxes].every(cb => cb.classList.contains('checked'));
                if (allChecked) {
                    selectAllCheckbox.classList.add('checked');
                } else {
                    selectAllCheckbox.classList.remove('checked');
                }
            });
        });
    });
    </script>
</body>
</html>








