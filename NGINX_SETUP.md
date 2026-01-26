# Nginx Setup on Host (Non-Containerized)

## Installation

```bash
# Install nginx
sudo apt update
sudo apt install -y nginx

# Create directories
sudo mkdir -p /etc/nginx/sites-available
sudo mkdir -p /etc/nginx/sites-enabled
```

## Configuration

1. **Copy nginx main config**:
```bash
cd ~/saas-platform
sudo cp nginx/nginx.conf /etc/nginx/nginx.conf
```

2. **Copy site configuration**:
```bash
sudo cp nginx/saas-platform.conf /etc/nginx/sites-available/saas-platform.conf
```

3. **Enable site**:
```bash
sudo ln -sf /etc/nginx/sites-available/saas-platform.conf /etc/nginx/sites-enabled/saas-platform.conf
```

4. **Test configuration**:
```bash
sudo nginx -t
```

5. **Reload nginx**:
```bash
sudo systemctl reload nginx
```

## SSL Setup

1. Install certbot:
```bash
sudo apt install -y certbot python3-certbot-nginx
```

2. Get SSL certificate:
```bash
sudo certbot --nginx -d urielsi.ca -d www.urielsi.ca
```

3. Uncomment HTTPS server block in `/etc/nginx/sites-available/saas-platform.conf`

4. Reload nginx:
```bash
sudo systemctl reload nginx
```

## Service Management

```bash
# Start nginx
sudo systemctl start nginx

# Stop nginx
sudo systemctl stop nginx

# Restart nginx
sudo systemctl restart nginx

# Reload configuration
sudo systemctl reload nginx

# Check status
sudo systemctl status nginx
```

