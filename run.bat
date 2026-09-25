@echo off
title Darkdump OSINT Suite
color 0E

echo ======================================================================
echo          DARKDUMP OSINT - AUTOMATED SYSTEM STARTUP
echo ======================================================================
echo.
echo [1/3] Verifying Tor SOCKS5 proxy socket...
php artisan tor:start
echo.
echo [2/3] Checking Port 8080 availability...
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }"
echo.
echo [3/3] Starting Laravel Web Application on http://127.0.0.1:8080 ...
echo Multi-worker support active (4 workers).
echo Press Ctrl+C in this terminal to stop the server anytime.
echo.
timeout /t 2 /nobreak >nul
start http://127.0.0.1:8080
php artisan serve --port=8080 --no-reload
pause

