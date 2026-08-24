# OmniLoop for Divi — প্রোডাক্ট রোডম্যাপ

**বর্তমান ভার্সন:** 2.4.0
**তৈরির তারিখ:** ২৩ আগস্ট, ২০২৬
**ভিত্তি:** প্রতিযোগী বিশ্লেষণ (Divi Ajax Filter, Divi Loop Extender, JetSmartFilters, FacetWP, BeRocket, Premmerce, YITH, Barn2) + Divi 5-এর নিজস্ব built-in Loop/Filter সিস্টেম

---

## প্রেক্ষাপট

Divi 5 নিজেই এখন ফ্রি Post Filter module দিচ্ছে — তাই OmniLoop-কে "শুধু ফিল্টারিং" থেকে এগিয়ে গিয়ে এমন ভ্যালু দিতে হবে যা native সিস্টেম বা free-tier প্রতিযোগীরা দেয় না। রোডম্যাপ তিনটা মূলনীতি মেনে সাজানো হয়েছে:

1. **Parity আগে** — যেসব ফিচার মার্কেট-স্ট্যান্ডার্ড হয়ে গেছে (SEO meta control, conditional facets) সেগুলো না থাকলে বিক্রি কমবে
2. **Differentiation পরে** — যেখানে প্রতিদ্বন্দ্বীরা দুর্বল সেখানে জোর দেওয়া
3. **প্রতি রিলিজ ছোট ও শিপযোগ্য** — ২.০.১-এর মতো প্যাটার্নে (নির্দিষ্ট বাগ/ফিচার সেট, changelog-এ ট্রেসেবল)

---

## v2.1.0 — SEO ও Parity ফিক্স (সর্বোচ্চ অগ্রাধিকার)

**থিম:** যা না থাকলে প্রতিযোগীর তুলনায় সরাসরি অসুবিধা হয়

| ফিচার | বিবরণ | সোর্স / কেন দরকার |
|---|---|---|
| Filtered page SEO meta control | প্রতি facet-combination-এর জন্য কাস্টম title/description/H1 টেমপ্লেট (placeholder ভ্যারিয়েবল সহ, যেমন `{facet_value} {category}`) | BeRocket, Premmerce, YITH — সবার আছে; OmniLoop-এর SeoUrls.php-এ pretty URL আছে কিন্তু meta নেই |
| Robots/noindex টগল | filtered URL-এ noindex/nofollow প্রয়োগ করার অপশন (per post-type, বা "page > 1" হলে অটো-noindex) | ডুপ্লিকেট কন্টেন্ট থেকে বাঁচা — crawl-বাজেট সমস্যা এড়ানো |
| Sitemap-এ filtered URL অন্তর্ভুক্তি/বর্জন | কোন filtered combination sitemap-এ যাবে তা কনফিগার করা | YITH-এ আছে |
| Empty-result আচরণ | facet value 0-count হলে hide করা (checkbox নিজে), অথবা পুরো facet group hide করা যদি কোনো match না থাকে | Divi Ajax Filter-এ আছে — এখন OmniLoop সব সময় facet দেখায় count=0 থাকলেও |
| Conditional facet visibility | Facet A-তে নির্দিষ্ট ভ্যালু সিলেক্ট হলে Facet B দেখানো/লুকানো (rule-based) | FacetWP-এর সিগনেচার ফিচার, এখন OmniLoop-এ নেই |

**সম্পন্ন হওয়ার মানদণ্ড:** Settings-এ নতুন "SEO" ট্যাব; QueryBuilder-এ facet dependency rule storage; `facet_ui` payload-এ zero-count ভ্যালু ফিল্টার করার অপশন।

---

## v2.2.0 — UX ও কনভার্শন উন্নতি

**থিম:** কেনাকাটার experience-কে প্রতিদ্বন্দ্বীদের সমান বা এগিয়ে নেওয়া

| ফিচার | বিবরণ | সোর্স / কেন দরকার |
|---|---|---|
| Range slider UI (visual) | price_range-এর জন্য draggable dual-handle slider, শুধু input box না | প্রায় সব প্রতিদ্বন্দ্বীতে আছে; বর্তমান OmniLoop input-ভিত্তিক |
| Fuzzy/typo-tolerant search | search box-এ Levenshtein-distance বা soundex-স্টাইল matching, exact LIKE-এর বদলে/পাশাপাশি | FiboSearch, SearchWP-এর মূল ফিচার — OmniLoop এখন strict substring match |
| Facet search-within-facet উন্নতি | (আংশিক আছে — ৮+ ভ্যালুতে search input দেখায়) — highlight matching text, keyboard navigation যোগ | UX পলিশ |
| Filter analytics ড্যাশবোর্ড (বেসিক) | Admin-এ: কোন facet/value সবচেয়ে বেশি ক্লিক হচ্ছে, কোন কম্বিনেশনে 0 result আসছে (৩০ দিনের রোলিং উইন্ডো) | BeRocket-এ আছে; merchandising সিদ্ধান্তে সরাসরি কাজে লাগবে (pin_ids, promotions ঠিক করতে) |

**সম্পন্ন হওয়ার মানদণ্ড:** নতুন `omniloop_facet_events` টেবিল (lightweight, ৩০ দিন পর auto-prune); Admin dashboard widget।

---

## v2.3.0 — ইন্টারন্যাশনাল ও এজেন্সি-ফ্রেন্ডলি

**থিম:** যেসব সাইট মাল্টি-ল্যাংগুয়েজ বা একাধিক ক্লায়েন্টে ডিপ্লয় করে তাদের জন্য

| ফিচার | বিবরণ | সোর্স / কেন দরকার |
|---|---|---|
| WPML / Polylang compatibility | Facet label ও taxonomy term ঠিক ভাষায় দেখানো, প্রতি ভাষার জন্য আলাদা indexed row অথবা runtime translation lookup | Barn2, YITH উল্লেখ করে এটা স্ট্যান্ডার্ড প্রত্যাশা |
| Settings export/import (JSON) | Merchandising rules, filter presets, ACF facet selection — এক সাইট থেকে অন্য সাইটে কপি করার অপশন | এজেন্সি ইউজারদের রিপিটেড সেটআপ কষ্ট কমায়; কোনো প্রতিদ্বন্দ্বী ভালোভাবে করেনি |
| Multi-currency price facet | WooCommerce Multi-Currency প্লাগইনগুলোর (যেমন WOOMULTI) সাথে price_range facet সামঞ্জস্যপূর্ণ করা | FacetWP marketplace listing-এ multi-currency ট্যাগ দেখা যায় — expectation বাড়ছে |

**সম্পন্ন হওয়ার মানদণ্ড:** i18n hook টেস্ট করা দুই ভাষার ডেমো সাইটে; export/import UI Settings পেজে বাটন হিসেবে।

---

## v2.4.0 — Divi 5 নেটিভ ইন্টিগ্রেশন (ডিফারেন্সিয়েটর)

**থিম:** যেখানে কোনো প্রতিদ্বন্দ্বী ভালোভাবে করেনি — OmniLoop-কে শুধু "আরেকটা module" না বানিয়ে Divi 5-এর নিজস্ব সিস্টেমের সাথে মিশিয়ে দেওয়া

| ফিচার | বিবরণ | সোর্স / সুযোগ |
|---|---|---|
| Native Loop Builder-এ facet attach | OmniLoop-এর নিজস্ব Filter module ছাড়াও, Divi 5-এর built-in Loop Builder container-এ সরাসরি facet filter যুক্ত করার অপশন (hook-ভিত্তিক) | Divi Loop Extender এটা করছে সফলভাবে — কিন্তু WooCommerce-নির্দিষ্ট গভীরতা তাদের নেই যা OmniLoop-এর আছে |
| Loop Builder query-তে OmniLoop facet key এক্সপোজ করা | যাতে ইউজার Loop Builder-এর নিজস্ব query condition-এ OmniLoop-এর ইনডেক্স করা facet ব্যবহার করতে পারে (performance advantage — postmeta join এড়ানো) | এটা OmniLoop-এর মূল টেকনিক্যাল সুবিধা (custom index table) কাজে লাগানো |

**সম্পন্ন হওয়ার মানদণ্ড:** Divi 5 Loop Builder API hook-এর সাথে integration টেস্ট; ডকুমেন্টেশনে দুটো ওয়ার্কফ্লো (OmniLoop module বনাম native Loop + OmniLoop facets) স্পষ্টভাবে ব্যাখ্যা করা।

---

## v2.5.0+ — এক্সপ্লোরেটরি / বড় বাজি

**থিম:** যাচাই করে দেখা দরকার এমন আইডিয়া — এখনই কমিট না করে ছোট স্পাইক/প্রোটোটাইপ দিয়ে শুরু করা ভালো

| ফিচার | বিবরণ | ঝুঁকি |
|---|---|---|
| Conversational/AI product finder | "বাজেট + অকেশন" টাইপ প্রশ্ন করে facet auto-select করা, natural language থেকে filter state বানানো | নতুন ট্রেন্ড (২০২৬-এ qualimero-এর মতো আর্টিকেলে উল্লেখ), Divi ecosystem-এ কেউ এখনো ভালো করেনি — কিন্তু API cost, external dependency, এবং scope creep-এর ঝুঁকি আছে |
| Hosted/external search index (Elasticsearch-স্টাইল) | সাইট বড় হলে DB query-নির্ভরতা কমানো | Search Sight, ElasticPress করছে — কিন্তু এটা OmniLoop-এর "self-contained, custom index table" ফিলোজফির বিপরীত; বড় infra commitment |

এই আইটেমগুলো রোডম্যাপে "কমিটেড" না — প্রতিটার আগে একটা ১-সপ্তাহের feasibility spike করা উচিত।

---

## অগ্রাধিকার সারাংশ (এক নজরে)

```
v2.1.0  ████████████████████  Must-have parity (SEO meta, conditional facets, empty-result)
v2.2.0  ███████████████       UX polish (slider, fuzzy search, analytics)
v2.3.0  ██████████            i18n + agency tools (WPML, export/import)
v2.4.0  ████████████          Differentiator (native Loop Builder integration)
v2.5.0+ █████                 Exploratory (AI finder, hosted search) — spike first
```

## প্রতিটা রিলিজের জন্য প্রক্রিয়া (২.০.১-এর প্যাটার্ন অনুসরণ করে)

প্রতিটা ভার্সনের জন্য:
1. প্রতিটা ফিচারের জন্য আলাদা প্রম্পট লেখা (আগের মতো — কোড এজেন্টকে দেওয়ার জন্য)
2. CHANGELOG.md-এ এন্ট্রি + PHPUnit টেস্ট (২.০.১-এ শুরু হওয়া scaffold ব্যবহার করে) আপডেট করা বাধ্যতামূলক
3. প্রতিটা রিলিজের পর আবার diff-ভিত্তিক রিভিউ (যেমন এই কথোপকথনে হয়েছে) — claim vs বাস্তব কোড মিলিয়ে দেখা
4. Marketplace ZIP build script-এ নতুন কোনো dev-only ফাইল যোগ হলে exclude লিস্ট আপডেট করা (`.phpunit.result.cache`-এর মতো ভুল যেন না হয়)

---

*এই ডকুমেন্টটা একটা লিভিং রোডম্যাপ — প্রতিটা রিলিজের পর প্রতিযোগী ল্যান্ডস্কেপ আবার চেক করে প্রায়োরিটি রিভাইজ করা উচিত, বিশেষ করে Divi 5-এর নিজস্ব Loop Builder ফিচার সেট দ্রুত বাড়ছে বলে।*
