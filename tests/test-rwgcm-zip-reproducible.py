#!/usr/bin/env python3
"""The publish zip is two identical runs of scripts/package_zip.py on the git tree."""

from __future__ import annotations

import hashlib
import os
import subprocess
import sys
import tempfile
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
FAILED = 0
FOLDER = "reactwoo-geo-commerce"


def check(label: str, ok: bool) -> None:
    global FAILED
    if ok:
        print(f"ok  - {label}")
        return
    print(f"FAIL - {label}")
    FAILED += 1


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def test_workflows_call_package_zip() -> None:
    publish = (ROOT / ".github/workflows/publish-update.yml").read_text(encoding="utf-8")

    check("publish workflow calls package_zip.py", "\n        run: python scripts/package_zip.py\n" in publish)
    check(
        "publish workflow does not install Composer dependencies before packaging",
        "composer install" not in publish,
    )
    check("publish workflow pins CPython 3.12", 'python-version: "3.12"' in publish)
    check(
        "publish workflow compares two package_zip.py runs",
        "package_zip.py produced different zips for the same tree." in publish,
    )
    check(
        "publish artifact key is unchanged",
        'ARTIFACT_KEY="plugins/${PLUGIN_SLUG}/${VERSION}/${PLUGIN_SLUG}.zip"' in publish,
    )
    check(
        "publish latest key is unchanged",
        'LATEST_KEY="plugins/${PLUGIN_SLUG}/latest/${PLUGIN_SLUG}.zip"' in publish,
    )
    check(
        "publish upload uses the unversioned zip name",
        'aws s3 cp "${PLUGIN_SLUG}.zip"' in publish,
    )
    check("publish workflow does not call git archive", "git archive" not in publish)


def _package(dest: Path, ci: bool) -> None:
    env = os.environ.copy()
    env.pop("SOURCE_DATE_EPOCH", None)
    if ci:
        env["CI"] = "true"
    else:
        env.pop("CI", None)
    subprocess.run([sys.executable, "scripts/package_zip.py"], cwd=dest, env=env, check=True)


def test_git_tree_zip_is_reproducible() -> None:
    """Package HEAD, which is the tree the publish job checks out."""
    with tempfile.TemporaryDirectory() as tmp:
        dest = Path(tmp) / "tree"
        dest.mkdir()
        archive = subprocess.check_output(["git", "-C", str(ROOT), "archive", "HEAD"])
        subprocess.run(["tar", "-x", "-C", str(dest)], input=archive, check=True)

        _package(dest, ci=True)
        publish_zip = dest / f"{FOLDER}.zip"
        check("CI package_zip.py writes reactwoo-geo-commerce.zip", publish_zip.is_file())
        if not publish_zip.is_file():
            return
        first = sha256(publish_zip)
        with zipfile.ZipFile(publish_zip) as archive_zip:
            names = archive_zip.namelist()
            info = archive_zip.getinfo(f"{FOLDER}/{FOLDER}.php")
        check(
            "publish zip timestamp is fixed at 1980-01-01",
            info.date_time == (1980, 1, 1, 0, 0, 0),
        )
        check("publish zip permissions are 0644", (info.external_attr >> 16) & 0o777 == 0o644)
        check(
            "publish zip keeps the plugin folder and entry order",
            names[:2]
            == [
                f"{FOLDER}/{FOLDER}.php",
                f"{FOLDER}/readme.txt",
            ]
            and all(name.startswith(f"{FOLDER}/") for name in names)
            and not any(name.startswith(f"{FOLDER}/{FOLDER}/") for name in names),
        )

        _package(dest, ci=True)
        second = sha256(publish_zip)
        check("two CI package_zip.py runs share a SHA-256", first == second)
        print(f"publish zip SHA-256 {first}")

        _package(dest, ci=False)
        local_zips = list(dest.glob(f"{FOLDER}-*.zip"))
        local_zip = local_zips[0] if local_zips else publish_zip
        local_hash = sha256(local_zip)
        check("local package_zip.py bytes match the publish zip", local_hash == first)

        _package(dest, ci=False)
        check("two local package_zip.py runs share a SHA-256", sha256(local_zip) == local_hash)

        epoch_env = os.environ.copy()
        epoch_env["CI"] = "true"
        epoch_env["SOURCE_DATE_EPOCH"] = "1700000000"
        subprocess.run([sys.executable, "scripts/package_zip.py"], cwd=dest, env=epoch_env, check=True)
        with zipfile.ZipFile(publish_zip) as archive_zip:
            stamped = archive_zip.getinfo(f"{FOLDER}/{FOLDER}.php").date_time
        stamped_hash = sha256(publish_zip)
        subprocess.run([sys.executable, "scripts/package_zip.py"], cwd=dest, env=epoch_env, check=True)
        check(
            "SOURCE_DATE_EPOCH sets a stable timestamp",
            stamped == (2023, 11, 14, 22, 13, 20) and sha256(publish_zip) == stamped_hash,
        )
        check("SOURCE_DATE_EPOCH changes the zip bytes", stamped_hash != first)


def main() -> None:
    test_workflows_call_package_zip()
    test_git_tree_zip_is_reproducible()
    if FAILED:
        print(f"{FAILED} failed")
        sys.exit(1)
    print("publish zip reproducibility checks passed")


if __name__ == "__main__":
    main()
