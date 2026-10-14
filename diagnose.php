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

// Test 3: Check Loaded Extensions & Attempt Database Connection
echo "<h3>3. PHP Modules & Database Connection:</h3>";
echo "<b>Loaded extensions:</b> " . implode(', ', get_loaded_extensions()) . "<br><br>";
echo "<b>pdo_pgsql loaded?</b> " . (extension_loaded('pdo_pgsql') ? 'YES' : 'NO') . "<br>";
echo "<b>pgsql loaded?</b> " . (extension_loaded('pgsql') ? 'YES' : 'NO') . "<br>";

$database_url = getenv('DATABASE_URL') 
    ?: getenv('POSTGRES_URL') 
    ?: 'postgresql://neondb_owner:npg_gn94ZaRWDLst@ep-snowy-poetry-b8k9tp6g-pooler.c-14.us-east-1.aws.neon.tech/neondb?sslmode=require';

echo "<b>Connecting to PG:</b> ";
try {
    $parts = parse_url($database_url);
    $pgHost = $parts['host'] ?? 'ep-snowy-poetry-b8k9tp6g-pooler.c-14.us-east-1.aws.neon.tech';
    $pgPort = $parts['port'] ?? 5432;
    $pgUser = $parts['user'] ?? 'neondb_owner';
    $pgPass = $parts['pass'] ?? 'npg_gn94ZaRWDLst';
    $pgDb   = ltrim($parts['path'] ?? '/neondb', '/');

    $dsn = "pgsql:host=$pgHost;port=$pgPort;dbname=$pgDb;sslmode=require";
    $test_pdo = new PDO($dsn, $pgUser, $pgPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<span style='color:green;'>✅ Connected to PostgreSQL successfully! Total rows: " . $test_pdo->query("SELECT count(*) FROM responses")->fetchColumn() . "</span><br>";
} catch (Throwable $e) {
    echo "<span style='color:red;'>❌ PostgreSQL Error: " . $e->getMessage() . "</span><br>";
}

echo "</div>";
?>