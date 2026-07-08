#!/bin/bash
set -euo pipefail

SITE_ROOT="/var/www/ru-atomy.ru"
PROJECT="${SITE_ROOT}/project"
VENV_PYTHON="${PROJECT}/venv/bin/python"
LOCK="/var/lock/ru-atomy-update.lock"
LOG="/var/log/ru-atomy-update.log"
export PATH="/usr/local/bin:/usr/bin:/bin"

exec 9>"${LOCK}"
if ! flock -n 9; then
  echo "$(date -Is) Update already running" >> "${LOG}"
  exit 0
fi

umask 022

{
  echo "=== $(date -Is) Daily update start ==="
  cd "${PROJECT}/scraper"
  "${VENV_PYTHON}" -u atomy_scraper.py scrape-all --data-dir "${PROJECT}/data" --skip-images
  "${VENV_PYTHON}" -u atomy_scraper.py download-images --data-dir "${PROJECT}/data" --delay 0.25 --per-product 1
  chown -R www-data:www-data "${PROJECT}/data"
  cd "${SITE_ROOT}"
  wp atomy import --data="${PROJECT}/data" --path="${SITE_ROOT}" --allow-root
  # Refresh homepage hero banners and donor category icons into uploads/
  mkdir -p "${SITE_ROOT}/wp-content/uploads/banners" "${SITE_ROOT}/wp-content/uploads/category-icons"
  "${VENV_PYTHON}" - "${PROJECT}/data/homepage.json" "${SITE_ROOT}/wp-content/uploads/banners" "${SITE_ROOT}/wp-content/uploads/category-icons" <<'PY'
import json, os, re, ssl, sys, urllib.request
src, banners_dir, icons_dir = sys.argv[1], sys.argv[2], sys.argv[3]
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

def fetch(url, out):
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=30, context=ctx) as r, open(out, "wb") as f:
        f.write(r.read())

try:
    data = json.load(open(src))
except Exception as e:
    print("homepage.json read failed:", e); sys.exit(0)

for b in data.get("banners", []):
    img = b.get("image", "")
    link = b.get("link", "")
    # Hero slides: PNG/JPG main-visual banners
    if re.search(r"\.(png|jpe?g)$", img, re.I):
        name = os.path.basename(img.split("?")[0])
        try:
            fetch(img, os.path.join(banners_dir, name)); print("banner OK", name)
        except Exception as e:
            print("banner FAIL", img, e)
    # Category line icons: SVG entries linked to a category dispCtgNo
    m = re.search(r"dispCtgNo=(\d+)", link)
    if m and img.lower().endswith(".svg"):
        disp = m.group(1)
        try:
            fetch(img, os.path.join(icons_dir, disp + ".svg")); print("icon OK", disp)
        except Exception as e:
            print("icon FAIL", img, e)
PY
  # Ensure media uploaded/generated during import are readable by Apache (www-data)
  chown -R www-data:www-data "${SITE_ROOT}/wp-content/uploads"
  find "${SITE_ROOT}/wp-content/uploads" -type d -exec chmod 755 {} \;
  find "${SITE_ROOT}/wp-content/uploads" -type f -exec chmod 644 {} \;
  echo "=== $(date -Is) Daily update done ==="
} >> "${LOG}" 2>&1
