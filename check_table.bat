@echo off
:loop
php artisan db:check-table
timeout /t 0.1 /nobreak >nul
goto loop
