<?php
// Include session check
require_once 'session_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - Qtro ISP System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .api-docs-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .doc-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .doc-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(90deg, var(--accent-blue), var(--accent-purple));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .doc-subtitle {
            color: var(--text-secondary);
            font-size: 1.2rem;
        }
        
        .doc-section {
            background-color: var(--bg-secondary);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }
        
        .doc-section h2 {
            color: var(--accent-blue);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .doc-section h3 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }
        
        .doc-section p {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .code-block {
            background-color: var(--bg-primary);
            border: 1px solid var(--bg-accent);
            border-radius: 0.5rem;
            padding: 1.5rem;
            overflow-x: auto;
            margin: 1rem 0;
        }
        
        .code-block code {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: var(--text-primary);
            white-space: pre;
        }
        
        .endpoint-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }
        
        .method-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .method-post {
            background-color: rgba(34, 197, 94, 0.2);
            color: var(--accent-green);
        }
        
        .endpoint-path {
            font-family: 'Courier New', monospace;
            background-color: var(--bg-primary);
            padding: 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid var(--bg-accent);
        }
        
        .param-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        
        .param-table th,
        .param-table td {
            text-align: left;
            padding: 0.75rem;
            border-bottom: 1px solid var(--bg-accent);
        }
        
        .param-table th {
            background-color: var(--bg-primary);
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .param-required {
            color: var(--accent-red);
            font-weight: 600;
        }
        
        .param-optional {
            color: var(--text-secondary);
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background-color: var(--accent-blue);
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: background-color 0.2s ease;
            margin-bottom: 2rem;
        }
        
        .back-btn:hover {
            background-color: #2563eb;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <div class="main-content" id="main-content">
        <div class="api-docs-container">
            <a href="routers.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
            
            <div class="doc-header">
                <h1 class="doc-title">API Documentation</h1>
                <p class="doc-subtitle">Batch Voucher API for WiFi Billing System</p>
            </div>
            
            <div class="doc-section">
                <h2><i class="fas fa-info-circle"></i> Overview</h2>
                <p>The Batch Voucher API allows you to create up to 100 vouchers per request programmatically. This is ideal for integrating with external systems like hotspot managers or custom voucher generation tools.</p>
            </div>
            
            <div class="doc-section">
                <h2><i class="fas fa-key"></i> Authentication</h2>
                <p>All API requests require authentication using your API key. Include your API key in the Authorization header:</p>
                <div class="code-block">
                    <code>Authorization: Bearer YOUR_API_KEY</code>
                </div>
                <p>You can generate your API key from the Router Management Dashboard.</p>
            </div>
            
            <div class="doc-section">
                <h2><i class="fas fa-server"></i> Base URL</h2>
                <div class="code-block">
                    <code><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>/api/</code>
                </div>
            </div>
            
            <div class="doc-section">
                <h2><i class="fas fa-ticket-alt"></i> Create Batch Vouchers</h2>
                <p>Create multiple vouchers in a single request.</p>
                
                <div class="endpoint-badge">
                    <span class="method-badge method-post">POST</span>
                    <code class="endpoint-path">/api/vouchers</code>
                </div>
                
                <h3>Request Headers</h3>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>Header</th>
                            <th>Value</th>
                            <th>Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Content-Type</td>
                            <td>application/json</td>
                            <td><span class="param-required">Yes</span></td>
                        </tr>
                        <tr>
                            <td>Authorization</td>
                            <td>Bearer YOUR_API_KEY</td>
                            <td><span class="param-required">Yes</span></td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Request Body Parameters</h3>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>router_id</td>
                            <td>string</td>
                            <td><span class="param-required">Yes</span></td>
                            <td>The name/ID of your router as configured in the system</td>
                        </tr>
                        <tr>
                            <td>vouchers</td>
                            <td>array</td>
                            <td><span class="param-required">Yes</span></td>
                            <td>Array of voucher objects (max 100)</td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Voucher Object Parameters</h3>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>voucher_code</td>
                            <td>string</td>
                            <td><span class="param-required">Yes</span></td>
                            <td>Unique voucher code</td>
                        </tr>
                        <tr>
                            <td>validity</td>
                            <td>string</td>
                            <td><span class="param-required">Yes</span></td>
                            <td>Voucher validity (1h, 2h, 1d, 7d, 30d, etc.)</td>
                        </tr>
                        <tr>
                            <td>profile</td>
                            <td>string</td>
                            <td><span class="param-optional">No</span></td>
                            <td>Speed profile (e.g., "2Mbps")</td>
                        </tr>
                        <tr>
                            <td>created_at</td>
                            <td>string</td>
                            <td><span class="param-optional">No</span></td>
                            <td>Creation timestamp (YYYY-MM-DD HH:MM:SS)</td>
                        </tr>
                        <tr>
                            <td>comment</td>
                            <td>string</td>
                            <td><span class="param-optional">No</span></td>
                            <td>Optional comment or batch identifier</td>
                        </tr>
                        <tr>
                            <td>metadata</td>
                            <td>object</td>
                            <td><span class="param-optional">No</span></td>
                            <td>Additional metadata (password, limits, etc.)</td>
                        </tr>
                    </tbody>
                </table>
                
                <h3>Example Request</h3>
                <div class="code-block">
                    <code>{
    "router_id": "Main_Office_Router",
    "vouchers": [
        {
            "voucher_code": "ABC123",
            "profile": "2Mbps",
            "validity": "1d",
            "created_at": "2025-08-25 14:30:00",
            "comment": "batch-001",
            "metadata": {
                "mikhmon_version": "3.0",
                "generated_by": "admin",
                "password": "ABC123",
                "time_limit": "1d",
                "data_limit": "1073741824",
                "user_mode": "vc"
            }
        },
        {
            "voucher_code": "DEF456",
            "profile": "5Mbps",
            "validity": "3h",
            "comment": "batch-001"
        }
    ]
}</code>
                </div>
                
                <h3>Success Response (200 OK)</h3>
                <div class="code-block">
                    <code>{
    "success": true,
    "message": "Batch processed",
    "data": {
        "total": 2,
        "stored": 2,
        "failed": 0,
        "results": [
            {"voucher_code": "ABC123", "status": "stored"},
            {"voucher_code": "DEF456", "status": "stored"}
        ]
    }
}</code>
                </div>
                
                <h3>Error Response</h3>
                <div class="code-block">
                    <code>{
    "success": false,
    "message": "Error description"
}</code>
                </div>
            </div>
            
            <div class="doc-section">
                <h2><i class="fas fa-exclamation-triangle"></i> Error Codes</h2>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>400</td>
                            <td>Bad Request - Invalid JSON or missing required fields</td>
                        </tr>
                        <tr>
                            <td>401</td>
                            <td>Unauthorized - Invalid or missing API key</td>
                        </tr>
                        <tr>
                            <td>403</td>
                            <td>Forbidden - Router doesn't belong to your account</td>
                        </tr>
                        <tr>
                            <td>413</td>
                            <td>Payload Too Large - More than 100 vouchers in request</td>
                        </tr>
                        <tr>
                            <td>500</td>
                            <td>Internal Server Error - Database or server error</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="doc-section">
                <h2><i class="fas fa-tachometer-alt"></i> Rate Limits</h2>
                <p>• Maximum 100 vouchers per request</p>
                <p>• No rate limiting on number of requests</p>
                <p>• All requests are logged for monitoring</p>
            </div>
            
            <div class="doc-section">
                <h2><i class="fas fa-question-circle"></i> Support</h2>
                <p>If you need help with the API integration, please contact support:</p>
                <p><i class="fas fa-phone"></i> Phone: 0750059353</p>
                <p><i class="fas fa-envelope"></i> Email: support@qtro-isp.com</p>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>
