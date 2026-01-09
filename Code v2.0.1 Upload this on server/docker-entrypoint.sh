#!/bin/bash
set -e

echo "🚀 Starting Taskify initialization..."

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL database..."
until php -r "try { \$pdo = new PDO('mysql:host=taskify_db;port=3306', '${DB_USERNAME}', '${DB_PASSWORD}'); echo 'MySQL is ready\n'; exit(0); } catch (PDOException \$e) { exit(1); }" 2>/dev/null; do
  echo "MySQL is unavailable - sleeping"
  sleep 2
done

echo "✅ MySQL is ready!"

# Change to Laravel directory
cd /var/www/html

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:generate-via-artisan" ]; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force || echo "⚠️  Key generation skipped (may already exist)"
else
    echo "✅ APP_KEY already set"
fi

# Run migrations
echo "📦 Running database migrations..."
php artisan migrate --force || echo "⚠️  Migrations may have failed or already run"

# Clear and cache config
echo "🔄 Optimizing Laravel..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Set permissions
echo "🔐 Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

echo "✅ Taskify initialization complete!"
echo "🌐 Starting services..."

# Start supervisor (which runs PHP-FPM and Nginx)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

