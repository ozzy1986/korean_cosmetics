"""Atomy.ru scraper configuration."""
from pathlib import Path

BASE_URL = "https://www.atomy.ru"
IMAGE_BASE = "https://image.atomy.ru"
DELAY_SECONDS = 2.0
DELAY_JITTER = 0.5
USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
REQUEST_TIMEOUT = 30
VERIFY_SSL = True
IMAGE_VERIFY_SSL = False  # image.atomy.ru chain fails on some VPS CA bundles

ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DATA_DIR = ROOT / "data"

KNOWN_CATEGORY_IDS = [
    "2411002244", "2411002245", "2411002246", "2411002247",
    "2411002248", "2411002249", "2411002250",
    "2504003387", "2504003390", "2504003408",
    "2505003504", "2602003555",
]
