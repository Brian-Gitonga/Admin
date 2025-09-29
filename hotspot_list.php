<?php
// Include session check
require_once 'session_check.php';

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Page title
$page_title = "Hotspot Portal List";

// Include necessary files
require_once 'connection_dp.php';
require_once 'functions.php';

// Function to get all active hotspots for a reseller
function getActiveHotspots($conn, $reseller_id) {
    $stmt = $conn->prepare("SELECT id, name, router_ip, status, last_checked FROM hotspots WHERE reseller_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->bind_param("i", $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result;
}

// Get business name for current user
$businessName = '';
try {
    if (isset($conn) && $conn) {
        // First check if the column is named business or business_name
        $columnCheckQuery = "SHOW COLUMNS FROM resellers LIKE 'business_name'";
        $columnResult = $conn->query($columnCheckQuery);
        
        if ($columnResult->num_rows > 0) {
            // Using business_name column
            $query = "SELECT business_name FROM resellers WHERE id = ?";
        } else {
            // Using business column
            $query = "SELECT business FROM resellers WHERE id = ?";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $businessName = isset($row['business_name']) ? $row['business_name'] : $row['business'];
        }
        $stmt->close();
    }
} catch (Exception $e) {
    // If error, use empty business name
    error_log("Error getting business name: " . $e->getMessage());
}

// Get all active hotspots for this reseller
$hotspots = getActiveHotspots($conn, $user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Include CSS files -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="other-css/hotspot_list.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Include navigation -->
    <?php include 'nav.php'; ?>
    
    <!-- Main content -->
    <main class="main-content">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-wifi"></i> 
                    Hotspot Portals
                </h1>
                <p class="page-subtitle">Select a hotspot to view its portal</p>
            </div>
            
            <!-- Hotspots list -->
            <?php if ($hotspots && $hotspots->num_rows > 0) : ?>
                <div class="hotspots-grid">
                    <?php while ($hotspot = $hotspots->fetch_assoc()) : ?>
                        <div class="hotspot-card" onclick="window.open('portal.php?router_id=<?php echo $hotspot['id']; ?>&business=<?php echo urlencode($businessName); ?>', '_blank')">
                            <div class="hotspot-status <?php echo $hotspot['status'] == 'online' ? 'status-online' : 'status-offline'; ?>">
                                <?php echo ucfirst($hotspot['status']); ?>
                            </div>
                            <h3 class="hotspot-name">
                                <i class="fas fa-router hotspot-icon"></i>
                                <?php echo htmlspecialchars($hotspot['name']); ?>
                            </h3>
                            <div class="hotspot-ip">
                                <i class="fas fa-network-wired hotspot-icon"></i>
                                <?php echo htmlspecialchars($hotspot['router_ip']); ?>
                            </div>
                            <?php if ($hotspot['last_checked']) : ?>
                                <div class="hotspot-last-checked">
                                    <i class="fas fa-clock hotspot-icon"></i>
                                    Last checked: <?php echo date('M j, Y g:i A', strtotime($hotspot['last_checked'])); ?>
                                </div>
                            <?php endif; ?>
                            <button class="portal-btn">
                                <i class="fas fa-external-link-alt"></i> Open Portal
                            </button>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else : ?>
                <div class="empty-state">
                    <i class="fas fa-router"></i>
                    <h3>No Hotspots Found</h3>
                    <p>You haven't added any MikroTik routers yet. Add a router to create a hotspot portal.</p>
                    <a href="linkrouter.php" class="add-router-btn">
                        <i class="fas fa-plus"></i> Add Router
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Include footer -->
    <?php include 'footer.php'; ?>
    
    <!-- Include your JS files here -->
    <script src="script.js"></script>
</body>
</html> 