<?php
// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Get the reseller ID from the session
$resellerId = $_SESSION['user_id'];

// Set response header
header('Content-Type: application/json');

// Check if required parameters are provided
if (!isset($_POST['package_id']) || !isset($_POST['router_id'])) {
    echo json_encode(['success' => false, 'message' => 'Package ID and Router ID are required']);
    exit;
}

$packageId = $_POST['package_id'];
$routerId = $_POST['router_id'];

// Validate package_id and router_id
if (!is_numeric($packageId) || !is_numeric($routerId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid Package ID or Router ID']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'No file uploaded or upload error occurred';
    if (isset($_FILES['file']['error'])) {
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = 'The uploaded file exceeds the maximum file size limit';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = 'The file was only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = 'No file was uploaded';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = 'Missing a temporary folder';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = 'Failed to write file to disk';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage = 'A PHP extension stopped the file upload';
                break;
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit;
}

// Get file information
$file = $_FILES['file'];
$fileName = $file['name'];
$fileTmpPath = $file['tmp_name'];
$fileSize = $file['size'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Check file size (max 5MB)
$maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
if ($fileSize > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds the 5MB limit']);
    exit;
}

// Check file extension
$allowedExtensions = ['csv', 'xlsx', 'xls', 'pdf'];
if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file format. Only CSV, Excel, and PDF files are allowed']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadsDir = __DIR__ . '/../uploads/vouchers';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Generate unique filename
$uniqueFileName = uniqid('voucher_') . '.' . $fileExtension;
$uploadFilePath = $uploadsDir . '/' . $uniqueFileName;

// Move uploaded file
if (!move_uploaded_file($fileTmpPath, $uploadFilePath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file']);
    exit;
}

// Process the file based on extension
$vouchers = [];
$errors = [];

try {
    if ($fileExtension === 'csv') {
        // Process CSV file
        $vouchers = processCSV($uploadFilePath);
    } else if ($fileExtension === 'xlsx' || $fileExtension === 'xls') {
        // Process Excel file (xlsx or xls)
        $vouchers = processExcel($uploadFilePath, $fileExtension);
    } else if ($fileExtension === 'pdf') {
        // Process PDF file
        // This requires a PDF parsing library or the pdftotext utility
        // Simplified demonstration using pattern matching:
        $vouchers = processPDF($uploadFilePath);
        
        if (empty($vouchers)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Could not extract voucher codes from the PDF. Make sure the PDF contains readable text with voucher codes.'
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Unsupported file format'
        ]);
        exit;
    }
    
    // Validate vouchers
    $validVouchers = validateVouchers($vouchers, $conn, $errors);
    
    // Import valid vouchers to database
    $importCount = importVouchers($validVouchers, $conn, $resellerId, $packageId, $routerId);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Successfully imported $importCount voucher(s). " . 
                     (count($errors) > 0 ? count($errors) . " voucher(s) had errors and were skipped." : ""),
        'imported' => $importCount,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode(['success' => false, 'message' => 'Error processing file: ' . $e->getMessage()]);
} finally {
    // Clean up - delete the uploaded file
    @unlink($uploadFilePath);
}

/**
 * Process CSV file and return array of vouchers
 */
function processCSV($filePath) {
    $vouchers = [];
    $headers = [];
    
    // Open the CSV file
    if (($handle = fopen($filePath, "r")) !== false) {
        // Read header row
        if (($headerRow = fgetcsv($handle, 1000, ",")) !== false) {
            $headers = array_map('trim', array_map('strtolower', $headerRow));

            // Check if required columns exist - prioritize 'username' column over 'code' column
            $hasUsernameColumn = in_array('username', $headers);
            $hasCodeColumn = in_array('code', $headers);

            if (!$hasUsernameColumn && !$hasCodeColumn) {
                throw new Exception("CSV file must contain either a 'username' or 'code' column");
            }

            // Map column indices
            $codeIndex = $hasCodeColumn ? array_search('code', $headers) : null;
            $usernameIndex = $hasUsernameColumn ? array_search('username', $headers) : null;
            $passwordIndex = in_array('password', $headers) ? array_search('password', $headers) : null;
            
            // Read data rows
            while (($dataRow = fgetcsv($handle, 1000, ",")) !== false) {
                // Determine the voucher code - prioritize username column
                $voucherCode = null;

                if ($usernameIndex !== null && isset($dataRow[$usernameIndex]) && !empty(trim($dataRow[$usernameIndex]))) {
                    // Use username column value as the voucher code
                    $voucherCode = trim($dataRow[$usernameIndex]);
                } elseif ($codeIndex !== null && isset($dataRow[$codeIndex]) && !empty(trim($dataRow[$codeIndex]))) {
                    // Fall back to code column if username is not available
                    $voucherCode = trim($dataRow[$codeIndex]);
                }

                // Only process if we have a valid voucher code
                if ($voucherCode !== null) {
                    $voucher = ['code' => $voucherCode];

                    // Set username - use the voucher code as username
                    $voucher['username'] = $voucherCode;

                    // If password column exists and has a value, use it
                    if ($passwordIndex !== null && isset($dataRow[$passwordIndex]) && !empty(trim($dataRow[$passwordIndex]))) {
                        $voucher['password'] = trim($dataRow[$passwordIndex]);
                    } else {
                        // Otherwise, use voucher code as password
                        $voucher['password'] = $voucherCode;
                    }

                    $vouchers[] = $voucher;
                }
            }
        }
        fclose($handle);
    } else {
        throw new Exception("Failed to open CSV file");
    }
    
    return $vouchers;
}

/**
 * Process PDF file to extract voucher codes
 * This is a simplified implementation. In production, you would want to use a
 * PDF parser library like TCPDF or smalot/pdfparser.
 */
function processPDF($filePath) {
    $vouchers = [];
    
    // Check if pdftotext command is available (part of poppler-utils)
    if (isCommandAvailable('pdftotext')) {
        // Use pdftotext to extract text from PDF
        $textContent = shell_exec("pdftotext \"$filePath\" -");
        
        // Process the extracted text
        if ($textContent) {
            // Extract codes using regex patterns
            // This regex looks for patterns that appear to be voucher codes
            // Adjust the pattern based on your actual voucher code format
            preg_match_all('/(?:\b|^)([A-Za-z0-9]{4,16})(?:\b|$)/', $textContent, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $code) {
                    $vouchers[] = ['code' => $code];
                }
            }
        }
    } else {
        // Fallback message if pdftotext is not available
        throw new Exception("PDF processing requires pdftotext utility which is not available on this server. Please convert your PDF to CSV and try again.");
    }
    
    return $vouchers;
}

/**
 * Check if a command is available on the system
 */
function isCommandAvailable($command) {
    $whereIsCommand = (PHP_OS == 'WINNT') ? "where" : "which";
    $result = shell_exec("$whereIsCommand $command");
    return !empty($result);
}

/**
 * Validate vouchers and return only valid ones
 */
function validateVouchers($vouchers, $conn, &$errors) {
    $validVouchers = [];
    $existingCodes = [];
    
    // Get all existing voucher codes from database
    $stmt = $conn->prepare("SELECT code FROM vouchers");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $existingCodes[] = $row['code'];
    }
    
    // Validate each voucher
    foreach ($vouchers as $index => $voucher) {
        $lineNumber = $index + 2; // +2 because index starts at 0 and we skip header row
        
        // Check if code is empty
        if (empty($voucher['code'])) {
            $errors[] = "Line $lineNumber: Voucher code cannot be empty";
            continue;
        }
        
        // Check if code already exists in database
        if (in_array($voucher['code'], $existingCodes)) {
            $errors[] = "Line $lineNumber: Voucher code '{$voucher['code']}' already exists in the system";
            continue;
        }
        
        // Check for duplicate codes within the file
        $codeExists = false;
        foreach ($validVouchers as $existingVoucher) {
            if ($existingVoucher['code'] === $voucher['code']) {
                $codeExists = true;
                break;
            }
        }
        
        if ($codeExists) {
            $errors[] = "Line $lineNumber: Duplicate voucher code '{$voucher['code']}' within the file";
            continue;
        }
        
        // Voucher is valid, add to valid list
        $validVouchers[] = $voucher;
    }
    
    return $validVouchers;
}

/**
 * Import vouchers to database
 */
function importVouchers($vouchers, $conn, $resellerId, $packageId, $routerId) {
    if (empty($vouchers)) {
        return 0;
    }
    
    $count = 0;
    $currentDate = date('Y-m-d H:i:s');
    
    // Get router information
    $routerQuery = "SELECT name, router_ip, router_username, router_password, api_port FROM hotspots WHERE id = ?";
    $routerStmt = $conn->prepare($routerQuery);
    
    if (!$routerStmt) {
        error_log("Error preparing router query: " . $conn->error);
        throw new Exception("Failed to retrieve router information");
    }
    
    $routerStmt->bind_param("i", $routerId);
    $routerStmt->execute();
    $routerResult = $routerStmt->get_result();
    
    if ($routerResult->num_rows === 0) {
        throw new Exception("Router not found");
    }
    
    $router = $routerResult->fetch_assoc();
    $routerData = [
        'ip_address' => $router['router_ip'],
        'username' => $router['router_username'],
        'password' => $router['router_password'],
        'api_port' => $router['api_port'] ?? 8728
    ];
    
    // MikroTik integration removed - vouchers will be generated without router communication
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Prepare statement for inserting vouchers with username, password, and router_id
        $stmt = $conn->prepare("
            INSERT INTO vouchers (code, username, password, package_id, router_id, reseller_id, customer_phone, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'admin', 'active', ?)
        ");
        
        // Insert each voucher
        foreach ($vouchers as $voucher) {
            $stmt->bind_param("sssiiss", 
                $voucher['code'], 
                $voucher['username'], 
                $voucher['password'], 
                $packageId,
                $routerId,
                $resellerId, 
                $currentDate
            );
            
            if ($stmt->execute()) {
                $count++;
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Attempt to import vouchers to MikroTik router
        if ($count > 0) {
            $importResult = importMikroTikVouchers($vouchers, $packageId, $routerData);
            
            if (!$importResult['success']) {
                error_log("Warning: Vouchers saved to database but not imported to MikroTik: " . $importResult['message']);
            } else {
                error_log("Successfully imported vouchers to MikroTik router");
            }
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }
    
    return $count;
}

/**
 * Import vouchers to MikroTik router
 * This function attempts to add vouchers to the MikroTik router
 * Returns success/failure status for logging purposes
 */
function importMikroTikVouchers($vouchers, $packageId, $routerData) {
    // Since MikroTik integration is disabled in this system,
    // we'll return a success response for compatibility
    error_log("MikroTik integration disabled - vouchers saved to database only");

    return [
        'success' => true,
        'message' => 'Vouchers saved to database (MikroTik integration disabled)'
    ];
}

// Function to process Excel files
function processExcel($filePath, $fileExtension) {
    try {
        // Check if vendor directory exists
        if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
            throw new Exception("PhpSpreadsheet library not found. Please run 'composer install' first.");
        }
        
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $vouchers = [];
        
        // Create Excel reader based on file extension
        if ($fileExtension === 'xlsx') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        }
        
        // Only read data, not formatting
        $reader->setReadDataOnly(true);
        
        // Load spreadsheet
        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Get the highest column index
        $highestColumn = $worksheet->getHighestDataColumn(1); // Get highest column from first row
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        // Get headers from first row
        $headers = [];
        $headerRow = $worksheet->getRowIterator(1, 1)->current();
        $cellIterator = $headerRow->getCellIterator('A', $highestColumn);
        $cellIterator->setIterateOnlyExistingCells(false);
        
        $colIndex = 0;
        foreach ($cellIterator as $cell) {
            $headers[$colIndex] = strtolower(trim((string)$cell->getValue()));
            $colIndex++;
        }
        
        // Check if required columns exist - prioritize 'username' column over 'code' column
        $hasUsernameColumn = in_array('username', $headers);
        $hasCodeColumn = in_array('code', $headers);

        if (!$hasUsernameColumn && !$hasCodeColumn) {
            throw new Exception("Excel file must contain either a 'username' or 'code' column");
        }

        // Get indices for required columns
        $codeIndex = $hasCodeColumn ? array_search('code', $headers) : null;
        $usernameIndex = $hasUsernameColumn ? array_search('username', $headers) : null;
        $passwordIndex = in_array('password', $headers) ? array_search('password', $headers) : null;
        
        // Get the highest row with data
        $highestRow = $worksheet->getHighestDataRow();
        
        // Read data rows (starting from row 2)
        for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
            // Initialize row data array
            $rowData = array_fill(0, $highestColumnIndex, '');
            
            // Read cell values for this row
            for ($colIndex = 0; $colIndex < $highestColumnIndex; $colIndex++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $cellCoordinate = $colLetter . $rowIndex;
                
                if ($worksheet->cellExists($cellCoordinate)) {
                    $cellValue = $worksheet->getCell($cellCoordinate)->getValue();
                    $rowData[$colIndex] = trim((string)$cellValue);
                }
            }
            
            // Determine the voucher code - prioritize username column
            $voucherCode = null;

            if ($usernameIndex !== null && isset($rowData[$usernameIndex]) && !empty($rowData[$usernameIndex])) {
                // Use username column value as the voucher code
                $voucherCode = $rowData[$usernameIndex];
            } elseif ($codeIndex !== null && isset($rowData[$codeIndex]) && !empty($rowData[$codeIndex])) {
                // Fall back to code column if username is not available
                $voucherCode = $rowData[$codeIndex];
            }

            // Only process if we have a valid voucher code
            if ($voucherCode !== null) {
                $voucher = ['code' => $voucherCode];

                // Set username - use the voucher code as username
                $voucher['username'] = $voucherCode;

                // If password column exists and has a value, use it
                if ($passwordIndex !== null && isset($rowData[$passwordIndex]) && !empty($rowData[$passwordIndex])) {
                    $voucher['password'] = $rowData[$passwordIndex];
                } else {
                    // Otherwise, use voucher code as password
                    $voucher['password'] = $voucherCode;
                }

                $vouchers[] = $voucher;
            }
        }
        
        return $vouchers;
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        throw new Exception("Error reading Excel file: " . $e->getMessage());
    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
        throw new Exception("Error processing Excel file: " . $e->getMessage());
    }
}

// Function to process Excel files (requires PhpSpreadsheet library)
// Uncomment and implement if the library is available
/*
function processExcel($filePath, $fileExtension) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $vouchers = [];
    
    // Create Excel reader based on file extension
    if ($fileExtension === 'xlsx') {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
    } else {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
    }
    
    // Load spreadsheet
    $spreadsheet = $reader->load($filePath);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Get headers from first row
    $headers = [];
    foreach ($worksheet->getRowIterator(1, 1) as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        
        foreach ($cellIterator as $cell) {
            $headers[] = strtolower(trim($cell->getValue()));
        }
    }
    
    // Check if required columns exist
    if (!in_array('code', $headers)) {
        throw new Exception("Excel file must contain a 'code' column");
    }
    
    // Get indices for required columns
    $codeIndex = array_search('code', $headers);
    
    // Read data rows (starting from row 2)
    foreach ($worksheet->getRowIterator(2) as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        
        $rowData = [];
        foreach ($cellIterator as $cell) {
            $rowData[] = trim($cell->getValue());
        }
        
        if (isset($rowData[$codeIndex]) && !empty($rowData[$codeIndex])) {
            $voucher = [
                'code' => $rowData[$codeIndex]
            ];
            
            $vouchers[] = $voucher;
        }
    }
    
    return $vouchers;
}
*/ 