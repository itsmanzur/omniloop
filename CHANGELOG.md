# Changelog

All notable changes to OmniLoop for Divi are documented here.

## Unreleased

### Added
- Variation-aware filtering keeps selected attributes, price, stock, and sale state on the same WooCommerce variation
- Optional anonymous product-click and add-to-cart intent analytics, with a 30-day product engagement report
- Index health now reports stale rows; schema upgrades automatically schedule a background reindex
- Frontend request failures show a localized retry state while Load More failures preserve existing results

### Changed
- Search includes WooCommerce SKU values and avoids ASCII phonetic matching for non-Latin searches
- Large facet-count queries are processed in bounded chunks
- Frontend-generated filter URLs use OmniLoop-prefixed query parameters

### Fixed
- WooCommerce catalog/search visibility and hidden out-of-stock settings are respected
- Simple products remain eligible for attribute filters, and REST-rendered Add to Cart links point to the storefront instead of the REST endpoint
- Deleted posts, added/deleted metadata, ACF false values, and cache invalidation stay in sync with the index
- Unrelated pages no longer become filter pages through generic query parameters or an unscoped `/ol/` path
- Frontend dynamic copy and accessibility labels are translatable; Quick View traps keyboard focus

## 1.0.1 — 2026-08-31

### Changed
- Saved presets and promo badges use filter pickers (no JSON). Pin products by title search. Add or remove preset, promo, and conditional-facet rows
- Listing and Help Guide copy: WPML/Polylang and multi-currency are described as hook-level support, not full suites

### Fixed
- Variable products index variation attributes plus min/max prices on the parent. Price filters match if the variation span overlaps the selected range
- Sort dropdowns in saved presets use plain-English labels

## 1.0.0 — 2026-08-30

Initial public release.

### Added
- Divi 5 **OmniLoop Filter** module: live AJAX search, sort, pagination (page numbers, Load More, or infinite scroll), active chips, and a mobile filter drawer
- Filter layouts: Sidebar, Accordion, Toolbar, Mega Bar, Pills, Chip Only
- Card layouts: Classic, Overlay, List, Compact, Carousel, Masonry, plus skeleton loading and a customizable empty state
- **Loop Item Builder** — nest **OmniLoop Card Item** children (Image, Title, Excerpt, Price, Rating, Add to Cart, Quick View) and reorder them in the layers panel
- **Filter Sync** and **saved filter presets**, including shareable `?ol_preset=` URLs
- **Merchandising** — pin products, push out-of-stock last, promo badges, and stock-urgency copy
- WooCommerce facets when Woo is active: categories, tags, attributes (color / image / size swatches), dual-handle price slider, stock, on sale, featured, rating; card add-to-cart and quick view
- Optional ACF facets (select, checkbox, radio, button group, true/false)
- Pretty SEO filter URLs (`/ol/…`) with title, description, and H1 templates, robots/noindex, and an optional sitemap of single-facet URLs
- Native Divi 5 / WordPress loop attach (`omniloop_attach`, `omniloop_attach_native_loop`, Loop Builder query-arg filters) limited to the main query unless opted in
- Hide zero-count values, hide empty facet groups, and conditional facet visibility rules
- Typo-tolerant search, within-facet search, WPML/Polylang labels, multi-currency price facets, filter analytics, and settings export/import
- Custom index with incremental auto-sync, Help Guide, and a clean uninstall (options, index/events tables, `olq_*` and `ol_ev_*` transients)
