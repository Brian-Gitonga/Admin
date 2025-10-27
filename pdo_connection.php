<?php
// PDO Database Connection as fallback for MySQLi
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "billing_system";

try {
    // Create PDO connection
    $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
    $pdo_conn = new PDO($dsn, $username, $password);
    
    // Set error mode to exception
    $pdo_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode
    $pdo_conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // For backward compatibility, create a wrapper that mimics MySQLi
    class PDOWrapper {
        private $pdo;
        public $connect_error = null;
        public $error = null;
        
        public function __construct($pdo) {
            $this->pdo = $pdo;
        }
        
        public function prepare($query) {
            try {
                return new PDOStatementWrapper($this->pdo->prepare($query));
            } catch (PDOException $e) {
                $this->error = $e->getMessage();
                return false;
            }
        }
        
        public function query($query) {
            try {
                $stmt = $this->pdo->query($query);
                return new PDOResultWrapper($stmt);
            } catch (PDOException $e) {
                $this->error = $e->getMessage();
                return false;
            }
        }
        
        public function set_charset($charset) {
            // PDO handles charset in DSN
            return true;
        }
        
        public function close() {
            $this->pdo = null;
        }
    }
    
    class PDOStatementWrapper {
        private $stmt;
        public $error = null;
        
        public function __construct($stmt) {
            $this->stmt = $stmt;
        }
        
        public function bind_param($types, ...$params) {
            try {
                for ($i = 0; $i < count($params); $i++) {
                    $this->stmt->bindValue($i + 1, $params[$i]);
                }
                return true;
            } catch (PDOException $e) {
                $this->error = $e->getMessage();
                return false;
            }
        }
        
        public function execute() {
            try {
                return $this->stmt->execute();
            } catch (PDOException $e) {
                $this->error = $e->getMessage();
                return false;
            }
        }
        
        public function get_result() {
            return new PDOResultWrapper($this->stmt);
        }
    }
    
    class PDOResultWrapper {
        private $stmt;
        public $num_rows;
        
        public function __construct($stmt) {
            $this->stmt = $stmt;
            $this->num_rows = $stmt->rowCount();
        }
        
        public function fetch_assoc() {
            return $this->stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        public function fetch_array() {
            return $this->stmt->fetch(PDO::FETCH_BOTH);
        }
    }
    
    // Create the wrapper
    $portal_conn = new PDOWrapper($pdo_conn);
    $conn = $portal_conn; // For backward compatibility
    
} catch (PDOException $e) {
    error_log("PDO Database Connection Error: " . $e->getMessage());
    $portal_conn = null;
    $conn = null;
    
    // Only show error message if we're in development mode
    if (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1')) {
        echo "<div style='color: red; padding: 20px; margin: 20px; border: 1px solid red; background: #ffeeee;'>";
        echo "<h3>Database Connection Error</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Please check your database configuration and ensure MySQL is running.</p>";
        echo "</div>";
    }
}

// Function to check if database is connected
function is_db_connected() {
    global $portal_conn;
    return $portal_conn !== null;
}
?>
