@echo off
title CSM Co-worker Desktop Shortcut Installer
echo ============================================================
echo   CSM - Client Satisfaction Measurement (Co-worker Setup)
echo   Provincial Assessor's Office - Province of Isabela
echo ============================================================
echo.
echo The Main Host PC has two office network connections:
echo   [1] 192.168.1.225  (Primary Office Network)
echo   [2] 192.168.4.210  (Secondary Office Network / 2nd Subnet)
echo.
set /p SERVER_IP="Enter Server IP Address [Press Enter for 192.168.1.225]: "
if "%SERVER_IP%"=="" set SERVER_IP=192.168.1.225
if "%SERVER_IP%"=="1" set SERVER_IP=192.168.1.225
if "%SERVER_IP%"=="2" set SERVER_IP=192.168.4.210

set SERVER_PORT=8000
set DESKTOP_DIR=%USERPROFILE%\Desktop
set SHORTCUT_SURVEY=%DESKTOP_DIR%\CSM Client Survey.url
set SHORTCUT_ADMIN=%DESKTOP_DIR%\CSM Admin Portal.url

(
echo [InternetShortcut]
echo URL=http://%SERVER_IP%:%SERVER_PORT%/index.php
echo IconIndex=0
) > "%SHORTCUT_SURVEY%"

(
echo [InternetShortcut]
echo URL=http://%SERVER_IP%:%SERVER_PORT%/login.php
echo IconIndex=0
) > "%SHORTCUT_ADMIN%"

echo.
echo ============================================================
echo [SUCCESS] Created Desktop shortcuts on your PC:
echo   1. "CSM Client Survey"  -^> http://%SERVER_IP%:%SERVER_PORT%/index.php
echo   2. "CSM Admin Portal"   -^> http://%SERVER_IP%:%SERVER_PORT%/login.php
echo ============================================================
echo.
echo ============================================================
echo IF THE LINK DOES NOT OPEN ON YOUR COMPUTER:
echo ============================================================
echo   Solution 1: Run this again and select Option 2 (192.168.4.210)
echo               (Some office desks are routed via the 2nd subnet)
echo.
echo   Solution 2: Ensure you are on the SAME office Wi-Fi network
echo               (Do NOT connect to Guest Wi-Fi or phone mobile hotspot)
echo.
echo   Solution 3: In Windows Settings (Win + I) -^> Network ^& Internet,
echo               change your network profile from "Public" to "Private"
echo ============================================================
echo.
pause
