#!/bin/bash

# Script to set up proper Docker permissions
# This script will help you configure the correct UID and GID for Docker containers

echo "🔧 Setting up Docker permissions..."

# Get current user's UID and GID
CURRENT_UID=$(id -u)
CURRENT_GID=$(id -g)

echo "Your current UID: $CURRENT_UID"
echo "Your current GID: $CURRENT_GID"

# Create or update .env.docker file
cat > .env.docker << EOF
# Docker environment variables for proper user permissions
UID=$CURRENT_UID
GID=$CURRENT_GID
EOF

echo "✅ Created .env.docker file with your UID and GID"

# Update docker-compose.dev.yml to use the .env.docker file
echo "📝 To use these permissions, run Docker Compose with:"
echo "   docker compose -f docker-compose.dev.yml --env-file .env.docker up --build"

echo ""
echo "🔍 Alternative: You can also export these variables in your shell:"
echo "   export UID=$CURRENT_UID"
echo "   export GID=$CURRENT_GID"
echo "   docker compose -f docker-compose.dev.yml up --build"

