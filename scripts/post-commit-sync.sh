#!/bin/bash
# Post-commit sync script for DTR Workbooks CRM Integration
# This script syncs the plugin to multiple locations after each commit

# Configuration
PLUGIN_NAME="dtr-workbooks-crm-integration"
SOURCE_DIR="/Users/levongravett/Desktop/BPC/Sites/drug-target-review-final-api/app/public/wp-content/plugins/$PLUGIN_NAME"

# Local sync destinations (add your paths here)
LOCAL_DESTINATIONS=(
    "/path/to/staging/wp-content/plugins/$PLUGIN_NAME"
    "/path/to/production/wp-content/plugins/$PLUGIN_NAME"
    "/path/to/backup/plugins/$PLUGIN_NAME"
)

# Remote destinations (SSH/SCP paths)
REMOTE_DESTINATIONS=(
    "user@staging-server.com:/var/www/html/wp-content/plugins/$PLUGIN_NAME"
    "user@production-server.com:/var/www/html/wp-content/plugins/$PLUGIN_NAME"
)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}🚀 Starting post-commit sync for $PLUGIN_NAME...${NC}"

# Get the latest commit info
COMMIT_HASH=$(git rev-parse HEAD)
COMMIT_MESSAGE=$(git log -1 --pretty=%B)
COMMIT_AUTHOR=$(git log -1 --pretty=%an)
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo -e "${GREEN}📝 Commit: $COMMIT_HASH${NC}"
echo -e "${GREEN}📝 Message: $COMMIT_MESSAGE${NC}"
echo -e "${GREEN}👤 Author: $COMMIT_AUTHOR${NC}"
echo -e "${GREEN}⏰ Time: $TIMESTAMP${NC}"

# Create sync log
LOG_FILE="$SOURCE_DIR/logs/sync-$(date +%Y%m%d).log"
mkdir -p "$SOURCE_DIR/logs"

echo "[$TIMESTAMP] Starting sync for commit $COMMIT_HASH" >> "$LOG_FILE"

# Function to sync to local destinations
sync_local() {
    local dest=$1
    echo -e "${YELLOW}📁 Syncing to local: $dest${NC}"
    
    if [ ! -d "$(dirname "$dest")" ]; then
        echo -e "${RED}❌ Parent directory doesn't exist: $(dirname "$dest")${NC}"
        echo "[$TIMESTAMP] ERROR: Parent directory doesn't exist: $(dirname "$dest")" >> "$LOG_FILE"
        return 1
    fi
    
    # Create destination if it doesn't exist
    mkdir -p "$dest"
    
    # Sync files excluding git, logs, and temp files
    rsync -av --delete \
        --exclude='.git' \
        --exclude='logs/' \
        --exclude='*.tmp' \
        --exclude='*.log' \
        --exclude='.DS_Store' \
        --exclude='node_modules/' \
        "$SOURCE_DIR/" "$dest/"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Local sync successful: $dest${NC}"
        echo "[$TIMESTAMP] SUCCESS: Local sync to $dest" >> "$LOG_FILE"
        
        # Create sync info file
        echo "{
    \"last_sync\": \"$TIMESTAMP\",
    \"commit_hash\": \"$COMMIT_HASH\",
    \"commit_message\": \"$COMMIT_MESSAGE\",
    \"commit_author\": \"$COMMIT_AUTHOR\",
    \"plugin_version\": \"1.4.3\"
}" > "$dest/sync-info.json"
    else
        echo -e "${RED}❌ Local sync failed: $dest${NC}"
        echo "[$TIMESTAMP] ERROR: Local sync failed to $dest" >> "$LOG_FILE"
    fi
}

# Function to sync to remote destinations
sync_remote() {
    local dest=$1
    echo -e "${YELLOW}🌐 Syncing to remote: $dest${NC}"
    
    # Create temporary directory for clean sync
    TEMP_DIR="/tmp/${PLUGIN_NAME}_sync_$(date +%s)"
    mkdir -p "$TEMP_DIR"
    
    # Copy files to temp directory (excluding sensitive files)
    rsync -av \
        --exclude='.git' \
        --exclude='logs/' \
        --exclude='*.tmp' \
        --exclude='*.log' \
        --exclude='.DS_Store' \
        --exclude='node_modules/' \
        --exclude='scripts/' \
        "$SOURCE_DIR/" "$TEMP_DIR/"
    
    # Create sync info file
    echo "{
    \"last_sync\": \"$TIMESTAMP\",
    \"commit_hash\": \"$COMMIT_HASH\",
    \"commit_message\": \"$COMMIT_MESSAGE\",
    \"commit_author\": \"$COMMIT_AUTHOR\",
    \"plugin_version\": \"1.4.3\"
}" > "$TEMP_DIR/sync-info.json"
    
    # Sync to remote using rsync over SSH
    rsync -avz --delete \
        -e "ssh -o StrictHostKeyChecking=no" \
        "$TEMP_DIR/" "$dest/"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Remote sync successful: $dest${NC}"
        echo "[$TIMESTAMP] SUCCESS: Remote sync to $dest" >> "$LOG_FILE"
    else
        echo -e "${RED}❌ Remote sync failed: $dest${NC}"
        echo "[$TIMESTAMP] ERROR: Remote sync failed to $dest" >> "$LOG_FILE"
    fi
    
    # Clean up temp directory
    rm -rf "$TEMP_DIR"
}

# Sync to all local destinations
echo -e "${YELLOW}📂 Starting local sync...${NC}"
for dest in "${LOCAL_DESTINATIONS[@]}"; do
    sync_local "$dest"
done

# Sync to all remote destinations
echo -e "${YELLOW}🌍 Starting remote sync...${NC}"
for dest in "${REMOTE_DESTINATIONS[@]}"; do
    sync_remote "$dest"
done

# Send notification (optional)
if command -v osascript &> /dev/null; then
    osascript -e "display notification \"Plugin synced successfully\" with title \"DTR Workbooks CRM\" sound name \"Glass\""
fi

echo -e "${GREEN}🎉 Sync complete! Check $LOG_FILE for details.${NC}"
echo "[$TIMESTAMP] Sync process completed" >> "$LOG_FILE"
