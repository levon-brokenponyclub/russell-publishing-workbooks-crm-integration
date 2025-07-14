#!/bin/bash
# Setup script for DTR Workbooks CRM Integration deployment system

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

echo "🚀 Setting up deployment system for DTR Workbooks CRM Integration..."

# Make scripts executable
chmod +x "$SCRIPT_DIR/deploy.sh"
chmod +x "$SCRIPT_DIR/post-commit-sync.sh"

# Copy example config
if [ ! -f "$SCRIPT_DIR/config.sh" ]; then
    cp "$SCRIPT_DIR/config.example.sh" "$SCRIPT_DIR/config.sh"
    echo "✅ Created config.sh from example"
    echo "📝 Please edit $SCRIPT_DIR/config.sh with your deployment settings"
else
    echo "ℹ️  config.sh already exists"
fi

# Create logs directory
mkdir -p "$PLUGIN_DIR/logs"
echo "✅ Created logs directory"

# Git hooks setup (optional)
if [ -d "$PLUGIN_DIR/.git" ]; then
    read -p "🤔 Do you want to set up automatic post-commit sync? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        # Find the git directory (could be in parent directories)
        GIT_DIR="$PLUGIN_DIR"
        while [ ! -d "$GIT_DIR/.git" ] && [ "$GIT_DIR" != "/" ]; do
            GIT_DIR="$(dirname "$GIT_DIR")"
        done
        
        if [ -d "$GIT_DIR/.git" ]; then
            HOOKS_DIR="$GIT_DIR/.git/hooks"
            
            # Create post-commit hook
            cat > "$HOOKS_DIR/post-commit" << EOF
#!/bin/bash
# Auto-generated post-commit hook for DTR Workbooks CRM Integration
exec "$SCRIPT_DIR/post-commit-sync.sh"
EOF
            chmod +x "$HOOKS_DIR/post-commit"
            echo "✅ Created git post-commit hook"
        else
            echo "❌ Could not find .git directory"
        fi
    fi
fi

# Test deployment setup
echo ""
echo "🧪 Testing deployment setup..."

# Test PHP syntax
if command -v php &> /dev/null; then
    if php -l "$PLUGIN_DIR/dtr-workbooks-crm-integration.php" > /dev/null 2>&1; then
        echo "✅ PHP syntax check passed"
    else
        echo "❌ PHP syntax check failed"
    fi
else
    echo "⚠️  PHP not found - skipping syntax check"
fi

# Test rsync
if command -v rsync &> /dev/null; then
    echo "✅ rsync found"
else
    echo "❌ rsync not found - please install rsync for deployment"
fi

# Test git
if command -v git &> /dev/null; then
    echo "✅ git found"
else
    echo "⚠️  git not found - version tracking will be limited"
fi

echo ""
echo "🎉 Setup complete!"
echo ""
echo "Next steps:"
echo "1. Edit $SCRIPT_DIR/config.sh with your deployment settings"
echo "2. Test deployment with: $SCRIPT_DIR/deploy.sh staging"
echo "3. For production deployment: $SCRIPT_DIR/deploy.sh production"
echo ""
echo "Available commands:"
echo "  ./deploy.sh staging              Deploy to staging"
echo "  ./deploy.sh production          Deploy to production"
echo "  ./deploy.sh all                 Deploy to all environments"
echo "  ./post-commit-sync.sh           Manual sync trigger"
