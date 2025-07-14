# DTR Workbooks CRM Integration - Deployment Configuration
# Customized for your environment

# Plugin Information
PLUGIN_NAME="dtr-workbooks-crm-integration"
PLUGIN_VERSION="1.4.3"

# Source directory (current plugin directory)
SOURCE_DIR="/Users/levongravett/Desktop/BPC/Sites/drug-target-review-final-api/app/public/wp-content/plugins/dtr-workbooks-crm-integration"

# Local deployment destinations
LOCAL_STAGING="/Users/levongravett/Desktop/BPC/Sites/drug-target-review-final-api/app/public/wp-content/plugins/dtr-workbooks-crm-integration"
LOCAL_PRODUCTION="/Users/levongravett/Desktop/BPC/Sites/drug-target-review/app/public/wp-content/plugins/dtr-workbooks-crm-integration"
LOCAL_BACKUP="/Users/levongravett/Desktop/BPC/Sites/drug-target-review-final-api/app/public/wp-content/plugins/dtr-workbooks-crm-integration-backup"

# Remote deployment destinations (SSH format: user@host:/path)
REMOTE_STAGING="dtrstagingsite@dtrstagingsite.ssh.wpengine.net:/nas/content/live/dtrstagingsite/wp-content/plugins/dtr-workbooks-crm-integration"
REMOTE_PRODUCTION="drugtrgtrevie@drugtrgtrevie.ssh.wpengine.net:/nas/content/live/drugtrgtrevie/wp-content/plugins/dtr-workbooks-crm-integration"  # Production WPEngine server

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
NOTIFICATION_EMAIL=""  # Add your email if you want notifications
SLACK_WEBHOOK_URL=""   # Optional: Slack notifications

# WordPress settings for online deployment
WP_CLI_PATH="/usr/local/bin/wp"  # Path to WP-CLI if installed
WP_STAGING_PATH="/nas/content/live/dtrstagingsite"
WP_PRODUCTION_PATH=""  # Add when you have production server

# Security settings
REQUIRE_VERSION_TAG=false  # Set to true for production-only tagged versions
REQUIRE_TESTS_PASS=true
REQUIRE_CLEAN_GIT=false    # Set to true if you want to require clean git state

# Deployment hooks (commands to run before/after deployment)
PRE_DEPLOY_HOOKS=(
    # "php -l dtr-workbooks-crm-integration.php"  # PHP syntax check
)

POST_DEPLOY_HOOKS=(
    # Commands to run after deployment (if WP-CLI is available)
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
        REQUIRE_VERSION_TAG=false  # Set to true when ready for production
        ;;
    "backup")
        DEPLOY_PATH="$LOCAL_BACKUP"
        REMOTE_PATH=""
        WP_PATH=""
        ;;
esac
