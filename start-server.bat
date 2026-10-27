@echo off
cd /d "%~dp0"
title CSM Private Office Server + Local Database (Port 8000)

echo ========================================================
echo   CSM (Client Satisfaction Measurement) - Isabela
echo   Provincial Assessor's Office (Local Office Server)
echo ========================================================
echo.

echo Checking and freeing port 8000...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8000 ^| findstr LISTENING') do taskkill /f /pid %%a >nul 2>&1

:: Ensure Local MySQL Database Server is running on port 3306
netstat -ano | findstr :3306 | findstr LISTENING >nul
if %errorlevel% neq 0 (
    echo [INFO] Starting local MySQL database server on port 3306...
    powershell -WindowStyle Hidden -Command "Start-Process 'C:\Users\user\mysql8\bin\mysqld.exe' -WorkingDirectory 'C:\Users\user\mysql8' -WindowStyle Hidden"
    timeout /t 3 /nobreak >nul
    netstat -ano | findstr :3306 | findstr LISTENING >nul
    if %errorlevel% neq 0 (
        echo [ERROR] Could not start local MySQL automatically.
        echo Please verify C:\Users\user\mysql8\bin\mysqld.exe exists.
    ) else (
        echo [OK] Local MySQL database (csm_isabela) started on port 3306.
    )
) else (
    echo [OK] Local MySQL database (csm_isabela) is active on port 3306.
)

echo.
echo ========================================================
echo   OFFICE NETWORK CONNECTIONS FOR CO-WORKERS
echo   (All co-workers share your PC's local database)
echo ========================================================
echo   Your PC has two office network connections:
echo.
echo   [CONNECTION 1 - Primary]:
echo     http://192.168.1.225:8000/index.php
echo.
echo   [CONNECTION 2 - 2nd Office Subnet (Fastest Fix!)]:
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
echo Starting CSM Private Office Web Server on 0.0.0.0:8000...
echo (Binding to 0.0.0.0 allows all office computers to connect)
echo.
echo Press Ctrl+C in this window to stop the server.
echo ========================================================

start "" http://localhost:8000/index.php
php -S 0.0.0.0:8000
pause
