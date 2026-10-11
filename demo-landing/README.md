# OmniLoop landing demo

Interactive product landing. **Not** part of the plugin ZIP. Do not copy this folder into `app/`.

Local preview: open `index.html` in a browser, or rebuild it after editing sections:

```powershell
powershell -ExecutionPolicy Bypass -File .\demo-landing\assemble.ps1
```

`http://itsdivi.local/wp-content/plugins/omniloop/demo-landing/index.html`

---

## Elementor-এ কীভাবে লাগাবে

প্রতিটা সেকশন আলাদা HTML — তাই Elementor-এ **সেকশন/কন্টেইনার টেনে রিঅরডার** করা যায়, আর মাঝখানে নতুন সেকশন **ইনসার্ট** করা যায়।

### একবার (assets)

1. পুরো `demo-landing` ফোল্ডার কপি করো:
   `wp-content/uploads/omniloop-landing/`
2. পেজের **সবচেয়ে উপরে** একটা HTML widget → `sections/00-assets.html` পেস্ট করো।
   URL যদি অন্য হয়, ওই ফাইলের `href` / `src` বদলাও।
3. চাইলে Theme Builder / Custom Code-এও CSS+JS রাখা যায় — শুধু **একবার** লোড হোক।

### তারপর সেকশন

প্রতিটা Elementor **Container (full width)** → ভিতরে **HTML** widget → সংশ্লিষ্ট ফাইল পেস্ট:

| ফাইল | সেকশন | সরানো / মাঝে ঢোকানো |
| --- | --- | --- |
| `01-nav.html` | Sticky nav (hamburger on small screens) | চাইলে বাদ |
| `02-hero.html` | Hero | |
| `04-live.html` | Live catalog (ইন্টারঅ্যাকটিভ) | হিরোর ঠিক পরে রাখা ভালো |
| `03-proof.html` | Setup — 3 steps | |
| `03-why.html` | Why OmniLoop | |
| `05-layouts.html` | Filter layout switcher | |
| `06-cards.html` | Card style switcher | |
| `07-builder.html` | Card item reorder | |
| `08-merch.html` | Merchandising + sync | |
| `09-seo.html` | Pretty URL playground | |
| `10-features.html` | Feature grid | নতুন কার্ড এখানে যোগ করো |
| `11-faq.html` | FAQ | নতুন প্রশ্ন কপি-পেস্ট |
| `12-cta.html` | Closing CTA | |

নতুন সেকশন বানাতে: যেকোনো ফাইল কপি → `data-ol-landing="my-block"` ইউনিক রাখো → ক্লাস `ol-landing` রাখো → Elementor-এ দুই সেকশনের মাঝে নতুন Container বসাও।

### নোট

- Elementor HTML widget কখনো `<script>` স্ট্রিপ করে। তাই JS `00-assets` বা **Elementor → Custom Code** (footer) থেকে দাও।
- থিমের গ্লোবাল টাইপোগ্রাফি কিছুটা ওভাররাইড করতে পারে; সব স্টাইল `.ol-landing` নেমস্পেসে।
- `index.html` শুধু প্রিভিউ — Elementor-এ সেটা পুরোটা পেস্ট করো না।
