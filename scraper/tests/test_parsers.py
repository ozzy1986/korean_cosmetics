"""Tests for atomy.ru HTML/JSON parsers."""
from __future__ import annotations

from pathlib import Path

import pytest

from parsers import (
    discover_category_ids,
    extract_goods_detail_json,
    extract_og_meta,
    normalize_image_url,
    parse_category_meta,
    parse_category_product_ids,
    parse_category_total_count,
    parse_homepage,
    parse_product_from_detail,
)

FIXTURES = Path(__file__).parent / "fixtures"


@pytest.fixture
def homepage_html() -> str:
    return (FIXTURES / "homepage.html").read_text(encoding="utf-8")


@pytest.fixture
def category_html() -> str:
    return (FIXTURES / "category_2411002244.html").read_text(encoding="utf-8")


@pytest.fixture
def product_html() -> str:
    return (FIXTURES / "product_R00001.html").read_text(encoding="utf-8")


def test_extract_goods_detail_json(product_html: str) -> None:
    detail = extract_goods_detail_json(product_html)
    assert detail is not None
    assert "gdGoods" in detail
    assert detail["gdGoods"]["goodsNo"] == "R00001"


def test_extract_og_meta(product_html: str) -> None:
    meta = extract_og_meta(product_html)
    assert "og:title" in meta
    assert "ХемоХИМ" in meta["og:title"]


def test_normalize_image_url() -> None:
    assert normalize_image_url("//image.atomy.ru/foo.jpg") == "https://image.atomy.ru/foo.jpg"
    assert normalize_image_url("/RU/goods/R00001/a.jpg").endswith("/RU/goods/R00001/a.jpg")


def test_discover_category_ids(homepage_html: str) -> None:
    ids = discover_category_ids(homepage_html)
    assert "2411002250" in ids
    assert "2504003390" in ids


def test_parse_homepage(homepage_html: str) -> None:
    data = parse_homepage(homepage_html)
    assert isinstance(data["banners"], list)
    assert len(data["banners"]) > 0
    assert data["banners"][0]["link"].startswith("http")
    assert isinstance(data["category_ids"], list)
    assert len(data["category_ids"]) > 0
    if data["rails"]:
        assert "product_ids" in data["rails"][0]


def test_parse_category_meta(category_html: str) -> None:
    cat = parse_category_meta(category_html, "2411002244", order=3)
    assert cat["disp_ctg_no"] == "2411002244"
    assert cat["name"] == "ДРУГОЕ"
    assert cat["order"] == 3
    assert cat["total_count"] == 12
    assert "R92496" in cat["product_ids"]
    assert cat["source_url"].endswith("dispCtgNo=2411002244")


def test_parse_category_product_ids(category_html: str) -> None:
    ids = parse_category_product_ids(category_html)
    assert all(id_.startswith("R") for id_ in ids)
    assert len(ids) >= 1


def test_parse_category_total_count(category_html: str) -> None:
    assert parse_category_total_count(category_html) == 12


def test_parse_product_from_detail(product_html: str) -> None:
    product = parse_product_from_detail(product_html, "R00001")
    assert product is not None
    assert product["goods_no"] == "R00001"
    assert product["name"] == "Атоми ХемоХИМ"
    assert product["reg_price"] == 9700.0
    assert product["member_price"] == 8800.0
    assert product["pv"] == 60000.0
    assert product["stock"] == 140576
    assert product["status"] == "publish"
    assert product["category_ids"] == ["2504003365"]
    assert "иммунитет" in product["tags"][0].lower() or "хемохим" in " ".join(product["tags"]).lower()
    assert len(product["gallery_images"]) >= 1
    assert product["gallery_images"][0].startswith("https://image.atomy.ru/")
    assert "Halal" in product["badges"]
    assert product["source_url"] == "https://www.atomy.ru/product/R00001"
