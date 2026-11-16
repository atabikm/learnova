#!/bin/sh
#========================================
# Learnova LMS Deployment Script
# Auto-detects VM from repo name, or use args.
# Usage: ./scripts/deploy.sh [vm-alias|vm-ip] [vm-user]
#========================================

set -e

REPO_NAME="learnova-lms-web"
DEPLOY_DIR="/home/ubuntu/learnova-lms-web"
HEALTH_URL="http://localhost:8080/login/index.php"

VM_HOST="${1:-}"
VM_USER="${2:-ubuntu}"

if [ -z "$VM_HOST" ]; then
    VM_HOST="learnova-lms-staging-01"
fi

SSH_KEY="$HOME/.ssh/id_ed25519"
[ ! -f "$SSH_KEY" ] && SSH_KEY="$HOME/.ssh/id_rsa"

SSH_OPTS="-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10 -i $SSH_KEY"

echo "==> Deploying $REPO_NAME to $VM_USER@$VM_HOST"

ssh -t $SSH_OPTS "$VM_USER@$VM_HOST" << 'SSHEOF'
set -e

echo "==> Switching to deploy directory"
cd /home/ubuntu/learnova-lms-web

echo "==> Pulling latest code from git"
git fetch origin main
git checkout main
git reset --hard origin/main

echo "==> Rebuilding moodle container"
docker compose build moodle

echo "==> Restarting all services"
docker compose up -d

echo "==> Checking container status"
docker compose ps

echo "==> Checking site health (with retries)..."
for i in 1 2 3 4 5; do
    sleep 3
    STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/login/index.php || echo "000")
    echo "   Attempt $i: HTTP $STATUS"
    [ "$STATUS" = "200" ] || [ "$STATUS" = "303" ] && break
done

echo "==> Deploy complete!"
SSHEOF

echo "==> SSH session ended"