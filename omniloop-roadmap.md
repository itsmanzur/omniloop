# OmniLoop for Divi — Product Roadmap

- **বর্তমান রিলিজ:** 1.0.0
- **রিলিজ তারিখ:** ৬ সেপ্টেম্বর ২০২৬
- **Database schema:** 1.3.0
- **অবস্থা:** Release candidate

## Product position

Divi 5-এর native Loop Builder ও Post Filter থাকা অবস্থায় OmniLoop-এর মূল্য শুধু AJAX filtering নয়। এর মূল পার্থক্য:

- WooCommerce variation-aware filtering
- custom indexed facets ও scalable merchandising
- shareable SEO filter URLs
- visual presets, conditional facets ও custom card composition
- Divi 5 native loop integration
- opt-in conversion-intent analytics

## 1.0.0 — সম্পন্ন

### Filtering ও WooCommerce

- একই variation-এর attribute, price, stock ও sale state মিলিয়ে filtering
- simple ও variable product উভয়ের attribute support
- WooCommerce catalog/search visibility এবং hidden out-of-stock rules
- category, tag, brand, rating, stock, sale, featured ও attribute facets
- visual price range, swatches, facet search এবং conditional visibility
- title, content ও SKU search; non-Latin query-safe fuzzy fallback

### SEO ও navigation

- shareable `/ol/` pretty URLs
- filtered title, description, H1, robots ও canonical control
- configurable single-facet sitemap entries
- unrelated page-এ generic query parameter বা unscoped `/ol/` route প্রয়োগ না করা
- pagination, sort, view ও active-filter state URL-এ সংরক্ষণ

### Conversion ও merchandising

- saved filter presets
- pinned products, out-of-stock-last, promo badge ও stock urgency
- card Add to Cart এবং accessible Quick View
- optional 30-day facet, product-click ও add-to-cart-intent analytics

### Admin ও agency workflow

- visual preset, promotion ও conditional-rule editors; JSON input প্রয়োজন নেই
- product title দিয়ে pin picker
- index health, stale-row report ও background schema-upgrade reindex
- settings export/import
- Help Guide ও configurable intro video
- WPML/Polylang-aware labels এবং supported multi-currency conversion hooks

### Reliability

- POST-only public query endpoint ও anonymous rate limiting
- bounded facet-count chunks, stale request abort এবং client cache
- localized loading/error/retry UI
- post, term, metadata, deletion ও ACF true/false index synchronization
- clean uninstall for tables, options, schedules ও transients

## 1.1.0 — পরবর্তী committed release

**থিম:** বড় catalog performance ও release operations

1. SQL-level pagination/counting দিয়ে all-matching-ID memory load সরানো।
2. 10k, 50k ও 100k product benchmark fixtures এবং performance budget যোগ করা।
3. Fresh install, 1.0.x upgrade, Woo disabled, ACF disabled এবং multisite test matrix চালু করা।
4. Marketplace ZIP-এর automated content audit, version check ও PHP compatibility gate যোগ করা।
5. Index diagnostics-এ slow query, missing row এবং repair action যোগ করা।

**সম্পন্ন হওয়ার মানদণ্ড:** 50k-product catalog-এ bounded PHP memory, repeatable benchmark report এবং automated release package verification।

## 1.2.0 — পরিকল্পিত differentiators

**থিম:** buyer journey ও actionable merchandising

- saved search এবং consent-based product/price alerts
- zero-result query থেকে admin recommendation
- analytics থেকে suggested pin/promotion rules
- reusable filter experience templates
- আরও শক্তিশালী keyboard navigation ও automated accessibility checks

এই release-এর আগে email delivery, consent, retention এবং privacy policy আলাদাভাবে design করতে হবে।

## ভবিষ্যৎ গবেষণা

- natural-language product finder, external API বাধ্যতামূলক না করে local-first prototype
- optional external search adapter for very large catalogs
- Divi native filter controls-এর সঙ্গে deeper bidirectional state sync

এগুলো committed feature নয়; প্রতিটির আগে feasibility spike ও buyer validation প্রয়োজন।

## Release process

প্রতিটি release-এ:

1. `omniloop.php`, `CHANGELOG.md`, Visual Builder package এবং Marketplace build version এক হতে হবে।
2. Database schema version শুধু schema migration হলে পরিবর্তন হবে; plugin version-এর সঙ্গে এক হওয়া বাধ্যতামূলক নয়।
3. PHPUnit, PHP lint, JavaScript build এবং live WordPress smoke test পাস করতে হবে।
4. Marketplace ZIP-এ tests, vendor, node_modules, demo এবং internal roadmap থাকবে না।
5. Feature claim বাস্তব code ও Help Guide-এর সঙ্গে মিলিয়ে review করতে হবে।
