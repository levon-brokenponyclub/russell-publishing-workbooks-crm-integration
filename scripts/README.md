# Deployment System for DTR Workbooks CRM Integration

This directory contains automated deployment scripts for syncing the DTR Workbooks CRM Integration plugin to multiple environments.

## Features

- ✅ **Automatic sync on git commit** via post-commit hooks
- ✅ **Manual deployment** to specific environments
- ✅ **Local and remote deployment** support
- ✅ **Backup creation** before deployment
- ✅ **Version tracking** and deployment logs
- ✅ **Pre-deployment testing** (PHP syntax, file structure)
- ✅ **Multiple environment support** (staging, production, backup)
- ✅ **Exclude sensitive files** (.git, logs, etc.)
- ✅ **Deployment notifications** (macOS notifications, optional Slack)

## Quick Setup

1. **Run the setup script:**
   ```bash
   cd scripts/
   chmod +x setup.sh
   ./setup.sh
   ```

2. **Configure your environments:**
   ```bash
   cp config.example.sh config.sh
   nano config.sh  # Edit with your paths and settings
   ```

3. **Test deployment:**
   ```bash
   ./deploy.sh staging
   ```

## Usage

### Manual Deployment

```bash
# Deploy to staging
./deploy.sh staging

# Deploy to production
./deploy.sh production

# Deploy to all environments
./deploy.sh all

# Deploy specific version
./deploy.sh production 1.4.3
```

### Automatic Deployment

The post-commit hook automatically syncs changes after each git commit:

```bash
git add .
git commit -m "feat: add new feature"
# Automatically syncs to configured destinations
```

## Configuration

Edit `config.sh` to customize:

```bash
# Local destinations
LOCAL_STAGING="/path/to/staging/wp-content/plugins/dtr-workbooks-crm-integration"
LOCAL_PRODUCTION="/path/to/production/wp-content/plugins/dtr-workbooks-crm-integration"

# Remote destinations (SSH)
REMOTE_STAGING="user@staging.example.com:/var/www/staging/wp-content/plugins/dtr-workbooks-crm-integration"
REMOTE_PRODUCTION="user@production.example.com:/var/www/html/wp-content/plugins/dtr-workbooks-crm-integration"
```

## Files Included

- **`setup.sh`** - Initial setup and configuration
- **`deploy.sh`** - Main deployment script
- **`post-commit-sync.sh`** - Git post-commit hook for automatic sync
- **`config.example.sh`** - Example configuration file
- **`README.md`** - This documentation

## Environment Examples

### Local Development to Staging
```bash
# Sync to local staging environment
./deploy.sh staging
```

### Production Deployment
```bash
# Deploy to production (requires version tag)
git tag v1.4.3
git push origin v1.4.3
./deploy.sh production
```

### Backup Creation
```bash
# Create backup copy
./deploy.sh backup
```

## Security Features

- **SSH key authentication** for remote deployments
- **File exclusions** prevent sensitive files from being deployed
- **Version validation** for production deployments
- **Backup creation** before overwriting existing installations
- **Clean git requirement** ensures no uncommitted changes

## Logging

All deployments are logged to:
- `../logs/sync-YYYYMMDD.log` - Daily sync logs
- Console output with colored status messages
- Deployment info JSON files in destination directories

## Troubleshooting

### SSH Connection Issues
```bash
# Test SSH connection
ssh user@your-server.com

# Set up SSH key authentication
ssh-copy-id user@your-server.com
```

### Permission Issues
```bash
# Make scripts executable
chmod +x scripts/*.sh

# Fix directory permissions
chmod 755 scripts/
```

### Missing Dependencies
```bash
# Install rsync (macOS)
brew install rsync

# Install rsync (Ubuntu/Debian)
sudo apt-get install rsync
```

## Advanced Usage

### Custom Hooks

Add pre/post deployment commands in `config.sh`:

```bash
PRE_DEPLOY_HOOKS=(
    "php vendor/bin/phpstan analyse"
    "npm run build"
)

POST_DEPLOY_HOOKS=(
    "wp plugin activate dtr-workbooks-crm-integration"
    "wp cache flush"
)
```

### GitHub Actions

For automated cloud deployments, use the included `.github/workflows/deploy.yml`:

1. Push to `staging` branch → auto-deploy to staging
2. Create version tag → auto-deploy to production
3. Automatic testing before deployment

### Environment Variables

Set environment variables for automated deployments:

```bash
export DEPLOY_ENV=staging
export PLUGIN_VERSION=1.4.3
./deploy.sh
```

## Support

For issues with the deployment system:

1. Check the logs in `../logs/`
2. Verify SSH connections and permissions
3. Test with a staging environment first
4. Ensure all dependencies are installed

## License

This deployment system is part of the DTR Workbooks CRM Integration plugin by Supersonic Playground.
