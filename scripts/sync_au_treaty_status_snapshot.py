"""Refresh the AU treaty snapshot. Requires requests and PyMuPDF."""

from __future__ import annotations

import json
import re
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import date
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin

import pymupdf
import requests


BASE_URL = "https://au.int"
INDEX_URL = f"{BASE_URL}/en/treaties"
OUTPUT_PATH = Path(__file__).resolve().parents[1] / "database" / "treaty files" / "AU_Treaty_Status_Snapshot.json"
DATE_PATTERN = re.compile(r"^(\d{1,2})/(\d{1,2})/(\d{4})$")
EMPTY_VALUES = {"", "-", "--", "n/a", "na"}


class TableParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.rows = []
        self.row = None
        self.cell = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "tr":
            self.row = []
        elif tag in {"td", "th"} and self.row is not None:
            self.cell = {"text": "", "hrefs": []}
            self.row.append(self.cell)
        elif tag == "a" and self.cell is not None and attrs.get("href"):
            self.cell["hrefs"].append(attrs["href"])

    def handle_data(self, data):
        if self.cell is not None:
            self.cell["text"] += data

    def handle_endtag(self, tag):
        if tag in {"td", "th"}:
            self.cell = None
        elif tag == "tr" and self.row is not None:
            self.rows.append(self.row)
            self.row = None


class LinkParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.link = None
        self.text = ""
        self.links = []

    def handle_starttag(self, tag, attrs):
        if tag == "a":
            self.link = dict(attrs).get("href")
            self.text = ""

    def handle_data(self, data):
        if self.link:
            self.text += data

    def handle_endtag(self, tag):
        if tag == "a" and self.link:
            self.links.append((self.text.strip(), self.link))
            self.link = None


def get(url: str) -> requests.Response:
    response = requests.get(url, timeout=35, headers={"User-Agent": "ATTP-AU-Treaty-Status-Sync/1.0"})
    response.raise_for_status()
    return response


def clean(value: str) -> str:
    return " ".join(value.split())


def key(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", " ", value.casefold()).strip()


def parse_iso_date(value: str):
    match = DATE_PATTERN.match(value.strip())
    if not match:
        return None
    day, month, year = map(int, match.groups())
    return date(year, month, day).isoformat()


def catalog_rows() -> list[dict]:
    parser = TableParser()
    parser.feed(get(INDEX_URL).text)
    by_title = {}
    for row in parser.rows[1:]:
        if len(row) < 4:
            continue
        title = clean(row[0]["text"])
        title = title.replace("Lom\ufffd Charter", "Lomé Charter").replace("Peoples\ufffd Rights", "Peoples’ Rights")
        detail_path = row[0]["hrefs"][0] if row[0]["hrefs"] else None
        if not title or not detail_path:
            continue
        item = {
            "title": title,
            "detail_url": urljoin(BASE_URL, detail_path),
            "adoption_date": parse_iso_date(clean(row[1]["text"])),
            "entry_into_force_date": parse_iso_date(clean(row[2]["text"])),
            "last_update": parse_iso_date(clean(row[3]["text"])),
        }
        item["score"] = sum(item[field] is not None for field in ("adoption_date", "entry_into_force_date", "last_update"))
        current = by_title.get(key(title))
        if current is None or item["score"] > current["score"]:
            by_title[key(title)] = item
    return list(by_title.values())


def status_pdf_url(detail_url: str) -> str | None:
    parser = LinkParser()
    parser.feed(get(detail_url).text)
    for label, href in parser.links:
        if label.casefold() == "status list (en)":
            return urljoin(BASE_URL, href)
    return None


def parse_pdf(pdf_url: str) -> dict:
    response = get(pdf_url)
    document = pymupdf.open(stream=response.content, filetype="pdf")
    lines = [clean(line).replace("\ufffd", "") for page in document for line in page.get_text().splitlines()]
    as_of = None
    for index, line in enumerate(lines[:-1]):
        if line.casefold() == "no":
            as_of = parse_iso_date(lines[index - 1]) if index else None
            break

    records = {}
    for index, line in enumerate(lines):
        if not line.isdigit() or not 1 <= int(line) <= 55:
            continue
        number = int(line)
        next_values = []
        cursor = index + 1
        while cursor < len(lines) and len(next_values) < 4:
            candidate = lines[cursor]
            cursor += 1
            if candidate:
                next_values.append(candidate)
        if len(next_values) < 4:
            continue
        country, signed, ratified_or_acceded, deposited = next_values
        if DATE_PATTERN.match(country) or country.casefold() in {"country/pays", "country", "pays"}:
            continue
        records[number] = {
            "country": country,
            "signed_at": parse_iso_date(signed),
            "ratification_or_accession_at": parse_iso_date(ratified_or_acceded),
            "instrument_deposited_at": parse_iso_date(deposited),
        }

    if len(records) < 50:
        raise ValueError(f"Only {len(records)} country rows parsed from {pdf_url}")
    return {"as_of": as_of, "pdf_url": pdf_url, "member_states": list(records.values())}


def fetch_treaty(item: dict) -> dict:
    try:
        pdf_url = status_pdf_url(item["detail_url"])
        if not pdf_url:
            return {**item, "status_list": None}
        return {**item, "status_list": parse_pdf(pdf_url)}
    except Exception as error:
        return {**item, "status_list": None, "error": str(error)}


def main():
    treaties = catalog_rows()
    complete = []
    failures = []
    with ThreadPoolExecutor(max_workers=8) as pool:
        futures = [pool.submit(fetch_treaty, item) for item in treaties]
        for future in as_completed(futures):
            item = future.result()
            if item.get("status_list"):
                complete.append(item)
            else:
                failures.append({"title": item["title"], "error": item.get("error", "No official status list")})

    payload = {
        "source": INDEX_URL,
        "fetched_at": date.today().isoformat(),
        "treaty_count": len(treaties),
        "status_list_count": len(complete),
        "catalog": [
            {key: value for key, value in item.items() if key in {"title", "detail_url", "adoption_date", "entry_into_force_date", "last_update"}}
            for item in sorted(treaties, key=lambda item: key(item["title"]))
        ],
        "treaties": sorted(complete, key=lambda item: key(item["title"])),
        "unavailable_status_lists": sorted(failures, key=lambda item: key(item["title"])),
    }
    OUTPUT_PATH.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Fetched {len(complete)} of {len(treaties)} official treaty status lists.")
    print(f"Snapshot: {OUTPUT_PATH}")
    if failures:
        for item in failures:
            print(f"Unavailable: {item['title']}: {item['error']}")


if __name__ == "__main__":
    main()
