<?php
/**
 * Database Connection Handler
 * Supports PostgreSQL (Neon, Vercel, Railway, Render) and MySQL fallback.
 */

$database_url = getenv('DATABASE_URL') 
    ?: getenv('POSTGRES_URL') 
    ?: getenv('POSTGRES_PRISMA_URL') 
    ?: getenv('DATABASE_URL_UNPOOLED')
    ?: 'postgresql://neondb_owner:npg_gn94ZaRWDLst@ep-snowy-poetry-b8k9tp6g-pooler.c-14.us-east-1.aws.neon.tech/neondb?sslmode=require';

$pdo = null;

// Try PostgreSQL first if extension is available
if (extension_loaded('pdo_pgsql') && $database_url) {
    try {
        $parts = parse_url($database_url);
        $pgHost = $parts['host'] ?? 'ep-snowy-poetry-b8k9tp6g-pooler.c-14.us-east-1.aws.neon.tech';
        $pgPort = $parts['port'] ?? 5432;
        $pgUser = $parts['user'] ?? 'neondb_owner';
        $pgPass = $parts['pass'] ?? 'npg_gn94ZaRWDLst';
        $pgDb   = ltrim($parts['path'] ?? '/neondb', '/');

        $dsn = "pgsql:host=$pgHost;port=$pgPort;dbname=$pgDb;sslmode=require";
        // Neon SNI compatibility (required by Neon when client libpq does not send SNI)
        if (strpos($pgHost, 'neon.tech') !== false) {
            $endpoint = explode('.', $pgHost)[0];
            $dsn .= ";options='endpoint=$endpoint'";
        }

        $pdo = new PDO($dsn, $pgUser, $pgPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ]);
    } catch (Throwable $e) {
        $pg_error = $e->getMessage();
        $pdo = null;
    }
}

// Fallback to local MySQL if PostgreSQL is not available
if (!$pdo && extension_loaded('pdo_mysql') && !getenv('VERCEL')) {
    $mysqlHost = getenv('MYSQL_HOST') ?: '127.0.0.1';
    $mysqlDb   = getenv('MYSQL_DATABASE') ?: 'csm_isabela';
    $mysqlUser = getenv('MYSQL_USER') ?: 'root';
    $mysqlPass = getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : '';

    try {
        $pdo = new PDO("mysql:host=$mysqlHost;dbname=$mysqlDb;charset=utf8mb4", $mysqlUser, $mysqlPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage() . " (PostgreSQL error: " . ($pg_error ?? 'none') . ")");
    }
}

if (!$pdo) {
    die("Database connection failed (PostgreSQL): " . ($pg_error ?? 'Unknown error'));
}
?>