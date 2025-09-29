@echo off
echo Installing PHP dependencies using Composer...
composer install
if %errorlevel% neq 0 (
    echo Composer install failed. Please make sure Composer is installed on your system.
    echo You can download Composer from https://getcomposer.org/download/
    echo After installing Composer, run this script again.
    pause
    exit /b 1
)
echo Dependencies installed successfully!

echo Creating uploads directory structure...
if not exist uploads mkdir uploads
if not exist uploads\vouchers mkdir uploads\vouchers
echo Directory structure created successfully!

echo Setup completed successfully!
pause 