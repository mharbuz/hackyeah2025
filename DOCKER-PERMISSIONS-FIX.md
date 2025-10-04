# Docker Permissions Fix

This document explains the changes made to fix Docker permission issues when working with files created inside containers.

## Problem

When running Docker containers, files created inside the container (like migration files) were owned by the `www-data` user (UID 33), but your host user has a different UID. This caused permission issues when trying to edit files from the host system.

## Solution

The following changes have been made to fix the permission issues:

### 1. Updated Dockerfile.dev
- Added `sudo` package installation
- Created a user `appuser` with UID 1000 (matching typical host user UID)
- Set proper ownership and permissions for application directories
- Switched to run as `appuser` instead of root

### 2. Updated docker-compose.dev.yml
- Added `user: "${UID:-1000}:${GID:-1000}"` to run container with host user's UID/GID
- Added `env_file: - .env.docker` to load environment variables

### 3. Updated docker-entrypoint-dev.sh
- Added permission setup at the beginning of the script
- Uses `sudo` to set proper ownership of files
- Ensures database files have correct permissions

### 4. Created setup script
- `setup-docker-permissions.sh` - automatically detects your UID/GID and creates `.env.docker`

## Usage

### Option 1: Use the setup script (Recommended)
```bash
# Run the setup script to automatically configure permissions
./setup-docker-permissions.sh

# Then start your containers
docker compose -f docker-compose.dev.yml up --build
```

### Option 2: Manual setup
```bash
# Get your UID and GID
id

# Create .env.docker file with your UID and GID
echo "UID=1000" > .env.docker
echo "GID=1000" >> .env.docker

# Start containers
docker compose -f docker-compose.dev.yml up --build
```

### Option 3: Export environment variables
```bash
# Export your UID and GID
export UID=$(id -u)
export GID=$(id -g)

# Start containers
docker compose -f docker-compose.dev.yml up --build
```

## What This Fixes

- ✅ No more "Insufficient permissions" errors when editing files
- ✅ Files created by Laravel commands (migrations, etc.) are owned by your host user
- ✅ You can edit files directly from your IDE without permission issues
- ✅ Git operations work properly inside the container

## Notes

- The container now runs as your host user (UID 1000 by default)
- All files created inside the container will be owned by your host user
- The `sudo` package is installed in the container for permission management
- The entrypoint script automatically sets correct permissions on startup

## Troubleshooting

If you still have permission issues:

1. Check your UID and GID: `id`
2. Make sure `.env.docker` contains the correct values
3. Rebuild the container: `docker compose -f docker-compose.dev.yml up --build`
4. Check file ownership: `ls -la` in your project directory

