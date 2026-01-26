# CI/CD Deployment Setup Guide

This guide will help you set up automated deployment to your VPS.

## Quick Start

### 1. VPS Initial Setup

SSH into your VPS and install Docker:

```bash
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Create directories
mkdir -p ~/saas-platform/nginx/ssl
```

### 2. Pull Code to VPS

**For CI/CD deployment:** Code is automatically synced when you push to main branch.

**For manual deployment:**
```bash
cd ~
git clone <your-repo-url> saas-platform
cd saas-platform
```

### 3. Create Environment Files

**IMPORTANT:** Create environment files AFTER code is pulled to VPS.

**Option 1: Single root .env file (Recommended for Docker)**
```bash
cd ~/saas-platform
cp .env.example .env
nano .env
```

Docker Compose will automatically pass these variables to containers. Update all values:
- Set `NEXT_PUBLIC_API_BASE_URL` to your domain (e.g., `https://yourdomain.com`)
- Update all secrets, API keys, and database credentials

**Option 2: Separate files (For local development or non-Docker)**
```bash
# Backend
cd ~/saas-platform/backend
cp ../.env.example .env
nano .env

# Frontend  
cd ~/saas-platform/frontend
cp ../.env.example .env.local
nano .env.local
```

**Note:** Docker Compose reads from root `.env` file and passes variables to containers. The `backend/.env` and `frontend/.env.local` files are only needed if running services directly (not in Docker).

### 4. Configure GitHub Secrets

Go to: **GitHub Repository → Settings → Secrets and variables → Actions**

Add these secrets:

| Secret Name | Description | Example |
|------------|-------------|---------|
| `VPS_HOST` | Your VPS IP or domain | `192.168.1.100` or `app.yourdomain.com` |
| `VPS_USER` | SSH username | `root` or `ubuntu` |
| `VPS_PASSWORD` | VPS password | `your_password_here` |
| `VPS_SSH_KEY` | (Optional) Private SSH key | Content of `~/.ssh/id_rsa` |

**Note:** Use either `VPS_PASSWORD` OR `VPS_SSH_KEY`, not both. SSH key is more secure.

### 5. Deploy

Push to `main` or `master` branch:

```bash
git push origin main
```

The CI/CD pipeline will automatically:
1. Pull code to VPS
2. Build Docker images
3. Start containers
4. Verify deployment

## Architecture

```
Internet
   │
   ▼
┌─────────────┐
│   Nginx     │ :80, :443
│  (Reverse   │
│   Proxy)    │
└──────┬──────┘
       │
   ┌───┴────┐
   │        │
┌──▼───┐ ┌──▼────┐
│Frontend│ │Backend│
│ :3000  │ │ :8000 │
└───────┘ └───┬───┘
              │
         ┌────▼────┐
         │   DB    │
         │ :5432   │
         └─────────┘
```

## API Versioning

All API endpoints are versioned:

- **Base URL:** `https://yourdomain.com/api/v1`
- **Example:** `https://yourdomain.com/api/v1/auth/login`
- **Health Check:** `https://yourdomain.com/api/v1/health`

The frontend automatically uses the versioned API path.

## Manual Deployment

If you need to deploy manually:

```bash
# SSH into VPS
ssh user@your-vps

# Navigate to deployment directory
cd ~/saas-platform

# Deploy
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Check status
docker-compose ps
docker-compose logs -f
```

## Troubleshooting

### Check Service Status
```bash
docker-compose ps
docker-compose logs backend
docker-compose logs frontend
docker-compose logs nginx
```

### Restart Services
```bash
docker-compose restart
```

### View Logs
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f backend
```

### Database Access
```bash
docker-compose exec db psql -U saas -d saas_production
```

### Rollback
```bash
cd ~/saas-platform
git checkout <previous-commit-hash>
docker-compose down
docker-compose build
docker-compose up -d
```

## SSL/HTTPS Setup

1. Obtain SSL certificates (Let's Encrypt recommended)
2. Place certificates in `~/saas-platform/nginx/ssl/`:
   - `cert.pem` (certificate)
   - `key.pem` (private key)
3. Uncomment HTTPS server block in `nginx/nginx.conf`
4. Update `server_name` with your domain
5. Restart nginx: `docker-compose restart nginx`

## Monitoring

### Health Checks
```bash
# Backend
curl http://yourdomain.com/api/v1/health

# Should return: {"status":"ok"}
```

### Resource Usage
```bash
docker stats
```

## Security Checklist

- [ ] Changed all default passwords
- [ ] Using strong JWT secrets (32+ characters)
- [ ] SSL/HTTPS configured
- [ ] Firewall configured (ports 22, 80, 443 only)
- [ ] SSH key authentication (preferred over password)
- [ ] Regular backups configured
- [ ] Environment variables secured

## Support

For issues, check:
1. Container logs
2. Nginx error logs
3. GitHub Actions workflow logs
4. VPS system resources (disk, memory, CPU)

