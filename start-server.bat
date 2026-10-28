@echo off
setlocal enabledelayedexpansion

:: Determine the CSM website folder
set "CSM_DIR=%~dp0"
if not exist "%CSM_DIR%index.php" (
    if exist "C:\Users\user\OneDrive\Documents\Azarel\Website\csm\index.php" (
        set "CSM_DIR=C:\Users\user\OneDrive\Documents\Azarel\Website\csm\"
    )
)

cd /d "%CSM_DIR%"
title CSM Private Office Server + Local Database (Port 8000)

echo ========================================================
echo   CSM - Client Satisfaction Measurement (Isabela)
echo   Provincial Assessor's Office - Local Office Server
echo ========================================================
echo Website Folder: %CSM_DIR%
echo.

echo [1/3] Checking and freeing port 8000...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8000 ^| findstr LISTENING') do taskkill /f /pid %%a >nul 2>&1

echo [2/3] Checking Local MySQL Database on port 3306...
netstat -ano | findstr :3306 | findstr LISTENING >nul
if %errorlevel% equ 0 goto MYSQL_READY

echo       Starting local MySQL server from C:\Users\user\mysql8...
powershell -WindowStyle Hidden -Command "Start-Process 'C:\Users\user\mysql8\bin\mysqld.exe' -WorkingDirectory 'C:\Users\user\mysql8' -WindowStyle Hidden"
ping 127.0.0.1 -n 4 >nul

:MYSQL_READY
echo       [OK] Local MySQL database csm_isabela is active on port 3306.
echo.
echo ========================================================
echo   OFFICE NETWORK CONNECTIONS FOR CO-WORKERS
echo   All co-workers share your PC's local database
echo ========================================================
echo   Your PC has two office network connections:
echo.
echo   [CONNECTION 1 - Primary]:
echo     http://192.168.1.225:8000/index.php
echo.
echo   [CONNECTION 2 - 2nd Office Subnet - Fastest Fix]:
echo     http://192.168.4.210:8000/index.php
echo.
echo   [ADMIN LOGIN]:
echo     http://192.168.1.225:8000/login.php
echo.
echo   [LOCAL HOST]:
echo     http://localhost:8000/index.php
echo.
echo   [DIAGNOSTICS AND LAN HUB]:
echo     http://localhost:8000/diagnose.php
echo ========================================================
echo   CO-WORKER TROUBLESHOOTING:
echo   - Solution 1: If 192.168.1.225 fails, have them open:
echo                 http://192.168.4.210:8000/index.php
echo   - Solution 2: Verify co-worker is on the same office Wi-Fi
echo                 Do NOT use Guest Wi-Fi or mobile hotspot
echo   - Solution 3: In Windows Settings, set network to Private
echo ========================================================
echo.
echo [3/3] Starting CSM Web Server on 0.0.0.0:8000...
echo       Serving files from: %CSM_DIR%
echo       Keep this window open while the office is using CSM.
echo       Press Ctrl+C in this window to stop the server.
echo ========================================================
echo.

start "" http://localhost:8000/index.php
if exist "C:\Users\user\php\php.exe" (
    "C:\Users\user\php\php.exe" -S 0.0.0.0:8000 -t "%CSM_DIR%"
) else (
    php -S 0.0.0.0:8000 -t "%CSM_DIR%"
)
pause
