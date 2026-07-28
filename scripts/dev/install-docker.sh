#!/bin/bash
# ──────────────────────────────────────────────────────────────
#  Install Docker on Ubuntu 24.04 (Noble)
#  Requires sudo. Run: sudo bash scripts/dev/install-docker.sh
# ──────────────────────────────────────────────────────────────
set -euo pipefail

echo "=== Installing Docker on Ubuntu 24.04 ==="

apt-get update -q
apt-get install -y docker.io docker-compose-plugin postgresql-client

systemctl enable docker
systemctl start docker

# Add the calling user (or specified user) to docker group
CALLER="${SUDO_USER:-$(logname 2>/dev/null || echo admtutor)}"
usermod -aG docker "$CALLER"

echo ""
docker --version
docker compose version
echo ""
echo "=== Docker installed ==="
echo "IMPORTANT: Log out and back in (or run 'newgrp docker') to use Docker without sudo."
