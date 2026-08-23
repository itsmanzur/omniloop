# Changelog

All notable changes to OmniLoop for Divi are documented here.

## 2.0.1 — 2026-08-23

### Fixed
- Product post type can be unchecked in Settings; WooCommerce no longer force-re-enables it on every page load (one-time bootstrap only)
- Uninstall now deletes leftover options (`last_index_sync`, `asset_version`, `sort_meta_keys`, `rewrite_version`) and `olq_*` query transients
- Full reindex walks `post_id > last_id` instead of `OFFSET`, so posts are not skipped or duplicated if the catalog changes mid-run

### Changed
- Facet count queries memoize matching ID sets within a request to avoid repeating the same SQL for overlapping filters

## 2.0.0 — 2026-08-19

### Added
- **Loop Item Builder** — nest **OmniLoop Card Item** children inside the Filter module (Image, Title, Excerpt, Price, Rating, Add to Cart, Quick View). Reorder in the layers panel to change card layout. No children = preset Card Style.

## 1.4.0 — 2026-08-18

### Added
- **Merchandising rules** in **OmniLoop → Settings**: pin product IDs to the top of matching results, push out-of-stock products last, and attach promo badges from facet JSON
- **Stock urgency** — cards show “Only X left” when WooCommerce stock is at or below the threshold
- Module toggles: Merchandising, Extra Pin IDs, Stock Urgency, Promoted Badge

## 1.3.0 — 2026-08-17

### Added
- **Filter Sync** — Multiple OmniLoop modules on the same page share live filter state when they use the same Sync Group and post type
- **Saved filter presets** — Define reusable filter combinations in **OmniLoop → Settings**; apply from the module **Saved Filter Preset** dropdown
- Shareable preset URLs via `?ol_preset=preset-id` (or `?preset=preset-id`)

## 1.2.0 — 2026-08-17

### Added
- Pagination Style module option: Page numbers, Load More, or Infinite scroll
- Incremental index auto-sync on post save, term changes, price/stock meta, WooCommerce updates, and ACF saves
- Admin dashboard stats for indexed posts, last sync time, and auto-sync toggle

## 1.1.39 — 2026-08-17

### Fixed
- Card and Filters Design tab settings (background, border, spacing, shadow, font) now bind to the correct Divi decoration paths

## 1.1.38 — 2026-08-17

### Fixed
- Sale Badge Design tab background (and font, border, spacing) now apply in Visual Builder and on the frontend

## 1.1.37 — 2026-08-17

### Fixed
- Carousel settings group now hides unless Card Style is Carousel (Divi only honors function visibility on group props)

## 1.1.36 — 2026-08-17

### Changed
- Carousel settings appear only when Card Style is Carousel; Autoplay Speed and Pause on Hover appear when Autoplay is on; Columns hide for List

## 1.1.35 — 2026-08-17

### Added
- Visual Builder option to show or hide pagination (off = first page of results only)

## 1.1.34 — 2026-08-17

### Changed
- Compact pagination: previous/next, nearby pages, and ellipsis instead of listing every page

## 1.1.33 — 2026-08-17

### Fixed
- Carousel dots (and arrows/autoplay) now render in the Visual Builder preview

## 1.1.32 — 2026-08-17

### Fixed
- Carousel autoplay not starting (data attribute whitespace + stricter flag parsing)

## 1.1.31 — 2026-08-17

### Added
- Carousel options: Autoplay, Speed, Pause on Hover, Loop, Show Arrows, Show Dots

## 1.1.30 — 2026-08-16

### Added
- Interactive English Help Guide for non-technical users (OmniLoop → Help Guide)

## 1.1.29 — 2026-08-16

### Changed
- Redesigned OmniLoop admin settings into a product dashboard (stats, cards, progress)

## 1.1.28 — 2026-08-16

### Added
- Dual-thumb price range slider (synced with From–To inputs)
- Within-facet AND/OR logic (smart: categories OR, attributes AND)
- Sort by popularity, average rating, and optional custom meta key
- Type-to-filter search inside long facet lists
- Sticky active bar with result count and filter chips

## 1.1.27 — 2026-08-16

### Added

- Quick view modal on cards (image, title, price, excerpt, Add to cart, View details)
- Content setting: Quick View Yes/No

## 1.1.26 — 2026-08-16

### Added

- Add to cart on product cards (WooCommerce AJAX for simple products; variable/grouped link to product)
- Content setting: Add to Cart Yes/No (products only)

## 1.1.25 — 2026-08-16

### Documentation

- Marketplace pack: expanded README, CHANGELOG, and `MARKETPLACE.md` submission checklist
- Clarified optional WooCommerce / ACF requirements for Divi Marketplace reviewers

## 1.1.24 — 2026-08-16

### Added

- Configurable empty-state template (icon style, title, message, reset button)
- Empty state works on SSR, AJAX, and Visual Builder preview

## 1.1.23 — 2026-08-16

### Added

- Design tab depth: card gap, filters/results gap, badge position, title lines, image hover zoom
- Sale Badge and Regular Price Text design groups
- Filters panel box shadow control

## 1.1.22 — 2026-08-16

### Added

- Chip Only filter layout (Filters button + active chips; drawer on all breakpoints)

## 1.1.21 — 2026-08-16

### Added

- Mega Bar filter layout (full-width multi-column facet panel)

## 1.1.20 — 2026-08-16

### Added

- Skeleton shimmer placeholders during AJAX filter/page changes

## 1.1.19 — 2026-08-16

### Added

- Masonry card style (CSS columns + staggered image ratios)

## 1.1.18 — 2026-08-16

### Added

- Carousel card style (arrows, dots, swipe)

## 1.1.17 — 2026-08-15

### Added

- Attribute image swatches (term image meta; color prefers image when present)

## 1.1.16 — 2026-08-15

### Added

- Price From–To number inputs with SEO path `price/20-100/`

## Earlier 1.1.x

### Added

- Divi 5 OmniLoop Filter module (single module; no Filter/Loop split)
- Custom index + REST query API
- WooCommerce facets: categories, attributes, stock, on sale, featured, rating
- Pretty SEO URLs under `/ol/` with legacy `?ol_f_…` redirects
- ACF facet opt-in (select, checkbox, radio, button_group, true_false)
- Filter styles: sidebar, accordion, toolbar, pills
- Card styles: classic, overlay, list, compact
- Color / size swatches, list/grid view toggle, sticky filters, mobile drawer
- Visible Filters picker and Design decoration for card / filters / fonts
