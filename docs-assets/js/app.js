(function () {
  "use strict";

  var ROUTES = [
    { id: "overview", label: "Overview", hint: "ইনট্রো ও স্ট্যাটস" },
    { id: "structure", label: "ফোল্ডার স্ট্রাকচার", hint: "PHP + React কোডবেস" },
    { id: "database", label: "ইন্ডেক্সড ডেটাবেস", hint: "wp_omniloop_index" },
    { id: "react-core", label: "শেয়ার্ড রিয়্যাক্ট কোর", hint: "useOmniQuery" },
    { id: "api", label: "REST API", hint: "JSON কন্ট্রাক্ট" },
    { id: "playbook", label: "ভাইব কোডিং প্লেবুক", hint: "AI প্রম্পট" },
    { id: "live-demo", label: "লাইভ ফিল্টার ডেমো", hint: "ইনস্ট্যান্ট ক্যাটালগ" }
  ];

  var FLOW_COPY = {
    controls:
      "Price slider, taxonomy checkboxes এবং search input ইউজারের ফ্যাসেট স্টেট সেট করে। কোনো পেজ রিলোড হয় না।",
    hook:
      "useOmniQuery স্টেট, 250ms debounce এবং history.pushState URL সিঙ্ক ধরে রাখে। প্রোডাকশনে REST কল যাবে; এই ডক্সে ফিল্টার মেমোরিতেই চলে।",
    renderer:
      "Display renderer গ্রিড, পেজিনেশন বা infinite scroll দেখায়। OmniLoop-এর লক্ষ্য: কার্ড HTML সার্ভার থেকে, ক্লায়েন্টে স্পিনার ছাড়া ক্যাশড রেজাল্ট।"
  };

  var demoState = {
    categories: {},
    brands: {},
    maxPrice: 2500,
    sort: "price_asc"
  };

  var paletteIndex = 0;
  var paletteItems = [];

  function $(id) {
    return document.getElementById(id);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function showToast(message) {
    var el = $("toast");
    if (!el) return;
    el.textContent = message;
    el.classList.add("is-on");
    window.clearTimeout(showToast._t);
    showToast._t = window.setTimeout(function () {
      el.classList.remove("is-on");
    }, 1600);
  }

  function currentRoute() {
    var hash = (window.location.hash || "#overview").replace(/^#/, "");
    for (var i = 0; i < ROUTES.length; i++) {
      if (ROUTES[i].id === hash) return hash;
    }
    return "overview";
  }

  function applyRoute(id, useTransition) {
    var run = function () {
      qsa(".panel").forEach(function (panel) {
        panel.classList.toggle("is-active", panel.id === id);
      });
      qsa(".nav-link").forEach(function (link) {
        var active = link.getAttribute("href") === "#" + id;
        link.classList.toggle("is-active", active);
        if (active) link.setAttribute("aria-current", "page");
        else link.removeAttribute("aria-current");
      });
      closeDrawer();
      var main = $("main-content");
      if (main) main.scrollTop = 0;
      window.scrollTo(0, 0);
    };

    if (
      useTransition &&
      document.startViewTransition &&
      !window.matchMedia("(prefers-reduced-motion: reduce)").matches
    ) {
      document.startViewTransition(run);
    } else {
      run();
    }
  }

  function go(id) {
    if (window.location.hash !== "#" + id) {
      window.location.hash = id;
    } else {
      applyRoute(id, true);
    }
  }

  function routeIndex() {
    var id = currentRoute();
    for (var i = 0; i < ROUTES.length; i++) {
      if (ROUTES[i].id === id) return i;
    }
    return 0;
  }

  function closeDrawer() {
    var sidebar = $("sidebar");
    var backdrop = $("drawer-backdrop");
    var btn = $("menu-btn");
    if (sidebar) sidebar.classList.remove("is-open");
    if (backdrop) backdrop.classList.remove("is-open");
    if (btn) btn.setAttribute("aria-expanded", "false");
  }

  function openDrawer() {
    var sidebar = $("sidebar");
    var backdrop = $("drawer-backdrop");
    var btn = $("menu-btn");
    if (sidebar) sidebar.classList.add("is-open");
    if (backdrop) backdrop.classList.add("is-open");
    if (btn) btn.setAttribute("aria-expanded", "true");
  }

  function isTypingTarget(el) {
    if (!el) return false;
    var tag = el.tagName;
    return tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || el.isContentEditable;
  }

  /* ---------- Command palette ---------- */

  function openPalette() {
    var overlay = $("palette-overlay");
    var input = $("palette-input");
    if (!overlay) return;
    overlay.classList.add("is-open");
    overlay.setAttribute("aria-hidden", "false");
    paletteIndex = 0;
    if (input) {
      input.value = "";
      renderPalette("");
      window.setTimeout(function () {
        input.focus();
      }, 0);
    }
  }

  function closePalette() {
    var overlay = $("palette-overlay");
    if (!overlay) return;
    overlay.classList.remove("is-open");
    overlay.setAttribute("aria-hidden", "true");
  }

  function paletteQuery(raw) {
    return String(raw || "").toLowerCase();
  }

  function renderPalette(raw) {
    var list = $("palette-list");
    if (!list) return;
    while (list.firstChild) list.removeChild(list.firstChild);

    var q = paletteQuery(raw);
    paletteItems = ROUTES.filter(function (route) {
      if (!q) return true;
      return (
        route.label.toLowerCase().indexOf(q) !== -1 ||
        route.hint.toLowerCase().indexOf(q) !== -1 ||
        route.id.toLowerCase().indexOf(q) !== -1
      );
    });

    if (!paletteItems.length) {
      var empty = document.createElement("li");
      empty.className = "palette-empty";
      empty.textContent = "কোনো ম্যাচ নেই";
      list.appendChild(empty);
      return;
    }

    if (paletteIndex >= paletteItems.length) paletteIndex = 0;

    paletteItems.forEach(function (route, i) {
      var li = document.createElement("li");
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "palette-item" + (i === paletteIndex ? " is-active" : "");
      btn.setAttribute("data-route", route.id);

      var label = document.createElement("span");
      label.textContent = route.label;

      var hint = document.createElement("small");
      hint.textContent = route.hint;

      btn.appendChild(label);
      btn.appendChild(hint);
      btn.addEventListener("click", function () {
        closePalette();
        go(route.id);
      });
      li.appendChild(btn);
      list.appendChild(li);
    });
  }

  /* ---------- Copy ---------- */

  function copyFrom(el) {
    if (!el) return;
    var text = el.textContent || "";
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        showToast("কপি হয়েছে");
      }).catch(function () {
        fallbackCopy(text);
      });
    } else {
      fallbackCopy(text);
    }
  }

  function fallbackCopy(text) {
    var ta = document.createElement("textarea");
    ta.value = text;
    ta.setAttribute("readonly", "");
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand("copy");
      showToast("কপি হয়েছে");
    } catch (e) {
      showToast("কপি করা যায়নি");
    }
    document.body.removeChild(ta);
  }

  /* ---------- Architecture diagram ---------- */

  function setFlow(key) {
    qsa(".flow-node").forEach(function (node) {
      node.classList.toggle("is-active", node.getAttribute("data-flow") === key);
    });
    var detail = $("flow-detail-text");
    if (detail) detail.textContent = FLOW_COPY[key] || FLOW_COPY.hook;
  }

  /* ---------- API playground + live demo ---------- */

  function selectedKeys(map) {
    return Object.keys(map).filter(function (k) {
      return map[k];
    });
  }

  function filterCatalog() {
    var catalog = window.OMNILOOP_CATALOG || [];
    var cats = selectedKeys(demoState.categories);
    var brands = selectedKeys(demoState.brands);
    var rows = catalog.filter(function (item) {
      if (item.price > demoState.maxPrice) return false;
      if (cats.length && cats.indexOf(item.category) === -1) return false;
      if (brands.length && brands.indexOf(item.brand) === -1) return false;
      return true;
    });

    if (demoState.sort === "price_desc") {
      rows.sort(function (a, b) { return b.price - a.price; });
    } else if (demoState.sort === "name") {
      rows.sort(function (a, b) { return a.title.localeCompare(b.title); });
    } else {
      rows.sort(function (a, b) { return a.price - b.price; });
    }
    return rows;
  }

  function facetCounts(rows) {
    var counts = {};
    rows.forEach(function (item) {
      counts[item.category] = (counts[item.category] || 0) + 1;
    });
    return counts;
  }

  function pretty(obj) {
    return JSON.stringify(obj, null, 2);
  }

  function updateApiPanels(rows) {
    var cats = selectedKeys(demoState.categories);
    var brands = selectedKeys(demoState.brands);
    var req = {
      post_type: "product",
      per_page: 12,
      page: 1,
      facets: {
        product_cat: cats.length ? cats : ["electronics", "laptops"],
        price_range: [0, demoState.maxPrice]
      },
      sort_by: demoState.sort
    };
    if (brands.length === 1) req.facets._acf_brand = brands[0];

    var posts = rows.slice(0, 12).map(function (item) {
      return {
        id: item.id,
        title: item.title,
        permalink: "https://site.com/product/" + item.id,
        rendered_card: "<div class=\"omniloop-card\">" + item.title + "</div>"
      };
    });

    var res = {
      status: "success",
      total_posts: rows.length,
      total_pages: Math.max(1, Math.ceil(rows.length / 12)),
      posts: posts,
      facets_counts: facetCounts(rows)
    };

    var reqEl = $("api-request");
    var resEl = $("api-response");
    if (reqEl) reqEl.textContent = pretty(req);
    if (resEl) resEl.textContent = pretty(res);
  }

  function renderProducts(rows) {
    var grid = $("demo-grid");
    var count = $("demo-count");
    if (!grid) return;

    while (grid.firstChild) grid.removeChild(grid.firstChild);

    if (count) {
      count.textContent = rows.length + "টি প্রোডাক্ট · 0ms মেমোরি ফিল্টার";
    }

    if (!rows.length) {
      var empty = document.createElement("div");
      empty.className = "empty-state";
      empty.textContent = "কোনো রেজাল্ট নেই — ফিল্টার রিসেট করুন";
      grid.appendChild(empty);
      return;
    }

    rows.forEach(function (item) {
      var card = document.createElement("article");
      card.className = "product-card";

      var h = document.createElement("h4");
      h.textContent = item.title;

      var meta = document.createElement("div");
      meta.className = "product-meta";

      var cat = document.createElement("span");
      cat.textContent = item.brand + " · " + item.category;

      var price = document.createElement("span");
      price.className = "product-price";
      price.textContent = "$" + item.price;

      meta.appendChild(cat);
      meta.appendChild(price);
      card.appendChild(h);
      card.appendChild(meta);
      grid.appendChild(card);
    });
  }

  function refreshDemo() {
    var rows = filterCatalog();
    renderProducts(rows);
    updateApiPanels(rows);
  }

  function bindDemo() {
    qsa("[data-facet]").forEach(function (input) {
      input.addEventListener("change", function () {
        var group = input.getAttribute("data-facet");
        var value = input.getAttribute("data-value");
        if (!group || !value) return;
        demoState[group][value] = !!input.checked;
        refreshDemo();
      });
    });

    var price = $("price-range");
    var priceLabel = $("price-value");
    if (price) {
      price.addEventListener("input", function () {
        demoState.maxPrice = Number(price.value) || 2500;
        if (priceLabel) priceLabel.textContent = "$" + demoState.maxPrice;
        refreshDemo();
      });
    }

    var sort = $("sort-select");
    if (sort) {
      sort.addEventListener("change", function () {
        demoState.sort = sort.value;
        refreshDemo();
      });
    }

    var reset = $("demo-reset");
    if (reset) {
      reset.addEventListener("click", function () {
        demoState.categories = {};
        demoState.brands = {};
        demoState.maxPrice = 2500;
        demoState.sort = "price_asc";
        qsa("[data-facet]").forEach(function (input) {
          input.checked = false;
        });
        if (price) price.value = "2500";
        if (priceLabel) priceLabel.textContent = "$2500";
        if (sort) sort.value = "price_asc";
        refreshDemo();
      });
    }
  }

  function bindApiTabs() {
    qsa("[data-api-tab]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var tab = btn.getAttribute("data-api-tab");
        qsa("[data-api-tab]").forEach(function (b) {
          b.classList.toggle("is-active", b === btn);
        });
        var req = $("api-request");
        var res = $("api-response");
        var copyBtn = $("api-copy-btn");
        if (req) req.hidden = tab !== "request";
        if (res) res.hidden = tab !== "response";
        if (copyBtn) copyBtn.setAttribute("data-copy", tab === "response" ? "api-response" : "api-request");
      });
    });
  }

  function countUp() {
    qsa("[data-count]").forEach(function (el) {
      var target = Number(el.getAttribute("data-count")) || 0;
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
        el.textContent = String(target);
        return;
      }
      var start = 0;
      var t0 = performance.now();
      function tick(now) {
        var p = Math.min(1, (now - t0) / 700);
        el.textContent = String(Math.round(start + (target - start) * p));
        if (p < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
    });
  }

  function init() {
    document.documentElement.classList.add("js-ready");

    applyRoute(currentRoute(), false);
    window.addEventListener("hashchange", function () {
      applyRoute(currentRoute(), true);
    });

    qsa(".nav-link").forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        var href = link.getAttribute("href") || "#overview";
        go(href.replace(/^#/, ""));
      });
    });

    var menuBtn = $("menu-btn");
    if (menuBtn) {
      menuBtn.addEventListener("click", function () {
        var sidebar = $("sidebar");
        if (sidebar && sidebar.classList.contains("is-open")) closeDrawer();
        else openDrawer();
      });
    }
    var backdrop = $("drawer-backdrop");
    if (backdrop) backdrop.addEventListener("click", closeDrawer);

    qsa("[data-copy]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var target = $(btn.getAttribute("data-copy"));
        copyFrom(target);
      });
    });

    qsa(".flow-node").forEach(function (node) {
      node.addEventListener("click", function () {
        setFlow(node.getAttribute("data-flow"));
      });
    });
    setFlow("hook");

    var searchBtn = $("search-trigger");
    if (searchBtn) searchBtn.addEventListener("click", openPalette);

    var palInput = $("palette-input");
    if (palInput) {
      palInput.addEventListener("input", function () {
        paletteIndex = 0;
        renderPalette(palInput.value);
      });
    }

    var palOverlay = $("palette-overlay");
    if (palOverlay) {
      palOverlay.addEventListener("click", function (e) {
        if (e.target === palOverlay) closePalette();
      });
    }

    bindDemo();
    bindApiTabs();
    refreshDemo();
    countUp();

    document.addEventListener("keydown", function (e) {
      var paletteOpen = $("palette-overlay") && $("palette-overlay").classList.contains("is-open");

      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        if (paletteOpen) closePalette();
        else openPalette();
        return;
      }

      if (e.key === "Escape") {
        closePalette();
        closeDrawer();
        return;
      }

      if (paletteOpen) {
        if (e.key === "ArrowDown") {
          e.preventDefault();
          paletteIndex = Math.min(paletteItems.length - 1, paletteIndex + 1);
          renderPalette($("palette-input") ? $("palette-input").value : "");
        } else if (e.key === "ArrowUp") {
          e.preventDefault();
          paletteIndex = Math.max(0, paletteIndex - 1);
          renderPalette($("palette-input") ? $("palette-input").value : "");
        } else if (e.key === "Enter" && paletteItems[paletteIndex]) {
          e.preventDefault();
          closePalette();
          go(paletteItems[paletteIndex].id);
        }
        return;
      }

      if (isTypingTarget(e.target)) return;

      if (e.key === "j") {
        var next = ROUTES[Math.min(ROUTES.length - 1, routeIndex() + 1)];
        go(next.id);
      } else if (e.key === "k") {
        var prev = ROUTES[Math.max(0, routeIndex() - 1)];
        go(prev.id);
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
