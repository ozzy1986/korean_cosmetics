# Deploy scripts (ru-atomy.ru VPS)

## DNS before SSL

Certbot (Let's Encrypt) requires a public DNS **A record** pointing to this VPS:

| Host | Type | Value |
|------|------|-------|
| `ru-atomy.ru` | A | `162.35.161.95` |
| `www.ru-atomy.ru` | A | `162.35.161.95` (if using www) |

Run `vps-setup-ru-atomy.sh` only after DNS propagates; otherwise certbot will fail and the site stays on HTTP.

## Scripts

| Script | Purpose |
|--------|---------|
| `vps-setup-ru-atomy.sh` | Initial WordPress, Apache, certbot setup (run as root) |
| `post-wp-setup.sh` | Activate theme/plugins and apply Atomy settings |
| `daily-update.sh` | Cron: scrape + import (uses project venv Python) |
| `deploy-sync.sh` | Rsync project and wp-content from local machine to VPS |

## Line endings

All `*.sh` files must use **LF** (Unix) line endings. CRLF breaks shebang execution on Linux.
