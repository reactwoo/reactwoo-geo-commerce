# Cursor output

## Status

done

## Summary

Geo Commerce 0.9.3 carries its own plugin updater and uses it when Geo Core's `RWGC_Satellite_Updater` is a stub, when `RWGC_DISTRIBUTION` is `wporg`, or when Geo Core is missing or inactive. A real Core updater is still registered once. With no licence key, or when login returns no token, Plugins and Geo Commerce screens show "Add your licence key to receive updates." Draft only: not tagged and not merged.

## Root cause

`RWGCM_Plugin::register_satellite_updater()` called `RWGC_Satellite_Updater::register()` whenever the class existed. The WordPress.org build of Geo Core defines that class with an empty `register()`, so the call did nothing and Commerce updates were never offered.

## Files changed

| File | Reason |
|------|--------|
| includes/class-rwgcm-updater.php | Own update check, stub detection, licence notice |
| includes/class-rwgcm-plugin.php | Register updates even when Core is missing, and delegate to `RWGCM_Updater` |
| reactwoo-geo-commerce.php, readme.txt, CHANGELOG.md, README.md | 0.9.3 |
| tests/test-updater.php | Stub, wporg, inactive, missing, and no double registration |
| tests/test-product-price.php, tests/test-cache-safe-price.php, tests/test-gateway-fallback.php | Version assertions moved to 0.9.3 |

## What was not changed

The ReactWoo check URL, the licence login in `RWGCM_Platform_Client`, and the arguments passed to a real `RWGC_Satellite_Updater::register()`. No tag and no merge.

## Commands run

```bash
php -l includes/class-rwgcm-updater.php
php -l tests/test-updater.php
php tests/test-updater.php
for f in tests/test-*.php; do php "$f" || exit 1; done
```

Updater child processes: stub 45 passed, real 8 passed, wporg 6 passed, inactive 6 passed, missing 5 passed. The rest of `tests/test-*.php` passed with 0 failed.

## Remaining errors

None.
