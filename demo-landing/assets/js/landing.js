(function () {
	"use strict";

	var PAGE = 9;

	var CATALOG = [
		{ id: 1, title: "Harbor Tote", cat: "bags", color: "navy", price: 148, compare: 0, rating: 4.8, reviews: 126, stock: 3, sale: false, pin: true, featured: true, shape: "tote", sku: "HG-TOTE-NVY", material: "canvas", sizes: ["one"], origin: "Portugal", care: "Spot clean. Do not machine wash.", blurb: "Waxed canvas, brass hardware, and a sleeve that fits a 13-inch laptop." },
		{ id: 2, title: "Clay Daypack", cat: "bags", color: "clay", price: 128, compare: 158, rating: 4.6, reviews: 84, stock: 11, sale: true, pin: false, featured: false, shape: "pack", sku: "HG-PACK-CLY", material: "canvas", sizes: ["one"], origin: "Portugal", care: "Spot clean. Air dry.", blurb: "A weekday pack with a padded back panel and a quiet clay finish." },
		{ id: 3, title: "Sand Weekender", cat: "bags", color: "sand", price: 186, rating: 4.7, reviews: 61, stock: 6, sale: false, pin: false, featured: false, shape: "tote", sku: "HG-WEEK-SND", material: "canvas", sizes: ["one"], origin: "Portugal", care: "Spot clean.", blurb: "Carry-on size. Shoe pocket, laundry sleeve, and a trolley pass-through." },
		{ id: 4, title: "Forest Shopper", cat: "bags", color: "forest", price: 96, rating: 4.4, reviews: 203, stock: 2, sale: false, pin: false, featured: false, shape: "tote", sku: "HG-SHOP-FOR", material: "canvas", sizes: ["one"], origin: "Portugal", care: "Spot clean.", blurb: "Open-top shopper that stands on its own at the checkout." },
		{ id: 5, title: "Ink Field Jacket", cat: "apparel", color: "ink", price: 220, rating: 4.9, reviews: 47, stock: 4, sale: false, pin: true, featured: true, shape: "jacket", sku: "HG-JKT-INK", material: "wool", sizes: ["s", "m", "l"], origin: "Scotland", care: "Dry clean only.", blurb: "Unlined wool, four pockets, a collar that actually stands up to wind." },
		{ id: 6, title: "Linen Overshirt", cat: "apparel", color: "sand", price: 118, rating: 4.5, reviews: 91, stock: 14, sale: false, pin: false, featured: false, shape: "shirt", sku: "HG-OVR-SND", material: "linen", sizes: ["s", "m", "l"], origin: "Lithuania", care: "Cold wash, hang dry.", blurb: "Mid-weight linen. Wear it open over a tee or closed as a shirt." },
		{ id: 7, title: "Clay Knit Polo", cat: "apparel", color: "clay", price: 78, compare: 98, rating: 4.3, reviews: 33, stock: 0, sale: true, pin: false, featured: false, shape: "shirt", sku: "HG-POL-CLY", material: "wool", sizes: ["s", "m", "l"], origin: "Portugal", care: "Hand wash.", blurb: "Fine merino polo. Sold out in this color until the next mill run." },
		{ id: 8, title: "Navy Work Tee", cat: "apparel", color: "navy", price: 42, rating: 4.2, reviews: 310, stock: 28, sale: false, pin: false, featured: false, shape: "shirt", sku: "HG-TEE-NVY", material: "linen", sizes: ["s", "m", "l"], origin: "Portugal", care: "Cold wash.", blurb: "Heavyweight tee that keeps its neck after a year of washes." },
		{ id: 9, title: "Stone Pourer", cat: "home", color: "sand", price: 54, rating: 4.8, reviews: 88, stock: 9, sale: false, pin: false, featured: false, shape: "pour", sku: "HG-POUR-SND", material: "ceramic", sizes: ["one"], origin: "Japan", care: "Dishwasher safe.", blurb: "800 ml pourer. Drip-cut spout, unglazed foot, sand glaze." },
		{ id: 10, title: "Forest Planter", cat: "home", color: "forest", price: 64, rating: 4.6, reviews: 54, stock: 7, sale: false, pin: false, featured: false, shape: "pot", sku: "HG-POT-FOR", material: "ceramic", sizes: ["one"], origin: "Japan", care: "Wipe with a damp cloth.", blurb: "Drainage hole, matching saucer, matte forest glaze." },
		{ id: 11, title: "Ink Table Lamp", cat: "home", color: "ink", price: 168, rating: 4.7, reviews: 41, stock: 5, sale: false, pin: false, featured: false, shape: "lamp", sku: "HG-LAMP-INK", material: "brass", sizes: ["one"], origin: "India", care: "Dust with a dry cloth. E27 bulb not included.", blurb: "Brass stem, linen shade, a dimmer-friendly switch on the cord." },
		{ id: 12, title: "Clay Serving Bowl", cat: "home", color: "clay", price: 48, rating: 4.4, reviews: 72, stock: 0, sale: false, pin: false, featured: false, shape: "bowl", sku: "HG-BWL-CLY", material: "ceramic", sizes: ["one"], origin: "Japan", care: "Dishwasher safe.", blurb: "Wide serving bowl. Food-safe glaze, slightly irregular rim." },
		{ id: 13, title: "Brass Key Ring", cat: "accessories", color: "sand", price: 24, rating: 4.1, reviews: 190, stock: 40, sale: false, pin: false, featured: false, shape: "ring", sku: "HG-KEY-BRS", material: "brass", sizes: ["one"], origin: "Japan", care: "Brass will patina. Polish if you prefer bright.", blurb: "Cast brass ring. Heavy enough to find in a bag." },
		{ id: 14, title: "Navy Card Case", cat: "accessories", color: "navy", price: 36, compare: 48, rating: 4.5, reviews: 118, stock: 18, sale: true, pin: false, featured: false, shape: "case", sku: "HG-CARD-NVY", material: "leather", sizes: ["one"], origin: "Spain", care: "Wipe with a dry cloth.", blurb: "Four cards, a note fold, and a snap that does not chew the leather." },
		{ id: 15, title: "Forest Belt", cat: "accessories", color: "forest", price: 58, rating: 4.6, reviews: 67, stock: 8, sale: false, pin: false, featured: false, shape: "belt", sku: "HG-BELT-FOR", material: "leather", sizes: ["s", "m", "l"], origin: "Spain", care: "Condition twice a year.", blurb: "Vegetable-tanned strap, brass buckle, stitched keeper." },
		{ id: 16, title: "Ink Cap", cat: "accessories", color: "ink", price: 32, rating: 4.0, reviews: 22, stock: 1, sale: false, pin: false, featured: false, shape: "cap", sku: "HG-CAP-INK", material: "wool", sizes: ["s", "m"], origin: "Scotland", care: "Spot clean.", blurb: "Unstructured wool cap. Last one in this dye lot." }
	];

	var LABELS = {
		bags: "Bags",
		apparel: "Apparel",
		home: "Home",
		accessories: "Accessories",
		navy: "Navy",
		clay: "Clay",
		sand: "Sand",
		forest: "Forest",
		ink: "Ink",
		canvas: "Canvas",
		linen: "Linen",
		wool: "Wool",
		ceramic: "Ceramic",
		brass: "Brass",
		leather: "Leather",
		s: "S",
		m: "M",
		l: "L",
		one: "One size"
	};

	var LAYOUT_COPY = {
		sidebar: "Facets stay on the left. Best when a shop has color, size, price, and a few taxonomies.",
		accordion: "Same column, grouped so long attribute lists do not sprawl. Click a heading in production to collapse it.",
		toolbar: "A compact row above the grid. Use it when you only expose three or four facets.",
		mega: "A wide bar: many swatches in one glance. Typical for apparel with color plus size.",
		pills: "Categories as chips. Fast for blogs, recipes, and light shops.",
		chip: "Hide filters until someone asks. Clean merchandising and landing pages."
	};

	var CARD_COPY = {
		classic: "Image, title, meta, price. The default Woo-style card.",
		overlay: "Type sits on the photo. Strong for apparel and lookbooks.",
		list: "Horizontal row with room for an excerpt. Good for search results.",
		compact: "Shorter media. Pack more SKUs above the fold.",
		masonry: "Uneven media heights. Useful when product photos are mixed crops.",
		carousel: "In the module this becomes a slider. Here it stays a row so you can still click a card."
	};

	function ready(fn) {
		if (document.readyState === "loading") {
			document.addEventListener("DOMContentLoaded", fn);
		} else {
			fn();
		}
	}

	function qs(root, sel) {
		return (root || document).querySelector(sel);
	}

	function qsa(root, sel) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function money(n) {
		return "$" + n;
	}

	function byId(id) {
		for (var i = 0; i < CATALOG.length; i++) {
			if (CATALOG[i].id === id) {
				return CATALOG[i];
			}
		}
		return null;
	}

	function selected(map) {
		return Object.keys(map || {}).filter(function (k) {
			return map[k];
		});
	}

	function emptyMap() {
		return {};
	}

	function cloneFilters(state) {
		return {
			colors: Object.assign({}, state.colors),
			cats: Object.assign({}, state.cats),
			sizes: Object.assign({}, state.sizes),
			mats: Object.assign({}, state.mats),
			max: state.max,
			q: state.q,
			inStock: state.inStock,
			onSale: state.onSale,
			minRating: state.minRating,
			sort: "featured"
		};
	}

	function stars(n) {
		var pct = Math.round((Number(n) / 5) * 100);
		return '<span class="ol-stars" style="--ol-star:' + pct + '%" title="' + n + ' of 5"></span>';
	}

	function priceHTML(p) {
		var html = '<span>' + money(p.price) + "</span>";
		if (p.compare && p.compare > p.price) {
			html += " <s>" + money(p.compare) + "</s>";
		}
		return html;
	}

	function badge(p, opts) {
		opts = opts || {};
		if (p.stock === 0) {
			return '<span class="ol-card__badge">Sold out</span>';
		}
		if (opts.urgent !== false && p.stock <= 3) {
			return '<span class="ol-card__badge ol-card__badge--low">Only ' + p.stock + " left</span>";
		}
		if (opts.badges !== false && p.sale) {
			return '<span class="ol-card__badge ol-card__badge--sale">Sale</span>';
		}
		if (opts.badges !== false && p.pin) {
			return '<span class="ol-card__badge">Pinned</span>';
		}
		return "";
	}

	function media(p, opts) {
		return '<div class="ol-card__media ol-ph ol-ph--' + p.color + " ol-art ol-art--" + p.shape + '"><span class="ol-art__obj"></span>' + badge(p, opts) + "</div>";
	}

	function sizeDots(p) {
		if (!p.sizes || p.sizes.length < 2) {
			return "";
		}
		return '<div class="ol-card__sizes">' + p.sizes.map(function (s) {
			return "<span>" + LABELS[s] + "</span>";
		}).join("") + "</div>";
	}

	function cardHTML(p, extra, opts) {
		opts = opts || {};
		var blurb = opts.view === "list" ? '<p class="ol-card__blurb">' + p.blurb + "</p>" : "";
		var sku = opts.view === "list" ? '<div class="ol-card__sku">' + p.sku + " · " + LABELS[p.material] + " · " + p.origin + "</div>" : "";
		return (
			'<article class="ol-card" data-ol-id="' + p.id + '"' + (extra || "") + ">" +
				media(p, opts) +
				'<div class="ol-card__body">' +
					"<h3>" + p.title + "</h3>" +
					'<div class="ol-card__meta"><span>' + LABELS[p.cat] + "</span><span>" + stars(p.rating) + " " + p.reviews + "</span></div>" +
					blurb +
					'<div class="ol-card__price">' + priceHTML(p) + "</div>" +
					sku +
					sizeDots(p) +
				"</div>" +
				'<span class="ol-card__peek">Quick view</span>' +
			"</article>"
		);
	}

	function filterList(state) {
		var colors = selected(state.colors);
		var cats = selected(state.cats);
		var sizes = selected(state.sizes);
		var mats = selected(state.mats);
		var q = (state.q || "").trim().toLowerCase();
		var list = CATALOG.filter(function (p) {
			if (colors.length && colors.indexOf(p.color) === -1) {
				return false;
			}
			if (cats.length && cats.indexOf(p.cat) === -1) {
				return false;
			}
			if (sizes.length) {
				var hit = false;
				for (var i = 0; i < p.sizes.length; i++) {
					if (sizes.indexOf(p.sizes[i]) !== -1) {
						hit = true;
						break;
					}
				}
				if (!hit) {
					return false;
				}
			}
			if (mats.length && mats.indexOf(p.material) === -1) {
				return false;
			}
			if (p.price > (state.max || 220)) {
				return false;
			}
			if (q && p.title.toLowerCase().indexOf(q) === -1 && p.blurb.toLowerCase().indexOf(q) === -1 && p.sku.toLowerCase().indexOf(q) === -1) {
				return false;
			}
			if (state.inStock && p.stock === 0) {
				return false;
			}
			if (state.onSale && !p.sale) {
				return false;
			}
			if (state.minRating && p.rating < 4.5) {
				return false;
			}
			return true;
		});
		list.sort(function (a, b) {
			if (state.sort === "price-asc") {
				return a.price - b.price;
			}
			if (state.sort === "price-desc") {
				return b.price - a.price;
			}
			if (state.sort === "rating") {
				return b.rating - a.rating || b.reviews - a.reviews;
			}
			if (state.sort === "newest") {
				return b.id - a.id;
			}
			if (state.sort === "name") {
				return a.title < b.title ? -1 : 1;
			}
			return (b.pin - a.pin) || (a.stock === 0) - (b.stock === 0) || a.id - b.id;
		});
		return list;
	}

	function countFacet(state, group, value) {
		var s = cloneFilters(state);
		s[group] = {};
		s[group][value] = true;
		return filterList(s).length;
	}

	function pathFor(state) {
		var bits = [];
		selected(state.colors).forEach(function (c) {
			bits.push("color/" + c);
		});
		selected(state.cats).forEach(function (c) {
			bits.push("category/" + c);
		});
		selected(state.sizes).forEach(function (c) {
			bits.push("size/" + c);
		});
		selected(state.mats).forEach(function (c) {
			bits.push("material/" + c);
		});
		if (state.onSale) {
			bits.push("sale/1");
		}
		var qsBits = [];
		if (state.max < 220) {
			qsBits.push("max=" + state.max);
		}
		if (state.q) {
			qsBits.push("q=" + encodeURIComponent(state.q));
		}
		var path = bits.length ? "/shop/<em>ol</em>/" + bits.join("/") + "/" : "/shop/";
		if (qsBits.length) {
			path += "?" + qsBits.join("&");
		}
		return path;
	}

	function toast(root, msg) {
		var el = qs(root, "[data-ol-toast]");
		if (!el) {
			return;
		}
		el.textContent = msg;
		el.classList.add("is-on");
		clearTimeout(el._t);
		el._t = setTimeout(function () {
			el.classList.remove("is-on");
		}, 2000);
	}

	function setQv(root, on) {
		var qv = qs(root, "[data-ol-qv]");
		var scrim = qs(root, "[data-ol-scrim]");
		if (qv) {
			qv.classList.toggle("is-on", on);
			qv.setAttribute("aria-hidden", on ? "false" : "true");
		}
		if (scrim) {
			scrim.classList.toggle("is-on", on);
		}
	}

	function openQuick(root, p) {
		var qv = qs(root, "[data-ol-qv]");
		if (!qv || !p) {
			return;
		}
		qs(qv, "[data-ol-qv-cat]").textContent = LABELS[p.cat] + " / " + LABELS[p.color] + " / " + LABELS[p.material];
		qs(qv, "[data-ol-qv-title]").textContent = p.title;
		qs(qv, "[data-ol-qv-price]").innerHTML = priceHTML(p);
		qs(qv, "[data-ol-qv-rating]").innerHTML = stars(p.rating) + " <b>" + p.rating + "</b> · " + p.reviews + " reviews";
		qs(qv, "[data-ol-qv-copy]").textContent = p.blurb;
		qs(qv, "[data-ol-qv-sku]").textContent = p.sku + " · Made in " + p.origin;
		qs(qv, "[data-ol-qv-specs]").innerHTML =
			"<div><dt>Material</dt><dd>" + LABELS[p.material] + "</dd></div>" +
			"<div><dt>Origin</dt><dd>" + p.origin + "</dd></div>" +
			"<div><dt>Care</dt><dd>" + p.care + "</dd></div>" +
			"<div><dt>Stock</dt><dd>" + (p.stock === 0 ? "Sold out. OmniLoop can push this to the bottom of the loop." : p.stock + " in warehouse") + "</dd></div>";
		qs(qv, "[data-ol-qv-media]").className = "ol-qv__media ol-ph ol-ph--" + p.color + " ol-art ol-art--" + p.shape;
		qs(qv, "[data-ol-qv-media]").innerHTML = '<span class="ol-art__obj"></span>' + badge(p);
		var sizes = qs(qv, "[data-ol-qv-sizes]");
		sizes.innerHTML = p.sizes.map(function (s, i) {
			return '<button type="button" class="ol-pill' + (i === 0 ? " is-on" : "") + '" data-ol-qv-size="' + s + '">' + LABELS[s] + "</button>";
		}).join("");
		qv.setAttribute("data-size", p.sizes[0]);
		qv.setAttribute("data-qty", "1");
		qs(qv, "[data-ol-qv-qty-n]").textContent = "1";
		qs(qv, "[data-ol-qv-cart]").disabled = p.stock === 0;
		qs(qv, "[data-ol-qv-cart]").textContent = p.stock === 0 ? "Sold out" : "Add to cart";
		qv.setAttribute("data-id", String(p.id));
		setQv(root, true);
	}

	function closeQuick(root) {
		setQv(root, false);
	}

	function openLiveQuick(id) {
		var live = document.querySelector('[data-ol-landing="live"]');
		if (live) {
			openQuick(live, byId(id));
		}
	}

	function defaultState() {
		return {
			colors: emptyMap(),
			cats: emptyMap(),
			sizes: emptyMap(),
			mats: emptyMap(),
			max: 220,
			q: "",
			sort: "featured",
			view: "grid",
			page: 1,
			inStock: false,
			onSale: false,
			minRating: false
		};
	}

	function initNav(root) {
		if (root.getAttribute("data-ol-ready") === "1") {
			return;
		}
		root.setAttribute("data-ol-ready", "1");
		var nav = qs(root, ".ol-nav");
		var toggle = qs(root, "[data-ol-nav-toggle]");

		function close() {
			if (nav) {
				nav.classList.remove("is-open");
			}
			if (toggle) {
				toggle.setAttribute("aria-expanded", "false");
			}
		}

		if (toggle) {
			toggle.addEventListener("click", function (e) {
				e.stopPropagation();
				var open = nav && nav.classList.contains("is-open");
				if (open) {
					close();
				} else if (nav) {
					nav.classList.add("is-open");
					toggle.setAttribute("aria-expanded", "true");
				}
			});
		}
		root.addEventListener("click", function (e) {
			if (e.target.closest(".ol-nav__links a") || e.target.closest(".ol-nav__cta")) {
				close();
			}
		});
		document.addEventListener("keydown", function (e) {
			if (e.key === "Escape") {
				close();
			}
		});
	}

	function initHero(root) {
		if (root.getAttribute("data-ol-ready") === "1") {
			return;
		}
		root.setAttribute("data-ol-ready", "1");
		var color = "navy";

		function paint() {
			qsa(root, "[data-ol-hero-color]").forEach(function (b) {
				var on = b.getAttribute("data-ol-hero-color") === color;
				b.classList.toggle("is-on", on);
				b.setAttribute("aria-pressed", on ? "true" : "false");
			});
			var n = 0;
			var names = [];
			qsa(root, "[data-ol-hero-item]").forEach(function (card) {
				var on = card.getAttribute("data-ol-hero-item") === color;
				card.classList.toggle("is-dim", !on);
				if (on) {
					n += 1;
					var t = qs(card, "strong");
					if (t) {
						names.push(t.textContent);
					}
				}
			});
			var float = qs(root, "[data-ol-hero-float]");
			if (float) {
				float.innerHTML = "<b>" + n + " in " + (LABELS[color] || color) + "</b><span>" + names.join(" · ") + "</span>";
			}
		}

		root.addEventListener("click", function (e) {
			var sw = e.target.closest("[data-ol-hero-color]");
			if (sw) {
				color = sw.getAttribute("data-ol-hero-color");
				paint();
				return;
			}
			var item = e.target.closest("[data-ol-hero-item]");
			if (item && !item.classList.contains("is-dim")) {
				var live = document.querySelector('[data-ol-landing="live"]');
				if (live) {
					openQuick(live, byId(parseInt(item.getAttribute("data-ol-open"), 10)));
					live.scrollIntoView({ behavior: "smooth", block: "start" });
				}
			}
		});
		paint();
	}

	function initDemo(root) {
		if (root.getAttribute("data-ol-ready") === "1") {
			return;
		}
		root.setAttribute("data-ol-ready", "1");

		var state = defaultState();
		var tried = { navy: false, bags: false };

		function setFiltersOpen(on) {
			var demo = qs(root, ".ol-demo");
			var toggle = qs(root, "[data-ol-filters-open]");
			var scrim = qs(root, "[data-ol-filters-scrim]");
			if (demo) {
				demo.classList.toggle("is-filters-open", on);
			}
			if (toggle) {
				toggle.setAttribute("aria-expanded", on ? "true" : "false");
			}
			if (scrim) {
				if (on) {
					scrim.removeAttribute("hidden");
				} else {
					scrim.setAttribute("hidden", "");
				}
			}
			document.body.classList.toggle("ol-filters-lock", on);
		}

		function activeFilterCount() {
			return selected(state.colors).length + selected(state.cats).length + selected(state.sizes).length + selected(state.mats).length + (state.max < 220 ? 1 : 0) + (state.q ? 1 : 0) + (state.inStock ? 1 : 0) + (state.onSale ? 1 : 0) + (state.minRating ? 1 : 0);
		}

		function hideCoachIfDone() {
			var coach = qs(root, "[data-ol-coach]");
			if (coach && tried.navy && tried.bags) {
				coach.classList.add("is-done");
			}
		}

		function syncToggles() {
			qsa(root, "[data-ol-color]").forEach(function (b) {
				var key = b.getAttribute("data-ol-color");
				b.classList.toggle("is-on", !!state.colors[key]);
				var n = countFacet(state, "colors", key);
				var badgeN = qs(b, "[data-ol-n]");
				if (badgeN) {
					badgeN.textContent = String(n);
				}
				b.classList.toggle("is-zero", n === 0);
			});
			qsa(root, "[data-ol-cat]").forEach(function (b) {
				var key = b.getAttribute("data-ol-cat");
				b.classList.toggle("is-on", !!state.cats[key]);
				var n = countFacet(state, "cats", key);
				var badgeN = qs(b, "[data-ol-n]");
				if (badgeN) {
					badgeN.textContent = String(n);
				}
				b.classList.toggle("is-zero", n === 0);
			});
			qsa(root, "[data-ol-size]").forEach(function (b) {
				var key = b.getAttribute("data-ol-size");
				b.classList.toggle("is-on", !!state.sizes[key]);
				var n = countFacet(state, "sizes", key);
				var badgeN = qs(b, "[data-ol-n]");
				if (badgeN) {
					badgeN.textContent = String(n);
				}
				b.classList.toggle("is-zero", n === 0);
			});
			qsa(root, "[data-ol-mat]").forEach(function (b) {
				var key = b.getAttribute("data-ol-mat");
				b.classList.toggle("is-on", !!state.mats[key]);
				var n = countFacet(state, "mats", key);
				var badgeN = qs(b, "[data-ol-n]");
				if (badgeN) {
					badgeN.textContent = String(n);
				}
				b.classList.toggle("is-zero", n === 0);
			});
			qsa(root, "[data-ol-view]").forEach(function (b) {
				b.classList.toggle("is-on", b.getAttribute("data-ol-view") === state.view);
			});
			qsa(root, "[data-ol-flag]").forEach(function (b) {
				var flag = b.getAttribute("data-ol-flag");
				b.classList.toggle("is-on", !!state[flag]);
			});
		}

		function render() {
			var list = filterList(state);
			var grid = qs(root, "[data-ol-grid]");
			var count = qs(root, "[data-ol-count]");
			var chips = qs(root, "[data-ol-chips]");
			var path = qs(root, "[data-ol-path]");
			var pager = qs(root, "[data-ol-pager]");
			var pages = Math.max(1, Math.ceil(list.length / PAGE));
			if (state.page > pages) {
				state.page = pages;
			}
			var start = (state.page - 1) * PAGE;
			var slice = list.slice(start, start + PAGE);
			syncToggles();
			if (count) {
				count.textContent = list.length
					? "Showing " + (start + 1) + "-" + (start + slice.length) + " of " + list.length + (list.length === 1 ? " product" : " products")
					: "0 products";
			}
			var filterN = qs(root, "[data-ol-filter-count]");
			if (filterN) {
				var nActive = activeFilterCount();
				filterN.textContent = String(nActive);
				if (nActive) {
					filterN.removeAttribute("hidden");
				} else {
					filterN.setAttribute("hidden", "");
				}
			}
			if (path) {
				path.innerHTML = pathFor(state);
			}
			if (chips) {
				var bits = [];
				function chip(label, key) {
					bits.push('<span class="ol-chip">' + label + ' <button type="button" data-ol-clear="' + key + '">x</button></span>');
				}
				selected(state.colors).forEach(function (c) {
					chip(LABELS[c], "color:" + c);
				});
				selected(state.cats).forEach(function (c) {
					chip(LABELS[c], "cat:" + c);
				});
				selected(state.sizes).forEach(function (c) {
					chip("Size " + LABELS[c], "size:" + c);
				});
				selected(state.mats).forEach(function (c) {
					chip(LABELS[c], "mat:" + c);
				});
				if (state.max < 220) {
					chip("Up to " + money(state.max), "max");
				}
				if (state.q) {
					chip("Search: " + state.q, "q");
				}
				if (state.inStock) {
					chip("In stock", "inStock");
				}
				if (state.onSale) {
					chip("On sale", "onSale");
				}
				if (state.minRating) {
					chip("4.5 stars+", "minRating");
				}
				if (bits.length) {
					bits.push('<button type="button" class="ol-shop__clear" data-ol-clear="all">Clear all</button>');
				}
				chips.innerHTML = bits.join("");
			}
			if (pager) {
				if (pages <= 1) {
					pager.innerHTML = "";
				} else {
					var html = "";
					for (var i = 1; i <= pages; i++) {
						html += '<button type="button" class="' + (i === state.page ? "is-on" : "") + '" data-ol-page="' + i + '">' + i + "</button>";
					}
					pager.innerHTML = html;
				}
			}
			if (!grid) {
				return;
			}
			grid.setAttribute("data-view", state.view);
			if (!list.length) {
				grid.innerHTML = '<div class="ol-empty"><strong>Nothing matches this mix.</strong><p>Clear a chip, raise the price cap, or turn off In stock. This is the custom empty state OmniLoop lets you write in the module.</p><button type="button" class="ol-btn" data-ol-clear="all">Clear all filters</button></div>';
				return;
			}
			grid.innerHTML = slice.map(function (p, i) {
				return cardHTML(p, ' style="--ol-i:' + i + '"', { view: state.view });
			}).join("");
		}

		function bump() {
			state.page = 1;
			render();
		}

		root.addEventListener("click", function (e) {
			var tryBtn = e.target.closest("[data-ol-try]");
			if (tryBtn) {
				var kind = tryBtn.getAttribute("data-ol-try");
				if (kind === "navy") {
					state.colors.navy = true;
					tried.navy = true;
				}
				if (kind === "bags") {
					state.cats.bags = true;
					tried.bags = true;
				}
				hideCoachIfDone();
				bump();
				return;
			}
			if (e.target.closest("[data-ol-filters-open]")) {
				setFiltersOpen(true);
				return;
			}
			if (e.target.closest("[data-ol-filters-close]") || e.target.closest("[data-ol-filters-scrim]")) {
				setFiltersOpen(false);
				return;
			}
			var colorBtn = e.target.closest("[data-ol-color]");
			if (colorBtn) {
				var c = colorBtn.getAttribute("data-ol-color");
				state.colors[c] = !state.colors[c];
				bump();
				return;
			}
			var catBtn = e.target.closest("[data-ol-cat]");
			if (catBtn && catBtn.hasAttribute("data-ol-cat")) {
				state.cats[catBtn.getAttribute("data-ol-cat")] = !state.cats[catBtn.getAttribute("data-ol-cat")];
				bump();
				return;
			}
			var sizeBtn = e.target.closest("[data-ol-size]");
			if (sizeBtn) {
				var sz = sizeBtn.getAttribute("data-ol-size");
				state.sizes[sz] = !state.sizes[sz];
				bump();
				return;
			}
			var matBtn = e.target.closest("[data-ol-mat]");
			if (matBtn) {
				var mk = matBtn.getAttribute("data-ol-mat");
				state.mats[mk] = !state.mats[mk];
				bump();
				return;
			}
			var flagBtn = e.target.closest("[data-ol-flag]");
			if (flagBtn) {
				var flag = flagBtn.getAttribute("data-ol-flag");
				state[flag] = !state[flag];
				bump();
				return;
			}
			var viewBtn = e.target.closest("[data-ol-view]");
			if (viewBtn) {
				state.view = viewBtn.getAttribute("data-ol-view");
				render();
				return;
			}
			var pageBtn = e.target.closest("[data-ol-page]");
			if (pageBtn) {
				state.page = parseInt(pageBtn.getAttribute("data-ol-page"), 10) || 1;
				render();
				return;
			}
			var clear = e.target.closest("[data-ol-clear]");
			if (clear) {
				var key = clear.getAttribute("data-ol-clear");
				if (key === "all") {
					state = defaultState();
					state.view = qs(root, "[data-ol-view].is-on") ? qs(root, "[data-ol-view].is-on").getAttribute("data-ol-view") : "grid";
					var maxEl = qs(root, "[data-ol-max]");
					var qEl = qs(root, "[data-ol-q]");
					if (maxEl) {
						maxEl.value = "220";
					}
					if (qs(root, "[data-ol-max-label]")) {
						qs(root, "[data-ol-max-label]").textContent = "$220";
					}
					if (qEl) {
						qEl.value = "";
					}
					var sortEl = qs(root, "[data-ol-sort]");
					if (sortEl) {
						sortEl.value = "featured";
					}
				} else if (key === "max") {
					state.max = 220;
					qs(root, "[data-ol-max]").value = "220";
					qs(root, "[data-ol-max-label]").textContent = "$220";
				} else if (key === "q") {
					state.q = "";
					qs(root, "[data-ol-q]").value = "";
				} else if (key === "inStock" || key === "onSale" || key === "minRating") {
					state[key] = false;
				} else if (key.indexOf("color:") === 0) {
					state.colors[key.slice(6)] = false;
				} else if (key.indexOf("cat:") === 0) {
					state.cats[key.slice(4)] = false;
				} else if (key.indexOf("size:") === 0) {
					state.sizes[key.slice(5)] = false;
				} else if (key.indexOf("mat:") === 0) {
					state.mats[key.slice(4)] = false;
				}
				bump();
				return;
			}
			if (e.target.closest("[data-ol-qv-close]") || e.target.closest("[data-ol-scrim]")) {
				closeQuick(root);
				return;
			}
			var qtyBtn = e.target.closest("[data-ol-qv-qty]");
			if (qtyBtn) {
				var qv = qs(root, "[data-ol-qv]");
				var item = byId(parseInt(qv.getAttribute("data-id"), 10));
				var qty = parseInt(qv.getAttribute("data-qty"), 10) || 1;
				qty += parseInt(qtyBtn.getAttribute("data-ol-qv-qty"), 10);
				if (qty < 1) {
					qty = 1;
				}
				if (item && item.stock && qty > item.stock) {
					qty = item.stock;
				}
				qv.setAttribute("data-qty", String(qty));
				qs(qv, "[data-ol-qv-qty-n]").textContent = String(qty);
				return;
			}
			var sizePick = e.target.closest("[data-ol-qv-size]");
			if (sizePick) {
				var qv2 = qs(root, "[data-ol-qv]");
				qv2.setAttribute("data-size", sizePick.getAttribute("data-ol-qv-size"));
				qsa(qv2, "[data-ol-qv-size]").forEach(function (b) {
					b.classList.toggle("is-on", b === sizePick);
				});
				return;
			}
			if (e.target.closest("[data-ol-qv-wish]")) {
				var qv3 = qs(root, "[data-ol-qv]");
				var wish = byId(parseInt(qv3.getAttribute("data-id"), 10));
				if (wish) {
					toast(root, wish.title + " saved to a list (demo)");
				}
				return;
			}
			if (e.target.closest("[data-ol-qv-cart]")) {
				var qv4 = qs(root, "[data-ol-qv]");
				var cartItem = byId(parseInt(qv4.getAttribute("data-id"), 10));
				if (cartItem && cartItem.stock) {
					var sizeLabel = LABELS[qv4.getAttribute("data-size")] || "";
					var qtyN = qv4.getAttribute("data-qty") || "1";
					toast(root, qtyN + " x " + cartItem.title + (sizeLabel ? ", " + sizeLabel : "") + " added");
					closeQuick(root);
				}
				return;
			}
			if (e.target.closest("[data-ol-qv]")) {
				return;
			}
			var card = e.target.closest("[data-ol-id]");
			if (card) {
				openQuick(root, byId(parseInt(card.getAttribute("data-ol-id"), 10)));
			}
		});

		root.addEventListener("change", function (e) {
			if (e.target.matches("[data-ol-sort]")) {
				state.sort = e.target.value;
				bump();
			}
		});

		root.addEventListener("input", function (e) {
			if (e.target.matches("[data-ol-q]")) {
				state.q = e.target.value;
				bump();
			}
			if (e.target.matches("[data-ol-max]")) {
				state.max = parseInt(e.target.value, 10) || 220;
				qs(root, "[data-ol-max-label]").textContent = money(state.max);
				bump();
			}
		});

		document.addEventListener("keydown", function (e) {
			if (e.key !== "Escape") {
				return;
			}
			var demo = qs(root, ".ol-demo");
			if (demo && demo.classList.contains("is-filters-open")) {
				setFiltersOpen(false);
				return;
			}
			closeQuick(root);
		});

		render();
	}

	function initLayouts(root) {
		if (root.getAttribute("data-ol-ready") === "1") {
			return;
		}
		root.setAttribute("data-ol-ready", "1");
		var state = { colors: {}, cats: {}, sizes: {}, mats: {}, max: 220, q: "", sort: "featured" };
		var shop = qs(root, ".ol-shop");
		var copy = qs(root, "[data-ol-layout-copy]");

		function render() {
			var grid = qs(root, "[data-ol-play-grid]");
			if (!grid) {
				return;
			}
			var list = filterList(state).slice(0, 6);
			grid.innerHTML = list.map(function (p) {
				return cardHTML(p);
			}).join("");
			qsa(root, "[data-ol-play-cat]").forEach(function (b) {
				var key = b.getAttribute("data-ol-play-cat");
				b.classList.toggle("is-on", !!state.cats[key]);
				var n = qs(b, "[data-ol-n]");
				if (n) {
					n.textContent = String(countFacet(state, "cats", key));
				}
			});
			qsa(root, "[data-ol-play-color]").forEach(function (b) {
				var key = b.getAttribute("data-ol-play-color");
				b.classList.toggle("is-on", !!state.colors[key]);
				var n = qs(b, "[data-ol-n]");
				if (n) {
					n.textContent = String(countFacet(state, "colors", key));
				}
			});
		}

		root.addEventListener("click", function (e) {
			var sw = e.target.closest("[data-ol-switch]");
			if (sw && shop) {
				qsa(root, "[data-ol-switch]").forEach(function (b) {
					b.classList.toggle("is-on", b === sw);
				});
				var style = sw.getAttribute("data-ol-switch");
				shop.setAttribute("data-style", style);
				if (copy) {
					copy.textContent = LAYOUT_COPY[style] || "";
				}
				return;
			}
			if (e.target.closest("[data-ol-shop-open]") && shop) {
				shop.classList.toggle("is-open");
				return;
			}
			if (e.target.closest("[data-ol-play-clear]")) {
				state.colors = {};
				state.cats = {};
				render();
				return;
			}
			var cat = e.target.closest("[data-ol-play-cat]");
			if (cat) {
				var ck = cat.getAttribute("data-ol-play-cat");
				state.cats[ck] = !state.cats[ck];
				render();
				return;
			}
			var col = e.target.closest("[data-ol-play-color]");
			if (col) {
				var ck2 = col.getAttribute("data-ol-play-color");
				state.colors[ck2] = !state.colors[ck2];
				render();
				return;
			}
			var playCard = e.target.closest("[data-ol-id]");
			if (playCard) {
				openLiveQuick(parseInt(playCard.getAttribute("data-ol-id"), 10));
			}
		});
		if (copy) {
			copy.textContent = LAYOUT_COPY.sidebar;
		}
		render();
	}

	function initCards(root) {
		if (root.getAttribute("data-ol-ready") === "1") {
			return;
		}
		root.setAttribute("data-ol-ready", "1");
		var grid = qs(root, "[data-ol-card-grid]");
		var copy = qs(root, "[data-ol-card-copy]");
		if (grid) {
			grid.innerHTML = [CATALOG[0], CATALOG[4], CATALOG[10]].map(function (p) {
				return cardHTML(p);
			}).join("");
		}
		if (copy) {
			copy.textContent = CARD_COPY.classic;
		}
		root.addEventListener("click", function (e) {
			var btn = e.target.closest("[data-ol-switch]");
			if (!btn) {
				return;
			}
			qsa(root, "[data-ol-switch]").forEach(function (b) {
				b.classList.toggle("is-on", b === btn);
			});
			var style = btn.getAttribute("data-ol-switch");
			if (grid) {
				grid.setAttribute("data-style", style);
			}
			if (copy) {
				copy.textContent = CARD_COPY[style] || "";
			}
		});
		root.addEventListener("click", function (e) {
			if (e.target.closest("[data-ol-switch]")) {
				return;
			}
			var card = e.target.closest("[data-ol-id]");
			if (card) {
				openLiveQuick(parseInt(card.getAttribute("data-ol-id"), 10));
			}
		});
	}

	function initBuilder(root) {
		if (root.getAttribute("data-ol-ready") === "1") {
			return;
		}
		root.setAttribute("data-ol-ready", "1");
		var list = qs(root, "[data-ol-parts]");
		if (!list) {
			return;
		}

		function apply() {
			var order = qsa(list, "[data-part]").map(function (el) {
				return el.getAttribute("data-part");
			});
			var preview = qs(root, "[data-ol-preview]");
			if (!preview) {
				return;
			}
			order.forEach(function (key) {
				var row = qs(preview, '[data-ol-preview-row="' + key + '"]');
				if (row) {
					preview.appendChild(row);
				}
			});
		}

		var drag = null;
		list.addEventListener("dragstart", function (e) {
			drag = e.target.closest("[data-part]");
			if (drag) {
				drag.classList.add("is-dragging");
				e.dataTransfer.effectAllowed = "move";
			}
		});
		list.addEventListener("dragend", function () {
			if (drag) {
				drag.classList.remove("is-dragging");
			}
			qsa(list, "[data-part]").forEach(function (el) {
				el.classList.remove("is-over");
			});
			drag = null;
			apply();
		});
		list.addEventListener("dragover", function (e) {
			e.preventDefault();
			var over = e.target.closest("[data-part]");
			if (!over || over === drag) {
				return;
			}
			qsa(list, "[data-part]").forEach(function (el) {
				el.classList.toggle("is-over", el === over);
			});
			var rect = over.getBoundingClientRect();
			var before = e.clientY < rect.top + rect.height / 2;
			list.insertBefore(drag, before ? over : over.nextSibling);
		});
		apply();
	}

	function initMerch(root) {
		if (root.getAttribute("data-ol-ready") === "1") {
			return;
		}
		root.setAttribute("data-ol-ready", "1");
		var flags = { pin: true, oos: true, badges: true, urgent: true };
		var ids = [1, 5, 2, 7, 16, 12];

		function render() {
			qsa(root, "[data-ol-merch]").forEach(function (b) {
				b.classList.toggle("is-on", !!flags[b.getAttribute("data-ol-merch")]);
			});
			var list = ids.map(byId).filter(Boolean);
			list.sort(function (a, b) {
				if (flags.pin && a.pin !== b.pin) {
					return b.pin - a.pin;
				}
				if (flags.oos && (a.stock === 0) !== (b.stock === 0)) {
					return (a.stock === 0) - (b.stock === 0);
				}
				return a.id - b.id;
			});
			var grid = qs(root, "[data-ol-merch-grid]");
			if (grid) {
				grid.innerHTML = list.map(function (p) {
					return cardHTML(p, "", { badges: flags.badges, urgent: flags.urgent });
				}).join("");
			}
			var note = qs(root, "[data-ol-merch-note]");
			if (note) {
				var bits = [];
				if (flags.pin) {
					bits.push("pinned SKUs first");
				}
				if (flags.oos) {
					bits.push("sold out last");
				}
				if (flags.badges) {
					bits.push("sale / pin badges");
				}
				if (flags.urgent) {
					bits.push("low-stock labels");
				}
				note.textContent = bits.length ? "Now showing: " + bits.join(", ") + "." : "No merchandising rules. Raw catalog order.";
			}
		}

		root.addEventListener("click", function (e) {
			var btn = e.target.closest("[data-ol-merch]");
			if (btn) {
				var key = btn.getAttribute("data-ol-merch");
				flags[key] = !flags[key];
				render();
				return;
			}
			var card = e.target.closest("[data-ol-id]");
			if (card) {
				openLiveQuick(parseInt(card.getAttribute("data-ol-id"), 10));
			}
		});
		render();
	}

	function initSeo(root) {
		if (root.getAttribute("data-ol-ready") === "1") {
			return;
		}
		root.setAttribute("data-ol-ready", "1");
		var picked = {};
		var noindex = false;

		function pretty() {
			return Object.keys(picked).map(function (k) {
				return picked[k] ? LABELS[picked[k]] || picked[k] : "";
			}).filter(Boolean).join(" + ");
		}

		function paint() {
			var parts = [];
			Object.keys(picked).forEach(function (k) {
				if (picked[k]) {
					parts.push(k + "/" + picked[k]);
				}
			});
			var url = qs(root, "[data-ol-url]");
			var title = qs(root, "[data-ol-title]");
			var h1 = qs(root, "[data-ol-h1]");
			var desc = qs(root, "[data-ol-desc]");
			var robots = qs(root, "[data-ol-robots]");
			var canon = qs(root, "[data-ol-canon]");
			var mix = pretty();
			if (url) {
				url.innerHTML = parts.length ? "/shop/<em>ol</em>/" + parts.join("/") + "/" : "/shop/";
			}
			if (title) {
				title.textContent = mix ? mix + " | Harbor Goods" : "Shop | Harbor Goods";
			}
			if (h1) {
				h1.textContent = mix ? "Shop " + mix : "Shop";
			}
			if (desc) {
				desc.textContent = mix
					? "Filter Harbor Goods by " + mix + ". Live facets, in stock first, free packing on orders over $100."
					: "The Harbor Goods catalog. Filter by color, category, and material.";
			}
			if (robots) {
				robots.textContent = noindex ? "noindex, follow" : "index, follow";
			}
			if (canon) {
				canon.textContent = parts.length ? "https://harbor.example/shop/ol/" + parts.join("/") + "/" : "https://harbor.example/shop/";
			}
		}

		root.addEventListener("click", function (e) {
			var btn = e.target.closest("[data-ol-seo]");
			if (btn) {
				var facet = btn.getAttribute("data-ol-seo");
				var value = btn.getAttribute("data-ol-value");
				picked[facet] = picked[facet] === value ? "" : value;
				qsa(root, '[data-ol-seo="' + facet + '"]').forEach(function (b) {
					b.classList.toggle("is-on", b.getAttribute("data-ol-value") === picked[facet]);
				});
				paint();
				return;
			}
		});
		root.addEventListener("change", function (e) {
			if (e.target.matches("[data-ol-noindex]")) {
				noindex = e.target.checked;
				paint();
			}
		});
		paint();
	}

	function initFaq(root) {
		if (root.getAttribute("data-ol-ready") === "1") {
			return;
		}
		root.setAttribute("data-ol-ready", "1");
		function syncFaqAria() {
			qsa(root, ".ol-faq").forEach(function (el) {
				var b = qs(el, "[data-ol-faq]");
				if (b) {
					b.setAttribute("aria-expanded", el.classList.contains("is-open") ? "true" : "false");
				}
			});
		}
		root.addEventListener("click", function (e) {
			var btn = e.target.closest("[data-ol-faq]");
			if (!btn) {
				return;
			}
			var item = btn.closest(".ol-faq");
			var open = item.classList.contains("is-open");
			qsa(root, ".ol-faq").forEach(function (el) {
				el.classList.remove("is-open");
			});
			if (!open) {
				item.classList.add("is-open");
			}
			syncFaqAria();
		});
		syncFaqAria();
	}

	function boot() {
		qsa(document, '[data-ol-landing="nav"]').forEach(initNav);
		qsa(document, '[data-ol-landing="hero"]').forEach(initHero);
		qsa(document, '[data-ol-landing="live"]').forEach(initDemo);
		qsa(document, '[data-ol-landing="layouts"]').forEach(initLayouts);
		qsa(document, '[data-ol-landing="cards"]').forEach(initCards);
		qsa(document, '[data-ol-landing="builder"]').forEach(initBuilder);
		qsa(document, '[data-ol-landing="merch"]').forEach(initMerch);
		qsa(document, '[data-ol-landing="seo"]').forEach(initSeo);
		qsa(document, '[data-ol-landing="faq"]').forEach(initFaq);
	}

	ready(boot);
	window.olLandingBoot = boot;
})();
