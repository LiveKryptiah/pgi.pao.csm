<?php
// Force errors to show on this specific page
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h2>System Diagnostic Tool</h2>";

// Test 1: Check if all files exist exactly as named
echo "<h3>1. Checking Files:</h3>";
$files = ['db.php', 'index.php', 'submit.php', 'dashboard.php', 'login.php'];
foreach($files as $file) {
    if(file_exists($file)) {
        echo "<span style='color:green;'>✅ $file is uploaded.</span><br>";
    } else {
        echo "<span style='color:red;'>❌ $file is MISSING! (Check your spelling/capitalization)</span><br>";
    }
}

// Test 2: Check for blank spaces in db.php
echo "<h3>2. Checking db.php structure:</h3>";
if (file_exists('db.php')) {
    $db_content = file_get_contents('db.php');
    if (strpos($db_content, '<?php') !== 0) {
        echo "<span style='color:red;'>❌ CRITICAL ERROR: Your db.php file has blank spaces or text before the &lt;?php tag. Open db.php and delete any empty lines at the very top.</span><br>";
    } else {
        echo "<span style='color:green;'>✅ db.php starts correctly.</span><br>";
    }
}

// Test 3: Attempt Database Connection
echo "<h3>3. Testing Database Connection...</h3>";
try {
    require 'db.php';
    if(isset($pdo)) {
        echo "<span style='color:green;'>✅ Database Connected Successfully!</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color:red;'>❌ Database connection failed: " . $e->getMessage() . "</span><br>";
}

echo "</div>";
?>