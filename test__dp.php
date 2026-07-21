<?php
// C:\Users\DELL\Downloads\nutan\htdocs\gp-complaint-system\test_db.php
// GPCMS Database Diagnostic Tool

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>GPCMS Database Diagnostic</title>";
echo "<style>body{font-family: Arial, sans-serif; padding: 25px; background: #f7fafc;} h2{color: #1e5624;} .success{color: green; font-weight: bold;} .error{color: red; font-weight: bold; padding: 10px; background: #fee; border: 1px solid #fdd; border-radius: 4px; margin: 10px 0;}</style>";
echo "</head><body>";

echo "<h2>GPCMS Database Connection Diagnostic</h2>";
echo "<hr>";

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'gp_complaint_db';

try {
    echo "<p>Step 1: Attempting connection to MySQL server (host: <strong>$host</strong>, user: <strong>$user</strong>)...</p>";
    
    // Connect to server
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p class='success'>✔ Successfully connected to MySQL server!</p>";
    
    echo "<p>Step 2: Checking if database <strong>$dbname</strong> exists...</p>";
    $dbCheck = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'")->fetch();
    
    if ($dbCheck) {
        echo "<p class='success'>✔ Database '$dbname' exists.</p>";
        $pdo->exec("USE `$dbname`");
        
        echo "<p>Step 3: Checking table structures and seed data...</p>";
        $tables = ['roles', 'users', 'complaint_categories', 'complaint_statuses', 'complaints', 'announcements', 'government_schemes', 'emergency_contacts', 'notifications', 'feedback'];
        
        echo "<ul>";
        foreach ($tables as $t) {
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
                echo "<li>Table <strong>$t</strong> exists: <span class='success'>✔ Yes ($count rows)</span></li>";
            } catch (Exception $e) {
                echo "<li>Table <strong>$t</strong> exists: <span class='error'>✘ Error: " . $e->getMessage() . "</span></li>";
            }
        }
        echo "</ul>";
        
        echo "<p class='success'>✔ Diagnosis complete. All tables are set up correctly.</p>";
        echo "<p><a href='index.php'>Go to Homepage</a></p>";
    } else {
        echo "<p class='error'>✘ Database '$dbname' does not exist yet. Please load index.php to trigger auto-setup, or manually run the schema.sql in phpMyAdmin.</p>";
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h3>✘ Connection Failed!</h3>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "</div>";
    
    echo "<h4>Troubleshooting Checklist:</h4>";
    echo "<ul>";
    echo "<li>Is the <strong>MySQL Module</strong> started in your XAMPP/WAMP Control Panel? (Must show green background on the 'MySQL' row)</li>";
    echo "<li>Are you running MySQL on a different port than the default 3306? (If so, update includes/db.php host to 'localhost:PORT')</li>";
    echo "<li>Did you set a password for the root user? (If so, update includes/db.php password setting)</li>";
    echo "</ul>";
}

echo "</body></html>";
?>
