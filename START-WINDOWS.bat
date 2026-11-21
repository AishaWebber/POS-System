@echo off
cd /d "%~dp0"
php setup.php
if errorlevel 1 (
 pause
 exit /b 1
)
php artisan serve
pause
