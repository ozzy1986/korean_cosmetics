"""HTML/JSON parsers for atomy.ru pages."""
from __future__ import annotations

import json
import re
from typing import Any
from urllib.parse import urljoin

from bs4 import BeautifulSoup

from config import BASE_URL, IMAGE_BASE


def _clean_text(value: str | None) -> str:
    return re.sub(r"\s+", " ", (value or "")).strip()


def extract_goods_detail_json(html: str) -> dict[str, Any] | None:
    marker = "let goodsDetail = "
    start = html.find(marker)
    if start == -1:
        return None
    start += len(marker)
    if start >= len(html) or html[start] != "{":
        return None

    depth = 0
    in_string = False
    escape = False
    quote = ""
    for idx in range(start, len(html)):
        ch = html[idx]
        if in_string:
            if escape:
                escape = False
            elif ch == "\\":
                escape = True
            elif ch == quote:
                in_string = False
            continue
        if ch in ('"', "'"):
            in_string = True
            quote = ch
            continue
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                try:
                    return json.loads(html[start : idx + 1])
                except json.JSONDecodeError:
                    return None
    return None


def extract_og_meta(html: str) -> dict[str, str]:
    soup = BeautifulSoup(html, "lxml")
    meta: dict[str, str] = {}
    for tag in soup.select('meta[property^="og:"]'):
        prop = tag.get("property", "")
        content = tag.get("content", "")
        if prop and content:
            meta[prop] = content
    return meta


def parse_category_name(html: str) -> str:
    og = extract_og_meta(html)
    title = og.get("og:title", "")
    return _clean_text(title.split("|")[0])


def parse_category_product_ids(html: str) -> list[str]:
    ids = re.findall(r"/product/(R\d+)", html)
    return sorted(set(ids))


def parse_category_total_count(html: str) -> int:
    match = re.search(r'name="totalCount"\s+value="(\d+)"', html)
    if match:
        return int(match.group(1))
    match = re.search(r'name="totalCountText">(\d+)<', html)
    return int(match.group(1)) if match else 0


def discover_category_ids(html: str) -> list[str]:
    ids = re.findall(r"dispCtgNo=(\d+)", html)
    return sorted(set(ids))


def normalize_image_url(url: str) -> str:
    if not url:
        return ""
    if url.startswith("//"):
        return "https:" + url
    if url.startswith("/"):
        return IMAGE_BASE + url
    return url


def extract_gallery_images_from_detail(detail: dict[str, Any]) -> list[str]:
    images: list[str] = []
    seen: set[str] = set()
    for item in detail.get("gdGoodsImgList") or []:
        path = item.get("imgPath")
        if not path:
            continue
        url = re.sub(r"\?.*$", "", normalize_image_url(path))
        if url not in seen:
            seen.add(url)
            images.append(url)
    return images


def extract_gallery_images(html: str, goods_no: str) -> list[str]:
    pattern = (
        rf"(?:https?:)?//image\.atomy\.ru/(?:\d+_\d+/)?"
        rf"RU/goods/{re.escape(goods_no)}/[^\s\"']+\.(?:jpg|jpeg|png|webp)"
    )
    urls = re.findall(pattern, html, re.IGNORECASE)
    normalized: list[str] = []
    seen: set[str] = set()
    for url in urls:
        clean = re.sub(r"\?.*$", "", normalize_image_url(url))
        if clean not in seen:
            seen.add(clean)
            normalized.append(clean)
    return normalized


def extract_description_html(html: str) -> str:
    soup = BeautifulSoup(html, "lxml")
    for selector in (
        ".product-detail-info",
        ".detail-description",
        ".goods-detail",
        ".gds-detail",
        "#goodsDetail",
        ".detail-cont",
        ".goods_desc",
    ):
        node = soup.select_one(selector)
        if node:
            return str(node)
    match = re.search(r'<div[^>]+class="[^"]*detail[^"]*"[^>]*>(.*?)</div>', html, re.DOTALL | re.IGNORECASE)
    return match.group(0) if match else ""


def extract_badges(html: str) -> list[str]:
    badges = []
    lower = html.lower()
    if "halal" in lower or "халяль" in lower:
        badges.append("Halal")
    if "корзина добра" in lower:
        badges.append("charity")
    if "бесплатная доставка" in lower:
        badges.append("free-shipping")
    return badges


def parse_product_from_detail(html: str, goods_no: str) -> dict[str, Any] | None:
    detail = extract_goods_detail_json(html)
    if not detail:
        return None

    gd = detail.get("gdGoods") or {}
    info = gd.get("goodsInfo") or {}
    name = _clean_text(gd.get("goodsNm") or info.get("goodsNm") or "")
    if not name:
        og = extract_og_meta(html)
        name = _clean_text(og.get("og:title", "").split("|")[0])

    tags_raw = _clean_text(gd.get("searchKwd") or info.get("searchKwd") or "")
    tags = [t.strip() for t in re.split(r"[,;]", tags_raw) if t.strip()]

    reg_price = float(info.get("nomeSalePrice") or gd.get("nomeSalePrice") or 0)
    member_price = float(info.get("custSalePrice") or gd.get("custSalePrice") or 0)
    pv = float(info.get("pvPrice") or info.get("custPvupPrice") or gd.get("pvPrice") or 0)

    stock_val = info.get("salePossQty")
    if stock_val is None:
        stock_val = gd.get("salePossQty")
    stock = int(stock_val or 0)
    disp = (gd.get("goodsDispYn") or info.get("goodsDispYn") or "Y") == "Y"
    status = "publish" if disp and stock > 0 else ("draft" if not disp else "publish")

    category_id = str(gd.get("dispCtgNo") or info.get("dispCtgNo") or "")

    gallery = extract_gallery_images_from_detail(detail)
    if not gallery:
        gallery = extract_gallery_images(html, goods_no)

    return {
        "goods_no": goods_no,
        "name": name,
        "reg_price": reg_price,
        "member_price": member_price,
        "pv": pv,
        "tags": tags,
        "category_ids": [category_id] if category_id else [],
        "description_html": extract_description_html(html),
        "gallery_images": gallery,
        "badges": extract_badges(html),
        "stock": stock,
        "status": status,
        "source_url": f"{BASE_URL}/product/{goods_no}",
    }


def parse_homepage(html: str) -> dict[str, Any]:
    banners = []
    rails = []

    for match in re.finditer(
        r"(?:overpass\.util\.)?goLinkUrl\(\{linkUrl:'([^']+)'.*?\}\).*?<img[^>]+src=\"([^\"]+)\"",
        html,
        re.DOTALL,
    ):
        link = match.group(1)
        if not link.startswith("http"):
            link = urljoin(BASE_URL, link)
        banners.append({"image": normalize_image_url(match.group(2)), "link": link})

    product_ids = parse_category_product_ids(html)
    if product_ids:
        rails.append({"title": "Бестселлеры", "product_ids": product_ids[:12]})

    return {"banners": banners[:20], "rails": rails, "category_ids": discover_category_ids(html)}


def parse_category_meta(html: str, disp_ctg_no: str, order: int = 0) -> dict[str, Any]:
    name = parse_category_name(html) or f"Category {disp_ctg_no}"
    image = ""
    og = extract_og_meta(html)
    if og.get("og:image"):
        image = normalize_image_url(og["og:image"])
    return {
        "disp_ctg_no": disp_ctg_no,
        "name": name,
        "parent": "",
        "image": image,
        "order": order,
        "product_ids": parse_category_product_ids(html),
        "total_count": parse_category_total_count(html),
        "source_url": f"{BASE_URL}/category?dispCtgNo={disp_ctg_no}",
    }
