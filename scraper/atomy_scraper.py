#!/usr/bin/env python3
"""Atomy.ru catalog scraper."""
from __future__ import annotations

import argparse
import json
import random
import sys
import time
from pathlib import Path
from urllib.parse import urljoin

import requests

from config import (
    BASE_URL,
    DEFAULT_DATA_DIR,
    DELAY_JITTER,
    DELAY_SECONDS,
    IMAGE_VERIFY_SSL,
    KNOWN_CATEGORY_IDS,
    REQUEST_TIMEOUT,
    USER_AGENT,
    VERIFY_SSL,
)
from parsers import (
    discover_category_ids,
    parse_category_meta,
    parse_homepage,
    parse_product_from_detail,
)


class AtomyScraper:
    def __init__(self, data_dir: Path, delay: float = DELAY_SECONDS) -> None:
        self.data_dir = data_dir
        self.images_dir = data_dir / "images"
        self.delay = delay
        self.session = requests.Session()
        self.session.headers.update({"User-Agent": USER_AGENT})

    def _sleep(self) -> None:
        time.sleep(self.delay + random.uniform(0, DELAY_JITTER))

    def fetch(self, path: str) -> str:
        url = path if path.startswith("http") else urljoin(BASE_URL, path)
        self._sleep()
        response = self.session.get(url, timeout=REQUEST_TIMEOUT, verify=VERIFY_SSL)
        response.raise_for_status()
        return response.text

    def download_image(self, url: str, dest: Path) -> Path | None:
        if not url:
            return None
        dest.parent.mkdir(parents=True, exist_ok=True)
        if dest.exists():
            return dest
        try:
            self._sleep()
            response = self.session.get(
                url,
                timeout=REQUEST_TIMEOUT,
                verify=IMAGE_VERIFY_SSL,
                headers={"Referer": BASE_URL + "/"},
            )
            response.raise_for_status()
            dest.write_bytes(response.content)
            return dest
        except requests.RequestException as exc:
            print(f"  image download failed: {url} ({exc})", flush=True)
            return None

    def download_images_from_products(self, products_file: Path | None = None, per_product: int = 1) -> int:
        products_file = products_file or (self.data_dir / "products.json")
        if not products_file.exists():
            print(f"No products.json at {products_file}", flush=True)
            return 0
        products = json.loads(products_file.read_text(encoding="utf-8"))
        downloaded = 0
        for idx, row in enumerate(products):
            goods_no = row.get("goods_no")
            urls = row.get("gallery_images", [])[:per_product] if per_product else row.get("gallery_images", [])
            local_images = []
            for img_idx, url in enumerate(urls):
                ext = Path(url).suffix or ".jpg"
                filename = f"{goods_no}_{img_idx}{ext}"
                local = self.download_image(url, self.images_dir / "goods" / filename)
                if local:
                    local_images.append(str(local.relative_to(self.data_dir)).replace("\\", "/"))
                    downloaded += 1
            if local_images:
                row["local_gallery_images"] = local_images
            print(f"[{idx + 1}/{len(products)}] {goods_no}: {len(local_images)} img", flush=True)
        products_file.write_text(json.dumps(products, ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"Done: {downloaded} images downloaded", flush=True)
        return downloaded

    def scrape_home(self) -> dict:
        html = self.fetch("/main")
        return parse_homepage(html)

    def discover_categories(self) -> list[str]:
        html = self.fetch("/main")
        ids = discover_category_ids(html)
        merged = sorted(set(ids) | set(KNOWN_CATEGORY_IDS))
        return merged

    def scrape_category(self, disp_ctg_no: str, order: int = 0) -> dict:
        html = self.fetch(f"/category?dispCtgNo={disp_ctg_no}")
        return parse_category_meta(html, disp_ctg_no, order)

    def scrape_product(self, goods_no: str, skip_images: bool = False) -> dict | None:
        html = self.fetch(f"/product/{goods_no}")
        product = parse_product_from_detail(html, goods_no)
        if not product:
            return None
        local_images = []
        if not skip_images:
            for idx, url in enumerate(product.get("gallery_images", [])):
                ext = Path(url).suffix or ".jpg"
                filename = f"{goods_no}_{idx}{ext}"
                local = self.download_image(url, self.images_dir / "goods" / filename)
                if local:
                    local_images.append(str(local.relative_to(self.data_dir)).replace("\\", "/"))
        product["local_gallery_images"] = local_images
        return product

    def scrape_all(self, max_products: int | None = None, skip_images: bool = False) -> None:
        self.data_dir.mkdir(parents=True, exist_ok=True)
        self.images_dir.mkdir(parents=True, exist_ok=True)

        print("Scraping homepage...")
        homepage = self.scrape_home()
        (self.data_dir / "homepage.json").write_text(
            json.dumps(homepage, ensure_ascii=False, indent=2), encoding="utf-8"
        )

        category_ids = sorted(set(self.discover_categories()) | set(homepage.get("category_ids", [])))
        print(f"Found {len(category_ids)} categories")

        categories = []
        all_product_ids: set[str] = set()

        for order, cat_id in enumerate(category_ids):
            print(f"Category {cat_id} ({order + 1}/{len(category_ids)})")
            try:
                cat = self.scrape_category(cat_id, order)
                categories.append(cat)
                all_product_ids.update(cat.get("product_ids", []))
            except requests.RequestException as exc:
                print(f"  skip category {cat_id}: {exc}")

        for rail in homepage.get("rails", []):
            all_product_ids.update(rail.get("product_ids", []))

        (self.data_dir / "categories.json").write_text(
            json.dumps(categories, ensure_ascii=False, indent=2), encoding="utf-8"
        )

        products = []
        product_list = sorted(all_product_ids)
        if max_products is not None:
            product_list = product_list[:max_products]
        print(f"Scraping {len(product_list)} products...")

        for idx, goods_no in enumerate(product_list):
            print(f"Product {goods_no} ({idx + 1}/{len(product_list)})")
            try:
                product = self.scrape_product(goods_no, skip_images=skip_images)
                if product:
                    products.append(product)
            except requests.RequestException as exc:
                print(f"  skip product {goods_no}: {exc}")

        (self.data_dir / "products.json").write_text(
            json.dumps(products, ensure_ascii=False, indent=2), encoding="utf-8"
        )
        print(f"Done: {len(categories)} categories, {len(products)} products")


def main() -> int:
    parser = argparse.ArgumentParser(description="Atomy.ru scraper")
    parser.add_argument("command", choices=["scrape-all", "scrape-home", "discover-categories", "download-images"])
    parser.add_argument("--data-dir", type=Path, default=DEFAULT_DATA_DIR)
    parser.add_argument("--delay", type=float, default=DELAY_SECONDS)
    parser.add_argument(
        "--per-product",
        type=int,
        default=1,
        help="Images per product for download-images (0 = all)",
    )
    parser.add_argument(
        "--max-products",
        type=int,
        default=None,
        help="Limit number of products to scrape (for testing)",
    )
    parser.add_argument(
        "--skip-images",
        action="store_true",
        help="Skip gallery image downloads (faster; importer can sideload URLs)",
    )
    args = parser.parse_args()

    scraper = AtomyScraper(args.data_dir, delay=args.delay)

    if args.command == "scrape-all":
        scraper.scrape_all(max_products=args.max_products, skip_images=args.skip_images)
    elif args.command == "scrape-home":
        data = scraper.scrape_home()
        args.data_dir.mkdir(parents=True, exist_ok=True)
        (args.data_dir / "homepage.json").write_text(
            json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8"
        )
        print(json.dumps(data, ensure_ascii=False, indent=2))
    elif args.command == "discover-categories":
        ids = scraper.discover_categories()
        print(json.dumps(ids, ensure_ascii=False, indent=2))
    elif args.command == "download-images":
        scraper.download_images_from_products(per_product=args.per_product)

    return 0


if __name__ == "__main__":
    sys.exit(main())
