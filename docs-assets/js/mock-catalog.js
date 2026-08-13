/* Local mock catalog for instant client-side filtering. No network. */
window.OMNILOOP_CATALOG = [
  { id: 101, title: "MacBook Pro M3", brand: "apple", category: "laptops", price: 1999 },
  { id: 102, title: "MacBook Air M2", brand: "apple", category: "laptops", price: 1199 },
  { id: 103, title: "iPad Pro 12.9", brand: "apple", category: "electronics", price: 1099 },
  { id: 104, title: "iPhone 15 Pro", brand: "apple", category: "electronics", price: 999 },
  { id: 105, title: "AirPods Max", brand: "apple", category: "audio", price: 549 },
  { id: 106, title: "Magic Keyboard", brand: "apple", category: "accessories", price: 299 },
  { id: 201, title: "Galaxy Book4 Pro", brand: "samsung", category: "laptops", price: 1549 },
  { id: 202, title: "Galaxy S24 Ultra", brand: "samsung", category: "electronics", price: 1199 },
  { id: 203, title: "Galaxy Tab S9", brand: "samsung", category: "electronics", price: 799 },
  { id: 204, title: "Galaxy Buds3 Pro", brand: "samsung", category: "audio", price: 249 },
  { id: 205, title: "Galaxy Watch 7", brand: "samsung", category: "accessories", price: 329 },
  { id: 301, title: "WH-1000XM5", brand: "sony", category: "audio", price: 398 },
  { id: 302, title: "WF-1000XM5", brand: "sony", category: "audio", price: 278 },
  { id: 303, title: "A7 IV Camera", brand: "sony", category: "electronics", price: 2498 },
  { id: 304, title: "PlayStation DualSense", brand: "sony", category: "accessories", price: 74 },
  { id: 305, title: "SRS-XG300 Speaker", brand: "sony", category: "audio", price: 248 },
  { id: 401, title: "XPS 15 OLED", brand: "dell", category: "laptops", price: 1899 },
  { id: 402, title: "XPS 13 Plus", brand: "dell", category: "laptops", price: 1299 },
  { id: 403, title: "Alienware m16", brand: "dell", category: "laptops", price: 2199 },
  { id: 404, title: "UltraSharp 27 Monitor", brand: "dell", category: "electronics", price: 649 },
  { id: 405, title: "Pro Wireless Mouse", brand: "dell", category: "accessories", price: 89 },
  { id: 501, title: "ThinkPad X1 Carbon", brand: "lenovo", category: "laptops", price: 1649 },
  { id: 502, title: "Yoga 9i 2-in-1", brand: "lenovo", category: "laptops", price: 1399 },
  { id: 503, title: "Legion Go Handheld", brand: "lenovo", category: "electronics", price: 699 },
  { id: 504, title: "USB-C Hub Pro", brand: "lenovo", category: "accessories", price: 59 },
  { id: 601, title: "Pixel 8 Pro", brand: "google", category: "electronics", price: 899 },
  { id: 602, title: "Pixel Buds Pro", brand: "google", category: "audio", price: 199 },
  { id: 603, title: "Pixelbook Go", brand: "google", category: "laptops", price: 649 },
  { id: 604, title: "Pixel Watch 2", brand: "google", category: "accessories", price: 349 },
  { id: 701, title: "Surface Laptop 5", brand: "microsoft", category: "laptops", price: 1299 },
  { id: 702, title: "Surface Headphones 2", brand: "microsoft", category: "audio", price: 249 },
  { id: 703, title: "Arc Mouse", brand: "microsoft", category: "accessories", price: 79 }
];

window.OMNILOOP_FACETS = {
  categories: [
    { id: "electronics", label: "Electronics" },
    { id: "laptops", label: "Laptops" },
    { id: "audio", label: "Audio" },
    { id: "accessories", label: "Accessories" }
  ],
  brands: [
    { id: "apple", label: "Apple" },
    { id: "samsung", label: "Samsung" },
    { id: "sony", label: "Sony" },
    { id: "dell", label: "Dell" },
    { id: "lenovo", label: "Lenovo" },
    { id: "google", label: "Google" },
    { id: "microsoft", label: "Microsoft" }
  ]
};
