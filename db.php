<?php
// includes/db.php
// Gram Panchayat Complaint Management System - Database Connection & Initialization

$host = 'localhost';
$user = 'root';
$pass = ''; // Default for local servers like XAMPP / WAMP
$dbname = 'gp_complaint_db';

try {
    // 1. Connect to MySQL server (without database selected first, to ensure we can create it if missing)
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // 2. Create database if it does not exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    
    // 3. Select the database
    $pdo->exec("USE `$dbname`");
    
    // 4. Check if tables exist by querying the roles table
    $tableExists = false;
    try {
        $result = $pdo->query("SELECT 1 FROM roles LIMIT 1");
        $tableExists = true;
    } catch (Exception $e) {
        $tableExists = false;
    }
    
    // 5. If tables are not initialized, parse and execute schema.sql
    if (!$tableExists) {
        $schemaPath = dirname(__DIR__) . '/schema.sql';
        if (file_exists($schemaPath)) {
            $sql = file_get_contents($schemaPath);
            
            // Standard PDO exec doesn't execute multi-query blocks consistently on all configurations.
            // We split queries by semicolon and run them one by one.
            // Simple SQL parsing: split by ';' ignoring those inside single quotes (simplified)
            // Or we can use PDO's ability to run multiple statements if emulation is enabled:
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $pdo->exec($sql);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Restore safety
        }
        
        // 6. Seed Default Users with secure dynamic bcrypt password hashing
        // Roles: 1 = super_admin, 2 = gp_admin, 3 = field_officer, 4 = citizen
        $usersToSeed = [
            [
                'username' => 'superadmin', 
                'password' => 'admin123', 
                'name' => 'Super Administrator', 
                'email' => 'superadmin@gp.gov.in', 
                'phone' => '9999900001', 
                'role_id' => 1
            ],
            [
                'username' => 'gpadmin', 
                'password' => 'admin123', 
                'name' => 'Gram Sevak (GP Admin)', 
                'email' => 'gramsevak@gp.gov.in', 
                'phone' => '9999900002', 
                'role_id' => 2
            ],
            [
                'username' => 'officer1', 
                'password' => 'officer123', 
                'name' => 'Rohan Das (Field Officer)', 
                'email' => 'rohan@gp.gov.in', 
                'phone' => '9999900003', 
                'role_id' => 3
            ],
            [
                'username' => 'citizen1', 
                'password' => 'citizen123', 
                'name' => 'Rajesh Patel (Citizen)', 
                'email' => 'rajesh@gmail.com', 
                'phone' => '9876543210', 
                'role_id' => 4
            ]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role_id) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($usersToSeed as $u) {
            $hashedPass = password_hash($u['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $u['username'], 
                $hashedPass, 
                $u['name'], 
                $u['email'], 
                $u['phone'], 
                $u['role_id']
            ]);
        }
    }
} catch (PDOException $e) {
    die("Database Initialization/Connection Failed: " . $e->getMessage() . "<br><br>Please verify that your MySQL server is running on localhost with username 'root' and no password (default XAMPP configuration).");
}
?>
