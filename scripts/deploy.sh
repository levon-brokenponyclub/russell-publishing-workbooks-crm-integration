#!/bin/bash
# DTR Workbooks CRM Integration - Deployment Script
# Usage: ./deploy.sh [environment] [version]
# Examples:
#   ./deploy.sh staging
#   ./deploy.sh production 1.4.3
#   ./deploy.sh all

set -e  # Exit on any error

# Configuration
PLUGIN_NAME="dtr-workbooks-crm-integration"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(dirname "$SCRIPT_DIR")"
VERSION="${2:-$(grep "Version:" "$SOURCE_DIR/dtr-workbooks-crm-integration.php" | awk '{print $3}')}"
ENVIRONMENT="${1:-staging}"

# Load configuration
source "$SCRIPT_DIR/config.sh"

# Environment configurations
declare -A ENVIRONMENTS
ENVIRONMENTS[staging]="$LOCAL_STAGING"
ENVIRONMENTS[production]="$LOCAL_PRODUCTION"
ENVIRONMENTS[backup]="$LOCAL_BACKUP"
ENVIRONMENTS[remote-staging]="$REMOTE_STAGING"
ENVIRONMENTS[remote-production]="$REMOTE_PRODUCTION"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Functions
print_header() {
    echo -e "${BLUE}"
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║                  DTR Workbooks CRM Deploy                   ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ️  $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Validate source directory
validate_source() {
    if [ ! -f "$SOURCE_DIR/dtr-workbooks-crm-integration.php" ]; then
        print_error "Source plugin file not found: $SOURCE_DIR/dtr-workbooks-crm-integration.php"
        exit 1
    fi
    
    if [ ! -d "$SOURCE_DIR/includes" ]; then
        print_error "Includes directory not found: $SOURCE_DIR/includes"
        exit 1
    fi
}

# Create deployment package
create_package() {
    local package_dir="/tmp/${PLUGIN_NAME}_deploy_$(date +%s)"
    print_info "Creating deployment package in $package_dir"
    
    mkdir -p "$package_dir"
    
    # Copy files with exclusions
    rsync -av \
        --exclude='.git*' \
        --exclude='logs/' \
        --exclude='*.log' \
        --exclude='*.tmp' \
        --exclude='.DS_Store' \
        --exclude='node_modules/' \
        --exclude='scripts/' \
        --exclude='*.md' \
        "$SOURCE_DIR/" "$package_dir/"
    
    # Create deployment info
    cat > "$package_dir/deployment-info.json" << EOF
{
    "plugin_name": "$PLUGIN_NAME",
    "version": "$VERSION",
    "deployed_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "deployed_by": "$(whoami)",
    "environment": "$ENVIRONMENT",
    "git_commit": "$(cd "$SOURCE_DIR" && git rev-parse HEAD 2>/dev/null || echo 'unknown')",
    "git_branch": "$(cd "$SOURCE_DIR" && git branch --show-current 2>/dev/null || echo 'unknown')"
}
EOF
    
    echo "$package_dir"
}

# Deploy to local environment
deploy_local() {
    local env_name=$1
    local destination=${ENVIRONMENTS[$env_name]}
    
    if [ -z "$destination" ]; then
        print_error "Unknown environment: $env_name"
        return 1
    fi
    
    print_info "Deploying to local environment: $env_name ($destination)"
    
    # Create backup if destination exists
    if [ -d "$destination" ]; then
        backup_dir="${destination}_backup_$(date +%Y%m%d_%H%M%S)"
        print_info "Creating backup: $backup_dir"
        cp -r "$destination" "$backup_dir"
    fi
    
    # Create destination directory
    mkdir -p "$destination"
    
    # Deploy
    local package_dir=$(create_package)
    rsync -av --delete "$package_dir/" "$destination/"
    rm -rf "$package_dir"
    
    print_success "Deployed to $env_name successfully"
}

# Deploy to remote environment
deploy_remote() {
    local env_name=$1
    local destination=${REMOTE_ENVIRONMENTS[$env_name]}
    
    if [ -z "$destination" ]; then
        print_error "Unknown remote environment: $env_name"
        return 1
    fi
    
    print_info "Deploying to remote environment: $env_name ($destination)"
    
    # Create package
    local package_dir=$(create_package)
    
    # Test SSH connection
    local ssh_host=$(echo "$destination" | cut -d':' -f1)
    if ! ssh -o ConnectTimeout=10 -o BatchMode=yes "$ssh_host" exit 2>/dev/null; then
        print_error "Cannot connect to $ssh_host"
        rm -rf "$package_dir"
        return 1
    fi
    
    # Deploy via rsync over SSH
    rsync -avz --delete \
        -e "ssh -o StrictHostKeyChecking=no" \
        "$package_dir/" "$destination/"
    
    rm -rf "$package_dir"
    print_success "Deployed to $env_name successfully"
}

# Run tests before deployment
run_tests() {
    print_info "Running pre-deployment tests..."
    
    # PHP syntax check
    if command -v php &> /dev/null; then
        find "$SOURCE_DIR" -name "*.php" -exec php -l {} \; > /dev/null
        if [ $? -eq 0 ]; then
            print_success "PHP syntax check passed"
        else
            print_error "PHP syntax check failed"
            exit 1
        fi
    fi
    
    # Check for required files
    local required_files=(
        "dtr-workbooks-crm-integration.php"
        "includes/nf-user-register.php"
        "includes/helper-functions.php"
        "lib/workbooks_api.php"
    )
    
    for file in "${required_files[@]}"; do
        if [ ! -f "$SOURCE_DIR/$file" ]; then
            print_error "Required file missing: $file"
            exit 1
        fi
    done
    
    print_success "All tests passed"
}

# Main deployment logic
main() {
    print_header
    
    print_info "Plugin: $PLUGIN_NAME"
    print_info "Version: $VERSION"
    print_info "Environment: $ENVIRONMENT"
    print_info "Source: $SOURCE_DIR"
    
    validate_source
    run_tests
    
    case "$ENVIRONMENT" in
        "all")
            print_info "Deploying to all environments..."
            for env in "${!ENVIRONMENTS[@]}"; do
                deploy_local "$env"
            done
            for env in "${!REMOTE_ENVIRONMENTS[@]}"; do
                deploy_remote "$env"
            done
            ;;
        "staging"|"production"|"local_backup")
            deploy_local "$ENVIRONMENT"
            ;;
        "staging_remote"|"production_remote")
            deploy_remote "$ENVIRONMENT"
            ;;
        *)
            print_error "Unknown environment: $ENVIRONMENT"
            echo "Available environments:"
            echo "  Local: ${!ENVIRONMENTS[*]}"
            echo "  Remote: ${!REMOTE_ENVIRONMENTS[*]}"
            echo "  Special: all"
            exit 1
            ;;
    esac
    
    print_success "Deployment completed successfully!"
}

# Help function
show_help() {
    echo "DTR Workbooks CRM Integration - Deployment Script"
    echo ""
    echo "Usage: $0 [environment] [version]"
    echo ""
    echo "Environments:"
    echo "  staging              Deploy to staging server"
    echo "  production           Deploy to production server"
    echo "  local_backup         Deploy to local backup"
    echo "  staging_remote       Deploy to remote staging"
    echo "  production_remote    Deploy to remote production"
    echo "  all                  Deploy to all environments"
    echo ""
    echo "Examples:"
    echo "  $0 staging"
    echo "  $0 production 1.4.3"
    echo "  $0 all"
    echo ""
    echo "Options:"
    echo "  -h, --help           Show this help message"
}

# Parse arguments
case "$1" in
    -h|--help)
        show_help
        exit 0
        ;;
    "")
        main
        ;;
    *)
        main
        ;;
esac
