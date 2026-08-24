# OmniLoop for Divi

Faceted product and post loop for the **Divi 5 Visual Builder**. Custom index table, live AJAX filters, pretty SEO URLs, WooCommerce-ready — without requiring WooCommerce.

This is a **Divi extension** (one Visual Builder module: **OmniLoop Filter**). It is not a Gutenberg block.

## Requirements

| Requirement | Notes |
| --- | --- |
| WordPress | 6.4+ |
| PHP | 7.4+ |
| Divi | Divi 5 Visual Builder |
| WooCommerce | Optional — enables Product, price, stock, sale, rating, attributes |
| Advanced Custom Fields | Optional — Select / Checkbox / Radio / Button Group / True–False facets |

Works with Divi + WordPress alone. WooCommerce and ACF unlock extra facets when present.

## Quick start

1. Activate **OmniLoop for Divi**.
2. Go to **OmniLoop → Settings**.
3. Choose post types (and ACF fields if needed).
4. Click **Rebuild index**.
5. Edit a page in the Divi Visual Builder → insert **OmniLoop Filter**.
6. Set **Post Type**, layout, and filters → Save → view the front end (hard refresh once after updates).

If pretty URLs like `/ol/...` 404, visit **Settings → Permalinks** and click Save.

## Features

### Filtering

- Live search, sort, pagination (page numbers, Load More, or infinite scroll)
- **Filter Sync** — multiple modules on one page share filter state via a Sync Group ID
- **Saved filter presets** — reusable combinations from Settings; share with `?ol_preset=preset-id`
- **Merchandising** — pin products, push out-of-stock last, promo badges, and “Only X left” urgency
- **Loop Item Builder** — add Card Item children (image, title, price, rating, buttons) and reorder them to design the card
- Taxonomy facets with counts and “Show more”
- WooCommerce: categories, tags, attributes (color / image / size swatches), price From–To, stock, on sale, featured, star rating
- ACF facets (opt-in in Settings)
- Active filter chips + clear all
- Pretty SEO URLs (`/page/ol/color/blue+red/...`) with title/description/H1 templates, noindex controls, and optional sitemap entries
- Conditional facets, hide empty values, typo-tolerant search, and filter analytics

### Layout presets

**Filter styles:** Sidebar, Accordion, Toolbar, Mega Bar, Pills, Chip Only  

**Card styles:** Classic, Overlay, List, Compact, Carousel, Masonry  

**Design tab:** Card / filters / title / price / badge decoration, gaps, badge position, title lines, image hover zoom  

**Empty state:** Custom title, message, icon style, reset button

### Shopping (WooCommerce)

- Add to cart on cards (AJAX for simple products; Select options / View product for variable & grouped)
- Quick view modal (preview without leaving the loop)
- Cart fragments refresh when Woo mini-cart is present

### Performance

- Custom index table for fast facet queries with incremental auto-sync on save
- AJAX query with client cache + abort of stale requests
- Skeleton shimmer while results load

## Module settings (Visual Builder)

### Content

- Post Type, Per Page, Show Filters, Visible Filters, Add to Cart, Quick View, Show Pagination, Pagination Style  
- Sync Filters, Sync Group, Saved Filter Preset  
- Merchandising, Extra Pin IDs, Stock Urgency, Promoted Badge  
- Nested **OmniLoop Card Item** children for custom card element order  
- Empty State (style, title, message, reset label)

### Design

- Loop Layout: columns, image ratio, filter style, card style  
- Carousel (only when Card Style is Carousel): autoplay, speed, pause on hover, loop, arrows, dots
- Spacing & Details: card gap, filters/results gap, badge position, title lines, hover zoom
- Filters / Card / Title / Price / Regular Price / Sale Badge (native Divi decoration groups)

## Admin settings

**OmniLoop → Settings**

- Post types to index
- ACF field allow-list
- SEO templates, robots, and sitemap
- Conditional facets and empty-result hiding
- Settings export / import
- Rebuild index

## OmniLoop Help Guide

- Plain-English interactive docs: intro, setup checklist, module settings, filter/card layouts, troubleshooting

## Support notes for buyers

- After changing indexed fields, attributes, or ACF choices → **Rebuild index**
- Visual Builder preview uses live REST data; always confirm on the published page too
- Chip Only and mobile layouts open filters in a drawer
- Carousel supports arrows, dots, and swipe

## Tests

Core sanitizers and SEO URL parsing run without WordPress:

```bash
composer install
composer test
```

PHPUnit + Brain Monkey live in `require-dev`. Marketplace ZIPs exclude `tests/` and `vendor/`.

## License

GPL-2.0-or-later. See [`license.txt`](license.txt).

Updates are delivered through the **Divi Marketplace** — this plugin does not ship a custom updater.

## Changelog

See [`CHANGELOG.md`](CHANGELOG.md).

## Marketplace submission

Seller checklist, listing copy, and screenshot plan: [`MARKETPLACE.md`](MARKETPLACE.md).
