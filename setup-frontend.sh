#!/bin/bash

# KiriminAja Frontend Setup Script

set -e

echo "🚀 Setting up KiriminAja Frontend Development Environment"
echo ""

# Check if bun is installed
if ! command -v bun &> /dev/null; then
    echo "❌ Bun is not installed!"
    echo "📦 Please install Bun first: https://bun.sh"
    echo ""
    echo "Quick install:"
    echo "  curl -fsSL https://bun.sh/install | bash"
    exit 1
fi

echo "✅ Bun is installed: $(bun --version)"
echo ""

# Install dependencies from root
echo "📦 Installing dependencies..."
bun install

echo ""
echo "✅ Setup complete!"
echo ""
echo "📋 Next steps:"
echo "  1. Start development server:"
echo "     make dev"
echo "     or"
echo "     bun run dev"
echo ""
echo "  2. Enable WP_DEBUG in wp-config.php:"
echo "     define('WP_DEBUG', true);"
echo ""
echo "  3. Visit your WordPress admin to see the Vue app!"
echo ""
echo "📚 Read frontend/README.md for full documentation"
