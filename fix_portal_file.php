<?php
/**
 * Fix portal.php by removing orphaned duplicate code after line 1695
 */

$file = 'portal.php';
$backup = 'portal_backup_' . date('Y-m-d_H-i-s') . '.php';

// Read the file
$lines = file($file);

if ($lines === false) {
    die("Error: Could not read $file");
}

// Create backup
if (!copy($file, $backup)) {
    die("Error: Could not create backup");
}

echo "Backup created: $backup<br>";
echo "Total lines in original file: " . count($lines) . "<br>";

// Keep only the first 1695 lines
$fixed_lines = array_slice($lines, 0, 1695);

echo "Lines after fix: " . count($fixed_lines) . "<br>";

// Write the fixed content back
if (file_put_contents($file, implode('', $fixed_lines)) === false) {
    die("Error: Could not write to $file");
}

echo "<br><strong>✅ SUCCESS!</strong><br>";
echo "portal.php has been fixed!<br>";
echo "Removed " . (count($lines) - count($fixed_lines)) . " orphaned lines.<br>";
echo "<br>Please refresh your portal page and test the modal.";
?>

