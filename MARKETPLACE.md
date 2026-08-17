# Divi Marketplace pack

Use this file when submitting **OmniLoop for Divi** as a Divi Extension.

Official references:

- [Developer Guidelines](https://www.elegantthemes.com/marketplace/developer-guidelines/)
- [Product Licensing](https://www.elegantthemes.com/marketplace/licensing/)
- [Become a Seller](https://help.elegantthemes.com/en/articles/10343634-becoming-a-divi-marketplace-seller)

---

## Listing copy (ready to paste)

### Product name

OmniLoop for Divi

### Short description (≈160 characters)

Faceted product & post loops for Divi 5 — live filters, SEO URLs, swatches, carousel & masonry. WooCommerce optional.

### Long description

OmniLoop for Divi is a Divi 5 Visual Builder extension that turns any page into a fast, faceted shop or archive.

Insert one module — **OmniLoop Filter** — and get:

- Live AJAX filtering with search, sort, pagination, and active chips  
- Filter layouts: Sidebar, Accordion, Toolbar, Mega Bar, Pills, Chip Only  
- Card layouts: Classic, Overlay, List, Compact, Carousel, Masonry  
- WooCommerce extras when Woo is active: attributes with color/image/size swatches, price From–To, stock, on sale, featured, star rating  
- Optional ACF facets from Settings  
- Pretty SEO filter URLs  
- Skeleton loading and a customizable empty state  
- Native Divi Design controls for cards, filters, typography, and sale badges  

**Requirements:** WordPress 6.4+, PHP 7.4+, Divi 5. WooCommerce and Advanced Custom Fields are optional enhancements — the extension runs on Divi + WordPress alone.

**License:** GPL-2.0-or-later. Updates via Divi Marketplace (no custom updater).

### Category suggestions

- Extensions / WooCommerce (optional)  
- Filters / Loops / Dynamic content  

### Tags

divi 5, filter, facets, woocommerce, loop, ajax, shop, attributes, swatches, seo

---

## Screenshot checklist

Capture on a clean Divi 5 site with sample Woo products. Avoid Unsplash / Pixabay / Pexels stock in marketing creatives (Marketplace licensing). Prefer your own photos or CC0 / CC BY 4.0 assets.

| # | Shot | Notes |
| --- | --- | --- |
| 1 | Hero / module on front | Sidebar filters + product grid |
| 2 | Visual Builder | Module selected; Content / Design panels visible |
| 3 | Filter styles | Collage or 2–3 crops: Mega Bar, Chip Only, Pills |
| 4 | Card styles | Carousel + Masonry (or Classic + Overlay) |
| 5 | Swatches | Color and/or image attribute filters |
| 6 | Empty state | Filtered to zero results with custom empty template |
| 7 | Mobile | Filters drawer open |
| 8 | Settings | OmniLoop → Settings with Rebuild index |

Recommended size: Marketplace listing ratio (typically wide hero + square thumbs). Keep UI chrome readable.

---

## ZIP package checklist

### Include

- Plugin root folder named `omniloop/`
- `omniloop.php`, `license.txt`, `README.md`, `CHANGELOG.md`
- `app/`, `assets/`, `languages/` (if present)
- Compiled VB asset: `app/Integrations/Divi5/visual-builder/build/loop.js`
- `app/Integrations/Divi5/visual-builder/loop/` (`module.json`, source ok)

### Exclude

- `.git/`, `.gitignore`, IDE folders
- `**/node_modules/`
- Local env / secrets
- Optional (cleaner customer ZIP): `omniloop_technical_blueprint.html`, `docs-assets/` (internal blueprint demo)

### Pre-flight

- [ ] `license.txt` present; third-party section accurate  
- [ ] No custom update / license server code  
- [ ] Works with Divi + WP only (Woo/ACF optional)  
- [ ] PHP 7.4+ compatible  
- [ ] Version in `omniloop.php` matches `CHANGELOG.md`  
- [ ] Fresh install → Rebuild index → insert module → front-end filter works  
- [ ] No console/PHP fatals with Woo active and inactive  

---

## Reviewer walkthrough

1. Activate plugin (Divi 5 theme/builder available).  
2. **OmniLoop → Settings** → ensure `product` or `post` selected → **Rebuild index**.  
3. New page → Visual Builder → add **OmniLoop Filter**.  
4. Set Post Type, Filter Style = Sidebar, Card Style = Classic → Save.  
5. Front end: change a facet; grid updates without full reload; skeleton may flash briefly.  
6. Switch Filter Style to Mega Bar / Chip Only; Card Style to Carousel / Masonry.  
7. With WooCommerce: confirm price, category, and an attribute appear.  
8. Apply filters until empty; confirm empty-state title/message.  
9. Confirm no third-party CDN scripts are enqueued by the plugin on the front.

---

## Support template (first reply)

Thanks for purchasing OmniLoop for Divi.

Please send:

1. WordPress, Divi, PHP, and OmniLoop versions  
2. Whether WooCommerce / ACF are active  
3. Screenshot of OmniLoop → Settings  
4. Page URL (or staging) and steps to reproduce  

Quick checks that fix most issues:

- OmniLoop → Settings → **Rebuild index**  
- Hard refresh the page (Ctrl+F5)  
- Settings → Permalinks → Save (if `/ol/` URLs 404)  
- Confirm the module’s Post Type matches indexed types  

---

## Known positioning

| Say this | Avoid saying this |
| --- | --- |
| Divi 5 Visual Builder extension | Gutenberg block / page builder agnostic |
| WooCommerce optional | Requires WooCommerce |
| One module: OmniLoop Filter | Separate Filter + Loop modules |
| Marketplace handles updates | Built-in license key updater |
