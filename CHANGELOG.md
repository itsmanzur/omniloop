# Changelog

All notable changes to OmniLoop for Divi are documented here.

## 1.0.0 — 2026-09-06

Initial public release.

### Added

- Divi 5 **OmniLoop Filter** module with live AJAX search, sorting, active chips, mobile drawer, and page-number, Load More, or infinite-scroll pagination
- Sidebar, Accordion, Toolbar, Mega Bar, Pills, and Chip Only filter layouts
- Classic, Overlay, List, Compact, Carousel, and Masonry card layouts with skeleton loading and customizable empty states
- **Loop Item Builder** with reorderable Image, Title, Excerpt, Price, Rating, Add to Cart, and Quick View items
- Filter Sync and saved filter presets with shareable `?ol_preset=` URLs
- Merchandising controls for pinned products, out-of-stock-last ordering, promo badges, and stock urgency
- WooCommerce category, tag, brand, attribute, price, stock, sale, featured, and rating facets
- Variation-aware matching that keeps selected attributes, price, stock, and sale state on the same variation while retaining simple-product matching
- Optional ACF select, checkbox, radio, button group, and true/false facets
- Pretty `/ol/` filter URLs with title, description, H1, robots, canonical, and optional filtered sitemap controls
- Native Divi 5 and WordPress loop attachment through query hooks
- Conditional facets, zero-count hiding, facet search, title/content/SKU search, and non-Latin-safe fuzzy fallback
- WPML/Polylang-aware labels, supported multi-currency hooks, and settings export/import
- Optional anonymous 30-day facet, product-click, and add-to-cart-intent analytics
- Visual admin editors for presets, merchandising, and conditional rules; product title pin picker; Help Guide and intro video support
- Custom incremental index with stale-row health reporting, schema-upgrade reindex, and clean uninstall

### Security and reliability

- Public filter queries use POST with bounded input and anonymous rate limiting
- WooCommerce catalog/search visibility and hidden out-of-stock settings are respected
- Generic query parameters and unscoped `/ol/` paths cannot turn unrelated content into filter pages
- Deleted posts, added/deleted metadata, ACF false values, and cache invalidation stay synchronized with the index
- Large facet counts are processed in bounded chunks
- Localized frontend error/retry states preserve existing Load More results
- Quick View includes keyboard focus containment and focus restoration
- REST-rendered Add to Cart links resolve to the storefront rather than the REST endpoint
