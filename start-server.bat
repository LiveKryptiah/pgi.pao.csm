@echo off
title CSM Local Server
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
echo Starting PHP built-in web server on http://localhost:8000...
echo.
echo Available URLs:
echo   - Survey Portal:  http://localhost:8000/index.php
echo   - Admin Login:    http://localhost:8000/login.php
echo   - Diagnostics:    http://localhost:8000/diagnose.php
echo.
echo Press Ctrl+C in this window to stop the server.
echo ========================================================

start "" http://localhost:8000/index.php
php -S localhost:8000
pause
