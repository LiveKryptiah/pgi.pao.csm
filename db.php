<?php
/**
 * Local Office Database Connection Handler (MySQL / MariaDB)
 * Connects exclusively to the local database (csm_isabela) on this PC
 * so all office co-workers on the LAN share the same local database.
 */

$mysqlHost = getenv('MYSQL_HOST') ?: '127.0.0.1';
$mysqlPort = getenv('MYSQL_PORT') ?: '3306';
$mysqlDb   = getenv('MYSQL_DATABASE') ?: 'csm_isabela';
$mysqlUser = getenv('MYSQL_USER') ?: 'root';
$mysqlPass = getenv('MYSQL_PASSWORD') !== false ? getenv('MYSQL_PASSWORD') : '';

$pdo = null;

if (!function_exists('connect_local_csm_mysql')) {
    function connect_local_csm_mysql($host, $port, $db, $user, $pass) {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ]);
    }
}

if (!function_exists('ensure_local_csm_schema')) {
    function ensure_local_csm_schema(PDO $pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `responses` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `control_number` varchar(50) DEFAULT NULL,
            `survey_date` date NOT NULL,
            `client_type` varchar(50) NOT NULL,
            `gender` varchar(20) NOT NULL,
            `province` varchar(255) DEFAULT NULL,
            `municipality` varchar(255) DEFAULT NULL,
            `age_group` varchar(20) NOT NULL,
            `region` varchar(100) DEFAULT 'Region II',
            `transaction_type` varchar(100) NOT NULL,
            `cc1` varchar(255) NOT NULL,
            `cc2` varchar(50) NOT NULL,
            `cc3` varchar(50) NOT NULL,
            `sqd0` varchar(50) NOT NULL,
            `sqd1` varchar(50) NOT NULL,
            `sqd2` varchar(50) NOT NULL,
            `sqd3` varchar(50) NOT NULL,
            `sqd4` varchar(50) NOT NULL,
            `sqd5` varchar(50) NOT NULL,
            `sqd6` varchar(10) NOT NULL DEFAULT '5',
            `sqd7` varchar(50) NOT NULL,
            `sqd8` varchar(50) NOT NULL,
            `suggestions` text DEFAULT NULL,
            `email` varchar(255) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `password` varchar(255) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `monthly_service_transactions` (
            `target_month` VARCHAR(7) NOT NULL,
            `service_id` INT NOT NULL,
            `service_name` VARCHAR(100) NOT NULL,
            `total_transactions` INT NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`target_month`, `service_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

try {
    $pdo = connect_local_csm_mysql($mysqlHost, $mysqlPort, $mysqlDb, $mysqlUser, $mysqlPass);
} catch (PDOException $e) {
    // If local MySQL server is not running yet on Windows, auto-start it
    $mysqldPath = 'C:\Users\user\mysql8\bin\mysqld.exe';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && file_exists($mysqldPath)) {
        @pclose(@popen('powershell -WindowStyle Hidden -Command "Start-Process \'C:\Users\user\mysql8\bin\mysqld.exe\' -WorkingDirectory \'C:\Users\user\mysql8\' -WindowStyle Hidden"', 'r'));
        for ($i = 0; $i < 8; $i++) {
            usleep(500000);
            try {
                $pdo = connect_local_csm_mysql($mysqlHost, $mysqlPort, $mysqlDb, $mysqlUser, $mysqlPass);
                break;
            } catch (PDOException $retryEx) {
                if (strpos($retryEx->getMessage(), 'Unknown database') !== false) {
                    try {
                        $rootPdo = new PDO("mysql:host={$mysqlHost};port={$mysqlPort};charset=utf8mb4", $mysqlUser, $mysqlPass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                        ]);
                        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$mysqlDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                        $pdo = connect_local_csm_mysql($mysqlHost, $mysqlPort, $mysqlDb, $mysqlUser, $mysqlPass);
                        ensure_local_csm_schema($pdo);
                        break;
                    } catch (Throwable $createErr) {}
                }
            }
        }
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        try {
            $rootPdo = new PDO("mysql:host={$mysqlHost};port={$mysqlPort};charset=utf8mb4", $mysqlUser, $mysqlPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$mysqlDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo = connect_local_csm_mysql($mysqlHost, $mysqlPort, $mysqlDb, $mysqlUser, $mysqlPass);
            ensure_local_csm_schema($pdo);
        } catch (Throwable $createErr) {}
    }

    if (!$pdo) {
        die("Local database connection failed: " . $e->getMessage() . ". Please run start.bat to launch the local MySQL server.");
    }
}
?>
