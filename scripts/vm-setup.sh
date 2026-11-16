#!/bin/sh
#========================================
# Learnova LMS VM Initial Setup
# Jalankan SEKALI di VM untuk setup awal
# Usage: ./scripts/vm-setup.sh [deploy-branch]
#========================================

set -e

DEPLOY_BRANCH="${1:-main}"
DEPLOY_DIR="/opt/learnova-lms-web"

echo "==> VM Initial Setup: Learnova LMS"

# Install Docker if not present
if ! command -v docker >/dev/null 2>&1; then
    echo "==> Installing Docker..."
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
fi

# Install Docker Compose v2 if not present
if ! docker compose version >/dev/null 2>&1; then
    echo "==> Installing Docker Compose..."
    apt-get update && apt-get install -y docker-compose-plugin
fi

# Create deploy directory and clone repo
if [ ! -d "$DEPLOY_DIR" ]; then
    echo "==> Cloning repository to $DEPLOY_DIR..."
    git clone https://github.com/lookmedia-tech/learnova-lms-web.git "$DEPLOY_DIR"
    cd "$DEPLOY_DIR"
    git checkout "$DEPLOY_BRANCH"
else
    echo "==> Repo already exists at $DEPLOY_DIR, skipping clone"
    cd "$DEPLOY_DIR"
    git checkout "$DEPLOY_BRANCH"
fi

# Start services
echo "==> Starting Docker Compose services..."
cd "$DEPLOY_DIR"
docker compose up -d --build

# Wait for services
echo "==> Waiting for services to be healthy..."
sleep 10

echo "==> Container status:"
docker compose ps

echo ""
echo "==> Setup complete!"
echo "   Access: http://$(curl -s ifconfig.me):8080"
echo "   Default login: admin / LearnovaLMS2026!"
echo ""
echo "To deploy updates later, run:"
echo "   ./scripts/deploy.sh <vm-ip> root"
