# Deployment Guide

This guide explains how to deploy the SaaS Platform to a VPS using Docker and CI/CD.

## Prerequisites

1. **VPS Requirements:**
   - Ubuntu 20.04+ or similar Linux distribution
   - Docker and Docker Compose installed
   - SSH access with password or key
   - At least 2GB RAM, 2 CPU cores, 20GB storage

2. **GitHub Secrets:**
   - `VPS_HOST`: Your VPS IP address or domain
   - `VPS_USER`: SSH username (usually `root` or a sudo user)
   - `VPS_SSH_KEY`: Private SSH key for authentication (or use password)
   - `VPS_PASSWORD`: VPS password (if using password authentication)

## Setup Instructions

### 1. Install Docker on VPS

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Add user to docker group (if not root)
sudo usermod -aG docker $USER
```

### 2. Configure GitHub Secrets

Go to your GitHub repository → Settings → Secrets and variables → Actions, and add:

- `VPS_HOST`: Your VPS IP or domain (e.g., `192.168.1.100` or `urielsi.ca`)
- `VPS_USER`: SSH username (e.g., `root` or `ubuntu`)
- `VPS_SSH_KEY`: Your private SSH key content (for key-based auth)
- `VPS_PASSWORD`: Your VPS password (for password-based auth)

**Note:** Use either SSH key OR password, not both.

### 3. Create Environment File on VPS

SSH into your VPS and create the production environment file:

```bash
mkdir -p ~/saas-platform
cd ~/saas-platform
nano .env.production
```

Copy the contents from `.env.production.example` and fill in your values.

### 4. Configure Nginx (Optional - for custom domain)

If you have a domain, update `nginx/nginx.conf`:
- Uncomment HTTPS server block
- Update `server_name` with your domain
- Place SSL certificates in `nginx/ssl/` directory

### 5. Deploy

The CI/CD pipeline will automatically deploy when you push to `main` or `master` branch.

**Manual deployment:**
```bash
# On VPS
cd ~/saas-platform
docker-compose down
docker-compose build
docker-compose up -d
```

## API Versioning

All API endpoints are now versioned under `/api/v1/`:

- **Before:** `http://urielsi.ca/auth/login`
- **After:** `https://urielsi.ca/api/v1/auth/login`

The frontend automatically uses the versioned API path.

## Architecture

```
┌─────────────────┐
│   Nginx (80/443) │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
┌───▼───┐ ┌──▼────┐
│Frontend│ │Backend│
│ :3000  │ │ :8000 │
└────┬───┘ └───┬───┘
     │         │
     └────┬────┘
          │
     ┌────▼────┐
     │   DB    │
     │ :5432   │
     └─────────┘
```

## Services

- **Nginx**: Reverse proxy, serves frontend and routes `/api/v1/*` to backend
- **Frontend**: Next.js application (standalone build)
- **Backend**: FastAPI application with versioned API
- **Database**: PostgreSQL 16

## Monitoring

Check service health:
```bash
# Backend health
curl https://urielsi.ca/api/v1/health

# Container status
docker-compose ps

# View logs
docker-compose logs -f
```

## Troubleshooting

### Backend not starting
```bash
docker-compose logs backend
```

### Frontend not loading
```bash
docker-compose logs frontend
```

### Database connection issues
```bash
docker-compose exec db psql -U saas -d saas_production
```

### Nginx errors
```bash
docker-compose exec nginx nginx -t
docker-compose logs nginx
```

## Rollback

If deployment fails, rollback to previous version:

```bash
cd ~/saas-platform
# List backups
ls -la backup-*

# Restore from backup
cd backup-YYYYMMDD-HHMMSS
docker-compose up -d
```

## Security Notes

1. **Change default passwords** in `.env.production`
2. **Use strong JWT secrets** (minimum 32 characters)
3. **Enable HTTPS** by configuring SSL certificates
4. **Restrict SSH access** to specific IPs
5. **Keep Docker updated**: `sudo apt update && sudo apt upgrade docker.io`

## Maintenance

### Update application
Push to `main` branch - CI/CD will automatically deploy.

### Update dependencies
```bash
# Backend
cd backend
pip install --upgrade -r requirements.txt

# Frontend
cd frontend
npm update
```

### Backup database
```bash
docker-compose exec db pg_dump -U saas saas_production > backup.sql
```

### Restore database
```bash
docker-compose exec -T db psql -U saas saas_production < backup.sql
```

