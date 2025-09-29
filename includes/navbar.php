<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'reseller.php' ? 'active' : ''; ?>" href="reseller.php">
        <i class="fas fa-users"></i> Reseller Management
    </a>
</li>

<li class="nav-item">
    <a class="nav-link <?php echo $current_page == 'subscription_requests.php' ? 'active' : ''; ?>" href="reseller.php#subscription-requests">
        <i class="fas fa-bell"></i> Subscription Requests
        <?php
        // Count pending subscription requests
        $pendingRequestsQuery = "SELECT COUNT(*) as count FROM subscription_requests WHERE status = 'pending'";
        $pendingRequestsResult = mysqli_query($mysqli, $pendingRequestsQuery);
        $pendingRequestsCount = mysqli_fetch_assoc($pendingRequestsResult)['count'];
        
        if ($pendingRequestsCount > 0) {
            echo '<span class="badge rounded-pill bg-danger">' . $pendingRequestsCount . '</span>';
        }
        ?>
    </a>
</li> 