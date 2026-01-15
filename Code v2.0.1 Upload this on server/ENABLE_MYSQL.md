# Enable MySQL PDO Extension

## Error
```
could not find driver (Connection: mysql)
```

This means PHP's MySQL PDO extension is not enabled.

## Solution

### Step 1: Open php.ini
Open: `C:\tools\php85\php.ini` (as Administrator)

### Step 2: Enable PDO MySQL Extension

Find this line (may be commented with `;`):
```ini
;extension=pdo_mysql
```

Remove the semicolon:
```ini
extension=pdo_mysql
```

### Step 3: Save and Verify

1. Save the file
2. Restart terminal
3. Verify: `php -m | Select-String -Pattern "pdo_mysql"`
4. Should output: `pdo_mysql`

### Step 4: Retry Migration

```powershell
php artisan migrate
```

---

## Alternative: If Extension File Doesn't Exist

If `pdo_mysql` extension file is missing, you may need to:

1. Download PHP extensions for Windows
2. Or use Docker (recommended - avoids all extension issues)

---

## Quick Check Command

```powershell
php -m | Select-String -Pattern "pdo|mysql"
```

You should see:
- `pdo`
- `pdo_mysql`

If not, enable them in php.ini.

















