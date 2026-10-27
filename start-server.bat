@echo off
title CSM Local Server (Office LAN)
echo ========================================================
echo   CSM (Client Satisfaction Measurement) - Isabela
echo   Provincial Assessor's Office
echo ========================================================
echo.

:: Check if MySQL/MariaDB is listening on port 3306
netstat -ano | findstr :3306 | findstr LISTENING >nul
if %errorlevel% neq 0 (
    echo [INFO] MySQL is not running on port 3306.
    echo [INFO] Starting local MySQL server from C:\Users\user\mysql8...
    powershell -WindowStyle Hidden -Command "Start-Process 'C:\Users\user\mysql8\bin\mysqld.exe' -WorkingDirectory 'C:\Users\user\mysql8' -WindowStyle Hidden"
    timeout /t 3 /nobreak >nul
    netstat -ano | findstr :3306 | findstr LISTENING >nul
    if %errorlevel% neq 0 (
        echo [ERROR] Could not start MySQL automatically.
        echo Please ensure C:\Users\user\mysql8\bin\mysqld.exe can be started.
    ) else (
        echo [OK] MySQL started successfully on port 3306.
    )
) else (
    echo [OK] MySQL is already active on port 3306.
)

echo.
echo ========================================================
echo   OFFICE NETWORK CONNECTIONS FOR CO-WORKERS
echo ========================================================
echo   Your PC has two office network connections:
echo.
echo   [CONNECTION 1 - Primary]:
echo     http://192.168.1.225:8000/index.php
echo.
echo   [CONNECTION 2 - 2nd Office Subnet (Fastest Fix for co-workers)]:
echo     http://192.168.4.210:8000/index.php
echo.
echo   [ADMIN LOGIN]:
echo     http://192.168.1.225:8000/login.php
echo.
echo   [LOCAL HOST]:
echo     http://localhost:8000/index.php
echo.
echo   [DIAGNOSTICS ^& LAN HUB]:
echo     http://localhost:8000/diagnose.php
echo ========================================================
echo   CO-WORKER TROUBLESHOOTING:
echo   - Solution 1: If 192.168.1.225 fails, have them open:
echo                 http://192.168.4.210:8000/index.php
echo   - Solution 2: Verify co-worker is on the same office Wi-Fi
echo                 (No Guest Wi-Fi / mobile hotspot)
echo   - Solution 3: In Windows Settings, set network to Private
echo ========================================================
echo.
echo Starting PHP built-in web server on 0.0.0.0:8000...
echo (Binding to 0.0.0.0 allows all office computers to connect)
echo.
echo Press Ctrl+C in this window to stop the server.
echo ========================================================

start "" http://localhost:8000/index.php
php -S 0.0.0.0:8000
pause
