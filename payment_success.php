<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if we have payment success data
if (!isset($_SESSION['payment_success']) || !$_SESSION['payment_success']) {
    // Redirect to portal if no success data
    header("Location: portal.php");
    exit;
}

// Get voucher details from session
$voucherCode = $_SESSION['voucher_code'] ?? '';
$voucherUsername = $_SESSION['voucher_username'] ?? '';
$voucherPassword = $_SESSION['voucher_password'] ?? '';
$packageName = $_SESSION['package_name'] ?? '';
$customerPhone = $_SESSION['customer_phone'] ?? '';
$smsSent = $_SESSION['sms_sent'] ?? false;
$smsError = $_SESSION['sms_error'] ?? '';

// Clear the session data after retrieving it
unset($_SESSION['payment_success']);
unset($_SESSION['voucher_code']);
unset($_SESSION['voucher_username']);
unset($_SESSION['voucher_password']);
unset($_SESSION['package_name']);
unset($_SESSION['customer_phone']);
unset($_SESSION['sms_sent']);
unset($_SESSION['sms_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - WiFi Voucher</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .success-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #4CAF50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: checkmark 0.6s ease-in-out;
        }

        .success-icon i {
            color: white;
            font-size: 40px;
        }

        @keyframes checkmark {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }

        .success-title {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .success-message {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .voucher-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            border: 2px dashed #4CAF50;
        }

        .voucher-title {
            color: #333;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .voucher-code {
            background: #4CAF50;
            color: white;
            font-size: 24px;
            font-weight: bold;
            padding: 15px 25px;
            border-radius: 10px;
            margin: 15px 0;
            letter-spacing: 2px;
            font-family: 'Courier New', monospace;
            position: relative;
            display: inline-block;
        }

        .copy-button {
            background: #2196F3;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .copy-button:hover {
            background: #1976D2;
            transform: translateY(-2px);
        }

        .copy-button.copied {
            background: #4CAF50;
            animation: pulse 0.6s ease-in-out;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .credentials-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        .credentials-title {
            color: #856404;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .credential-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .credential-item:last-child {
            border-bottom: none;
        }

        .credential-label {
            font-weight: 600;
            color: #333;
        }

        .credential-value {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
        }

        .sms-status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sms-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .sms-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .action-buttons {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        @media (max-width: 600px) {
            .success-container {
                padding: 30px 20px;
                margin: 10px;
            }

            .voucher-code {
                font-size: 20px;
                padding: 12px 20px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>

        <h1 class="success-title">Payment Successful!</h1>
        <p class="success-message">
            Your payment has been processed successfully. <?php echo $smsSent ? 'Your voucher has been sent to your phone via SMS.' : 'Please save your voucher details below.'; ?>
        </p>

        <?php if ($smsSent): ?>
            <div class="sms-status sms-success">
                <i class="fas fa-sms"></i>
                <span>SMS sent successfully to <?php echo htmlspecialchars($customerPhone); ?></span>
            </div>
        <?php elseif (!empty($smsError)): ?>
            <div class="sms-status sms-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span>SMS delivery failed: <?php echo htmlspecialchars($smsError); ?></span>
            </div>
        <?php endif; ?>

        <div class="voucher-section">
            <h2 class="voucher-title">
                <i class="fas fa-ticket-alt"></i>
                Your WiFi Voucher
            </h2>
            
            <div class="voucher-code" id="voucherCode">
                <?php echo htmlspecialchars($voucherCode); ?>
            </div>
            
            <button class="copy-button" onclick="copyVoucherCode()">
                <i class="fas fa-copy"></i>
                <span id="copyButtonText">Copy Voucher Code</span>
            </button>

            <?php if ($packageName): ?>
                <p style="margin-top: 15px; color: #666; font-size: 14px;">
                    Package: <strong><?php echo htmlspecialchars($packageName); ?></strong>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($voucherUsername && $voucherPassword): ?>
            <div class="credentials-section">
                <h3 class="credentials-title">Login Credentials</h3>
                <div class="credential-item">
                    <span class="credential-label">Username:</span>
                    <span class="credential-value"><?php echo htmlspecialchars($voucherUsername); ?></span>
                </div>
                <div class="credential-item">
                    <span class="credential-label">Password:</span>
                    <span class="credential-value"><?php echo htmlspecialchars($voucherPassword); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="portal.php" class="btn btn-primary">
                <i class="fas fa-wifi"></i>
                Back to Portal
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i>
                Print Voucher
            </button>
        </div>
    </div>

    <script>
        function copyVoucherCode() {
            const voucherCode = document.getElementById('voucherCode').textContent.trim();
            const copyButton = document.querySelector('.copy-button');
            const copyButtonText = document.getElementById('copyButtonText');
            
            // Use the modern Clipboard API if available
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(voucherCode).then(() => {
                    showCopySuccess(copyButton, copyButtonText);
                }).catch(err => {
                    // Fallback to older method
                    fallbackCopyTextToClipboard(voucherCode, copyButton, copyButtonText);
                });
            } else {
                // Fallback for older browsers
                fallbackCopyTextToClipboard(voucherCode, copyButton, copyButtonText);
            }
        }

        function fallbackCopyTextToClipboard(text, button, buttonText) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess(button, buttonText);
                } else {
                    showCopyError(button, buttonText);
                }
            } catch (err) {
                showCopyError(button, buttonText);
            }
            
            document.body.removeChild(textArea);
        }

        function showCopySuccess(button, buttonText) {
            button.classList.add('copied');
            buttonText.innerHTML = '<i class="fas fa-check"></i> Copied!';
            
            setTimeout(() => {
                button.classList.remove('copied');
                buttonText.innerHTML = '<i class="fas fa-copy"></i> Copy Voucher Code';
            }, 2000);
        }

        function showCopyError(button, buttonText) {
            buttonText.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Copy Failed';
            
            setTimeout(() => {
                buttonText.innerHTML = '<i class="fas fa-copy"></i> Copy Voucher Code';
            }, 2000);
        }

        // Auto-focus on the voucher code for easy manual copying
        document.addEventListener('DOMContentLoaded', function() {
            const voucherElement = document.getElementById('voucherCode');
            if (voucherElement) {
                voucherElement.addEventListener('click', function() {
                    // Select the text when clicked
                    if (window.getSelection) {
                        const selection = window.getSelection();
                        const range = document.createRange();
                        range.selectNodeContents(voucherElement);
                        selection.removeAllRanges();
                        selection.addRange(range);
                    }
                });
            }
        });
    </script>
</body>
</html>
