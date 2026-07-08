#!/bin/bash
set -euo pipefail

VPS="root@162.35.161.95"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/id_ed25519}"
REMOTE_ROOT="/var/www/ru-atomy.ru"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOCAL_ROOT="${SCRIPT_DIR}/.."

RSYNC_SSH="ssh -i ${SSH_KEY} -o StrictHostKeyChecking=accept-new"

echo "=== Sync project to VPS ==="
rsync -avz \
  --exclude '.git/' \
  --exclude 'data/' \
  --exclude '.env' \
  --exclude '__pycache__/' \
  --exclude '.pytest_cache/' \
  --exclude 'vendor/' \
  -e "${RSYNC_SSH}" \
  "${LOCAL_ROOT}/" "${VPS}:${REMOTE_ROOT}/project/"

echo "=== Sync wp-content ==="
rsync -avz \
  --exclude 'uploads/' \
  --exclude 'cache/' \
  --exclude 'upgrade/' \
  -e "${RSYNC_SSH}" \
  "${LOCAL_ROOT}/wp-content/" "${VPS}:${REMOTE_ROOT}/wp-content/"

ssh -i "${SSH_KEY}" -o StrictHostKeyChecking=accept-new "${VPS}" \
  "chown -R www-data:www-data ${REMOTE_ROOT}/wp-content ${REMOTE_ROOT}/project"

echo "Done."
