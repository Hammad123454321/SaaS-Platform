# Verify Taskify Database Connection

## MySQL Container Started

MySQL is now running in Docker. Verify your `.env` file has these settings:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dev_taskify
DB_USERNAME=taskify
DB_PASSWORD=taskify123
```

**OR if using root:**
```env
DB_USERNAME=root
DB_PASSWORD=root123
```

## Test Connection

```powershell
cd "Code v2.0.1 Upload this on server"
php artisan migrate
```

## If Connection Still Fails

1. **Wait 30 seconds** - MySQL needs time to initialize
2. **Check container logs:**
   ```powershell
   docker logs taskify_mysql
   ```
3. **Verify container is running:**
   ```powershell
   docker ps | Select-String "taskify_mysql"
   ```

## Alternative: Use Root User

If the `taskify` user doesn't work, use root:
```env
DB_USERNAME=root
DB_PASSWORD=root123
```

















