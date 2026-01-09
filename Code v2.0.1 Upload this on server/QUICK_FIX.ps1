# Quick Fix Script for Composer Install Issues
# This script helps you fix PHP extension and version issues

Write-Host "🔧 Fixing PHP Extensions for Taskify..." -ForegroundColor Cyan

# Check current PHP version
$phpVersion = php -r "echo PHP_VERSION;"
Write-Host "`n📋 Current PHP Version: $phpVersion" -ForegroundColor Yellow

# Check if extensions are loaded
Write-Host "`n🔍 Checking PHP Extensions..." -ForegroundColor Cyan
php -m | Select-String -Pattern "zip|fileinfo|exif"

Write-Host "`n💡 Solution Options:" -ForegroundColor Green
Write-Host "`n1️⃣  QUICK FIX (For Testing Only):" -ForegroundColor Yellow
Write-Host "   Run this command to ignore platform requirements:" -ForegroundColor White
Write-Host "   composer install --ignore-platform-req=php --ignore-platform-req=ext-zip --ignore-platform-req=ext-fileinfo --ignore-platform-req=ext-exif" -ForegroundColor Cyan

Write-Host "`n2️⃣  PROPER FIX (Enable Extensions):" -ForegroundColor Yellow
Write-Host "   Edit: C:\tools\php85\php.ini" -ForegroundColor White
Write-Host "   Find and uncomment (remove ;):" -ForegroundColor White
Write-Host "   - extension=zip" -ForegroundColor Cyan
Write-Host "   - extension=fileinfo" -ForegroundColor Cyan
Write-Host "   - extension=exif" -ForegroundColor Cyan
Write-Host "   Then restart terminal and run: composer install" -ForegroundColor White

Write-Host "`n3️⃣  BEST OPTION (Use Docker):" -ForegroundColor Yellow
Write-Host "   Use Docker to avoid PHP version/extensions issues:" -ForegroundColor White
Write-Host "   docker-compose -f docker-compose.prod.yml up taskify" -ForegroundColor Cyan

Write-Host "`n⚠️  Note: PHP 8.5.1 may have compatibility issues." -ForegroundColor Red
Write-Host "   Consider using PHP 8.4 or Docker for best compatibility." -ForegroundColor Yellow







