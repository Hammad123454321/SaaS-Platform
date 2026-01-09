# Fix PHP Extensions for Taskify

## Issue
Composer is failing because PHP extensions are missing: `zip`, `fileinfo`, and `exif`.

## Solution

### Step 1: Locate php.ini
The error shows your php.ini is at: `C:\tools\php85\php.ini`

### Step 2: Enable Extensions

Open `C:\tools\php85\php.ini` in a text editor (as Administrator) and find these lines:

```ini
;extension=zip
;extension=fileinfo
;extension=exif
```

**Remove the semicolons** to uncomment them:

```ini
extension=zip
extension=fileinfo
extension=exif
```

### Step 3: Verify Extensions

Run:
```powershell
php -m | Select-String -Pattern "zip|fileinfo|exif"
```

You should see all three extensions listed.

### Step 4: Retry Composer Install

```powershell
composer install
```

---

## Alternative: If Extensions Don't Exist

If the extensions aren't available, you may need to:

1. **Download PHP extensions DLLs** (if using Windows)
2. **Or use Docker** (recommended - see below)

---

## PHP Version Issue

You have PHP 8.5.1, but some packages only support up to 8.4.

### Option 1: Use PHP 8.4 (Recommended)

Download PHP 8.4 from https://windows.php.net/download/ and use that instead.

### Option 2: Ignore Platform Requirements (Quick Fix for Testing)

```powershell
composer install --ignore-platform-req=php --ignore-platform-req=ext-zip --ignore-platform-req=ext-fileinfo --ignore-platform-req=ext-exif
```

**Note**: This is only for local testing. Production should use proper PHP version.

---

## Recommended: Use Docker Instead

For local testing, using Docker is easier and avoids these issues:

```bash
# Use the Docker setup from docker-compose.prod.yml
docker-compose -f docker-compose.prod.yml up taskify
```

This will handle all PHP extensions automatically.







