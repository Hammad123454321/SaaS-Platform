# Enable GD Extension

## Step 1: Open php.ini
Open: `C:\tools\php85\php.ini` (as Administrator)

## Step 2: Find and Enable GD Extension

Look for this line (may be commented with `;`):
```ini
;extension=gd
```

Remove the semicolon:
```ini
extension=gd
```

## Step 3: Save and Verify

1. Save the file
2. Restart terminal
3. Verify: `php -m | Select-String -Pattern "gd"`
4. Should output: `gd`

## Step 4: Run Composer with Platform Requirements Ignored

Since PHP 8.5.1 is newer than what packages support, use:

```powershell
composer install --ignore-platform-req=php --ignore-platform-req=ext-gd
```

This will install dependencies for local testing.

















