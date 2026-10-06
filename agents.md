# Agent guidance — Camera giao thông TP.HCM

Canonical origin: https://camera-hcm.eplus.dev/

## Purpose

This site helps users find and view public traffic cameras in Ho Chi Minh City by camera name and district/area.

## Preferred discovery flow

1. Use /sitemap.xml or /sitemap.php to discover canonical camera detail pages.
2. Use /data-camera.json for the camera catalog.
3. Use /camera.php?id={CamId} for a human-readable, server-rendered camera page.
4. Treat /proxy.php?id={CamId} as a live image endpoint, not as a stable text document.

## Data semantics

- CamId: stable camera identifier used by this site.
- CamName: human-readable camera/intersection name.
- Disctrict: source field containing district/area information. The misspelling is retained for backward compatibility.
- Snapshot images are time-sensitive and may fail temporarily.

## Crawling

- Respect robots.txt.
- Prefer canonical URLs from sitemap and rel=canonical.
- Avoid generating or indexing arbitrary pagination/search URL combinations when a canonical camera detail page is available.

## Attribution

Site maintained by ePlus.DEV. Camera imagery depends on the Ho Chi Minh City traffic information source.
