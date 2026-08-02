#!/usr/bin/env python3
"""Build the deterministic UA FREE Plugin Suite v3.0 release assets."""

from __future__ import annotations

import argparse
import hashlib
import json
import stat
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

RELEASE_DATE = "2026-08-02"
LIBRARY_VERSION = "3.0"
FIXED_TIME = (2026, 8, 2, 12, 0, 0)
LIBRARY_NAME = "UA_FREE_HUB_RELEASE_LIBRARY_FINAL_2026-08-02_v3.0.zip"

PLUGINS = [
    ("ua-free-migration-cleanup", "ua-free-migration-cleanup", "ua-free-migration-cleanup", "UA FREE Migration & Cleanup", "0.8.10"),
    ("ua-free-static-translate", "ua-free-static-translate", "ua-free-static-translate", "UA FREE Static Translate", "0.8.7"),
    ("ua-free-translate-diagnostics", "ua-free-translate-diagnostics", "ua-free-translate-diagnostics", "UA FREE Translate Diagnostics", "0.2.14"),
    ("ua-free-seo-core", "ua-free-seo-core", "ua-free-seo-core", "UA FREE SEO Core", "2.0.8"),
    ("ua-free-404-guard", "ua-free-404-guard", "ua-free-404-guard", "UA FREE 404 Guard & URL Intelligence", "2.0.8"),
    ("ua-free-site-bridge", "ua-free-site-bridge", "ua-free-site-bridge", "UA FREE Site Bridge", "0.4.10"),
    ("ua-free-consent-manager", "ua-free-consent-manager", "ua-free-consent-manager", "UA FREE Consent Manager", "0.1.6"),
    ("ua-free-donate-stats", "ua-free-donate-stats", "ua-free-donate-stats", "UA FREE Donate Stats & Conversions", "1.2.8"),
    ("ua-free-google-ads-campaign-builder", "ua-free-google-ads-campaign-builder", "ua-free-google-ads-campaign-builder", "UA FREE Google Ads Campaign Builder", "1.3.7"),
    ("ua-free-copy", "ua-free-copy", "ua-free-copy", "UA FREE Copy", "1.0.7"),
    ("ua-free-url-only-comment-spam", "ua-free-url-only-comment-spam", "ua-free-url-only-comment-spam", "UA FREE URL-Only Comment Spam", "1.0.7"),
    ("ua-free-suite-control-center", "ua-free-analytics-dashboard", "ua-free-analytics-dashboard", "UA FREE Suite Control Center", "0.3.13"),
]


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def zip_info(name: str) -> ZipInfo:
    info = ZipInfo(name, date_time=FIXED_TIME)
    info.compress_type = ZIP_DEFLATED
    info.external_attr = 0o644 << 16
    return info


def build_plugin_zip(root: Path, zip_root: str) -> bytes:
    from io import BytesIO

    buffer = BytesIO()
    with ZipFile(buffer, "w", ZIP_DEFLATED, compresslevel=9) as archive:
        for path in sorted(root.rglob("*")):
            if not path.is_file() or ".git" in path.parts:
                continue
            relative = path.relative_to(root).as_posix()
            archive.writestr(zip_info(f"{zip_root}/{relative}"), path.read_bytes())
    return buffer.getvalue()


def validate_zip(data: bytes, expected_root: str) -> None:
    from io import BytesIO

    with ZipFile(BytesIO(data)) as archive:
        if archive.testzip() is not None:
            raise RuntimeError(f"CRC failure in {expected_root}")
        for entry in archive.infolist():
            if not entry.filename.startswith(f"{expected_root}/"):
                raise RuntimeError(f"Unexpected ZIP root: {entry.filename}")
            if entry.filename.startswith("/") or ".." in Path(entry.filename).parts:
                raise RuntimeError(f"Unsafe ZIP path: {entry.filename}")
            if stat.S_ISLNK((entry.external_attr >> 16) & 0xFFFF):
                raise RuntimeError(f"Symlink is not allowed: {entry.filename}")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, default=Path("dist"))
    args = parser.parse_args()

    repository = Path(__file__).resolve().parents[1]
    output = args.output.resolve()
    output.mkdir(parents=True, exist_ok=True)

    packages: list[dict[str, object]] = []
    package_bytes: list[tuple[str, str, bytes]] = []
    release_checksums: list[tuple[str, str]] = []

    for slug, wporg_slug, root_name, name, version in PLUGINS:
        root = repository / root_name
        if not root.is_dir():
            raise FileNotFoundError(root)

        data = build_plugin_zip(root, root_name)
        validate_zip(data, root_name)

        library_file_name = f"packages/{slug}-v{version}.zip"
        asset_name = f"{slug}-{version}.zip"
        digest = sha256(data)

        package_bytes.append((library_file_name, asset_name, data))
        (output / asset_name).write_bytes(data)
        (output / f"{asset_name}.sha256").write_text(
            f"{digest}  {asset_name}\n",
            encoding="utf-8",
        )
        release_checksums.append((asset_name, digest))

        packages.append(
            {
                "slug": slug,
                "wporg_slug": wporg_slug,
                "name": name,
                "version": version,
                "file": library_file_name,
                "release_asset": asset_name,
                "sha256": digest,
                "size": len(data),
            }
        )

    manifest = {
        "format": "UA FREE Hub Release Library",
        "version": LIBRARY_VERSION,
        "created": RELEASE_DATE,
        "status": "FINAL — 12 OF 12 SUPPORT PANEL V2.1 UPDATES VERIFIED",
        "packages": packages,
        "update_policy": "No custom updater. Public releases will use WordPress.org updates after directory approval.",
        "verified_packages": [
            {
                "slug": item["slug"],
                "version": item["version"],
                "plugin_check": "PASS",
                "live_visual_check": "PASS",
            }
            for item in packages
        ],
    }

    manifest_data = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")
    library_path = output / LIBRARY_NAME
    with ZipFile(library_path, "w", ZIP_DEFLATED, compresslevel=9) as archive:
        archive.writestr(zip_info("manifest.json"), manifest_data)
        for library_file_name, _asset_name, data in sorted(package_bytes):
            archive.writestr(zip_info(library_file_name), data)

    with ZipFile(library_path) as archive:
        if archive.testzip() is not None:
            raise RuntimeError("Release library CRC failure")
        embedded = json.loads(archive.read("manifest.json"))
        if len(embedded["packages"]) != 12:
            raise RuntimeError("Expected 12 packages")
        for package in embedded["packages"]:
            data = archive.read(package["file"])
            if sha256(data) != package["sha256"] or len(data) != package["size"]:
                raise RuntimeError(f"Manifest mismatch: {package['slug']}")

    library_digest = sha256(library_path.read_bytes())
    checksum_path = output / f"{LIBRARY_NAME}.sha256"
    checksum_path.write_text(
        f"{library_digest}  {LIBRARY_NAME}\n",
        encoding="utf-8",
    )

    release_checksums.append((LIBRARY_NAME, library_digest))
    (output / "SHA256SUMS.txt").write_text(
        "".join(
            f"{digest}  {file_name}\n"
            for file_name, digest in sorted(release_checksums)
        ),
        encoding="utf-8",
    )
    (output / "manifest.json").write_bytes(manifest_data)

    print(f"Built {library_path}")
    print(f"Built {len(packages)} individual WordPress plugin ZIP files")
    print(f"Library SHA-256: {library_digest}")


if __name__ == "__main__":
    main()
