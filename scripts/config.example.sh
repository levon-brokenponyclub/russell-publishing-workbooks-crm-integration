# DTR Workbooks CRM Integration - Deployment Configuration
# Copy this file to config.sh and customize for your environment

# Plugin Information
PLUGIN_NAME="dtr-workbooks-crm-integration"
PLUGIN_VERSION="1.4.3"

# Source directory (usually the current plugin directory)
SOURCE_DIR="/Users/levongravett/Desktop/BPC/Sites/drug-target-review-final-api/app/public/wp-content/plugins/dtr-workbooks-crm-integration"

# Local deployment destinations
# Add or modify these paths according to your local setup
LOCAL_STAGING="/Users/levongravett/Sites/staging/wp-content/plugins/dtr-workbooks-crm-integration"
LOCAL_PRODUCTION="/Users/levongravett/Sites/production/wp-content/plugins/dtr-workbooks-crm-integration"
LOCAL_BACKUP="/Users/levongravett/Backups/plugins/dtr-workbooks-crm-integration"

# Remote deployment destinations (SSH format: user@host:/path)
REMOTE_STAGING="staging@yourdomain.com:/var/www/staging/wp-content/plugins/dtr-workbooks-crm-integration"
REMOTE_PRODUCTION="production@yourdomain.com:/var/www/html/wp-content/plugins/dtr-workbooks-crm-integration"

# FTP/SFTP settings (if using FTP instead of SSH)
FTP_HOST="ftp.yourdomain.com"
FTP_USER="your-ftp-username"
FTP_PASS="your-ftp-password"  # Better to use SSH keys instead
FTP_PATH="/public_html/wp-content/plugins/dtr-workbooks-crm-integration"

# Files and directories to exclude from deployment
EXCLUDE_PATTERNS=(
    ".git*"
    "logs/"
    "*.log"
    "*.tmp"
    ".DS_Store"
    "node_modules/"
    "scripts/"
    "*.md"
    "config.sh"
    "config.example.sh"
    ".github/"
    "tests/"
    "*.zip"
    "*.tar.gz"
)

# Backup settings
CREATE_BACKUPS=true
BACKUP_RETENTION_DAYS=30

# Notification settings
ENABLE_NOTIFICATIONS=true
NOTIFICATION_EMAIL="admin@yourdomain.com"
SLACK_WEBHOOK_URL=""  # Optional: Slack notifications

# WordPress settings for online deployment
WP_CLI_PATH="/usr/local/bin/wp"  # Path to WP-CLI
WP_STAGING_PATH="/var/www/staging"
WP_PRODUCTION_PATH="/var/www/html"

# Database settings (for migrations if needed)
DB_STAGING_HOST="localhost"
DB_STAGING_NAME="staging_db"
DB_STAGING_USER="staging_user"
DB_STAGING_PASS="staging_password"

DB_PRODUCTION_HOST="localhost"
DB_PRODUCTION_NAME="production_db"
DB_PRODUCTION_USER="production_user"
DB_PRODUCTION_PASS="production_password"

# Security settings
REQUIRE_VERSION_TAG=false  # Set to true to only deploy tagged versions
REQUIRE_TESTS_PASS=true
REQUIRE_CLEAN_GIT=true     # Require no uncommitted changes

# Deployment hooks (commands to run before/after deployment)
PRE_DEPLOY_HOOKS=(
    # "php vendor/bin/phpstan analyse"
    # "npm run build"
)

POST_DEPLOY_HOOKS=(
    # "wp plugin activate dtr-workbooks-crm-integration"
    # "wp cache flush"
)

# Environment-specific settings
case "$DEPLOY_ENV" in
    "staging")
        DEPLOY_PATH="$LOCAL_STAGING"
        REMOTE_PATH="$REMOTE_STAGING"
        WP_PATH="$WP_STAGING_PATH"
        ;;
    "production")
        DEPLOY_PATH="$LOCAL_PRODUCTION"
        REMOTE_PATH="$REMOTE_PRODUCTION"
        WP_PATH="$WP_PRODUCTION_PATH"
        REQUIRE_VERSION_TAG=true
        ;;
    "backup")
        DEPLOY_PATH="$LOCAL_BACKUP"
        REMOTE_PATH=""
        WP_PATH=""
        ;;
esac
