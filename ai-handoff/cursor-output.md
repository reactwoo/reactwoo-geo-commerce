# Cursor output

## Status

done

A local `python scripts/package_zip.py` of this tree is byte-identical to the zip `publish-update.yml` builds. Version stays 0.10.1. Not tagged and not published.

## Files changed

- `scripts/package_zip.py` — write members with `ZipInfo` + `writestr` instead of `ZipFile.write()`. Timestamp is `SOURCE_DATE_EPOCH` when that value is 1980-01-01 or later, otherwise 1980-01-01 00:00:00. Mode is 0644, creator is Unix, compression level is 9. Entry order and the `reactwoo-geo-commerce/` folder name are unchanged.
- `.github/workflows/publish-update.yml` — pin CPython 3.12, do not install Composer before packaging, and fail the job when a second `package_zip.py` run hashes differently. Upload paths are unchanged.
- `tests/test-rwgcm-zip-reproducible.py` — checks that workflow contract and packages `git archive HEAD` twice with `CI=true` and twice without it.

## Not changed

- Plugin version, readme Stable tag, and `RWGCM_VERSION`.
- Zip entry order and the top-level folder name.
- R2 object keys and the unversioned `reactwoo-geo-commerce.zip` name used when `CI=true`.

## Cause

`ZipFile.write()` stored each file's filesystem mtime. Touching `reactwoo-geo-commerce.php` changed the zip from `46b66d93ba1019f204b9cd49fb1c13a6bc52d0502b00bb1774773f0d792945eb` to `9a13391b491d83b6478468df073fb144d8a4a7ee052e7b9280c1e10d538b809e`. The publish workflow also used `python-version: "3.x"`. It does not run Composer.

## Commands

- `python3 tests/test-zip-js-order.py` — zip JS entry order checks passed.
- `python3 tests/test-rwgcm-zip-reproducible.py` — publish zip reproducibility checks passed.
- Two consecutive local builds, with and without `CI=true`, share one SHA-256. `SOURCE_DATE_EPOCH` was unset. The local filename is `reactwoo-geo-commerce-0.10.1.zip`; CI writes `reactwoo-geo-commerce.zip`.

## Remaining errors

- None from these tests.
