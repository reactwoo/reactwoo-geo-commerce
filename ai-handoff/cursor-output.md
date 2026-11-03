# Cursor output

## Status

done

Release zips no longer put a `.js` file on the ClamAV Foxhole entries, and Commerce's own updater no longer stores a ten-minute R2 URL in `update_plugins`. Version is 0.10.1 for QA. Not tagged and not merged.

## Files changed

- `scripts/package_zip.py` — write `reactwoo-geo-commerce.php` and `readme.txt`, then other non-JS files, then `.js`, in a stable order. The zip is refused when a `.js` file would match `Sanesecurity.Foxhole.JS_Zip_1`, `_2`, `_4`, or `_11`–`_24`.
- `tests/test-zip-js-order.py` — applies those rules to synthetic zips and to the built Commerce zip.
- `includes/class-rwgcm-updater.php` — placeholder package, fresh signed URL in `upgrader_pre_download`, one-hour offer cache, 15-minute failed-login cache, skip when a save only changes `last_checked`. Check timeout 8s, install check 10s, login 10s.
- `includes/class-rwgcm-platform-client.php` — `get_access_token()` and `get_bearer_for_updates()` accept a timeout. The default remains 30s.
- `tests/test-updater.php` — covers the placeholder, caches, timeouts, and install-time URL, including the real platform client.

## Not changed

- Geo Core's updater path. When Core's updater is real, Commerce still registers with it and does not install its own download hook.
- Other platform calls still use the 30s login timeout.

## Commands

- `php tests/test-updater.php` — updater children passed (stub 65, real 9, wporg 7, inactive 7, missing 6, client 6).
- `python3 tests/test-zip-js-order.py` — zip JS entry order checks passed, including the built zip.
- `for f in tests/test-*.php; do php "$f"; done` and the zip test again after the 0.10.1 bump — all passed.

## Remaining errors

- None from these tests.
