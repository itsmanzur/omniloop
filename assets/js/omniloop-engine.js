/**
 * Shared OmniLoop frontend engine.
 * Instant cache hits; no spinner. DOM built with createElement (no innerHTML).
 */
(function () {
	"use strict";

	var cache = {};

	function qs(root, sel) {
		return root.querySelector(sel);
	}

	function qsa(root, sel) {
		return Array.prototype.slice.call(root.querySelectorAll(sel));
	}

	function readConfig(root) {
		var node = qs(root, "script.omniloop-config");
		if (!node) return null;
		try {
			var data = JSON.parse(node.textContent || "{}");
			if (!data || typeof data.restUrl !== "string") return null;
			return data;
		} catch (e) {
			return null;
		}
	}

	function selectedFacets(root) {
		var facets = {};
		qsa(root, "input[data-facet]:checked").forEach(function (input) {
			var key = input.getAttribute("data-facet") || "";
			var value = input.getAttribute("data-value") || "";
			if (!key || !value) return;
			if (!facets[key]) facets[key] = [];
			facets[key].push(value);
		});
		var price = priceRangeOf(root);
		if (price) {
			facets.price_range = price;
		}
		return facets;
	}

	function payload(root, config, page) {
		var searchEl = qs(root, ".omniloop-search__input");
		var sortEl = qs(root, ".omniloop-sort__select");
		return {
			post_type: config.postType || "post",
			per_page: Number(config.perPage) || 12,
			page: usesPagedResults(root) ? (page || 1) : 1,
			search: searchEl ? String(searchEl.value || "").slice(0, 120) : "",
			sort_by: sortEl ? sortEl.value : "date",
			facets: selectedFacets(root),
			facet_logic: config.facetLogic || "smart",
			sort_meta_key: config.sortMetaKey || "",
			merchandising: config.merchandising !== false,
			extra_pin_ids: config.extraPinIds || ""
		};
	}

	function cacheKey(body) {
		return JSON.stringify(body);
	}

	function setCount(root, total) {
		var el = qs(root, ".omniloop-count");
		if (!el) return;
		el.textContent = total === 1 ? "1 result" : String(total) + " results";
	}

	function saleBadge(item) {
		if (!item || !item.onSale) return null;
		var badge = document.createElement("span");
		badge.className = "omniloop-card__badge";
		var pct = Number(item.salePercent) || 0;
		badge.textContent = pct > 0 ? ("-" + pct + "%") : "Sale";
		return badge;
	}

	function cardBadges(item, root) {
		var cfg = readConfig(root) || {};
		var wrap = document.createElement("span");
		wrap.className = "omniloop-card__badges";
		var count = 0;
		if (cfg.promotedBadge !== false && item && item.promotedBadge) {
			var promo = document.createElement("span");
			promo.className = "omniloop-card__badge omniloop-card__badge--promo";
			promo.textContent = String(item.promotedBadge);
			wrap.appendChild(promo);
			count += 1;
		}
		var sale = saleBadge(item);
		if (sale) {
			wrap.appendChild(sale);
			count += 1;
		}
		if (cfg.stockUrgency !== false && item && item.stockUrgency) {
			var urgency = document.createElement("span");
			urgency.className = "omniloop-card__badge omniloop-card__badge--urgency";
			urgency.textContent = String(item.stockUrgency);
			wrap.appendChild(urgency);
			count += 1;
		}
		return count ? wrap : null;
	}

	function editorCardStyle(root) {
		var cfg = readConfig(root);
		if (cfg && typeof cfg.cardStyle === "string" && cfg.cardStyle) {
			return cfg.cardStyle;
		}
		if (root.classList.contains("omniloop-app--card-overlay")) return "overlay";
		if (root.classList.contains("omniloop-app--card-compact")) return "compact";
		if (root.classList.contains("omniloop-app--card-carousel")) return "carousel";
		if (root.classList.contains("omniloop-app--card-masonry")) return "masonry";
		if (root.classList.contains("omniloop-app--card-list") && !(cfg && cfg.cardStyle)) return "list";
		return "classic";
	}

	function gridStyleOf(root) {
		var style = editorCardStyle(root);
		return style === "list" ? "classic" : style;
	}

	function defaultViewOf(root) {
		return editorCardStyle(root) === "list" ? "list" : "grid";
	}

	function viewOf(root) {
		return root.classList.contains("omniloop-app--card-list") ? "list" : "grid";
	}

	function cardStyleOf(root) {
		return viewOf(root) === "list" ? "list" : gridStyleOf(root);
	}

	var CARD_SKINS = ["classic", "overlay", "compact", "list", "carousel", "masonry"];

	function applyView(root, view) {
		view = view === "list" ? "list" : "grid";
		CARD_SKINS.forEach(function (skin) {
			root.classList.remove("omniloop-app--card-" + skin);
		});
		root.classList.add(view === "list" ? "omniloop-app--card-list" : "omniloop-app--card-" + gridStyleOf(root));
		qsa(root, ".omniloop-view__btn").forEach(function (btn) {
			var on = (btn.getAttribute("data-view") || "") === view;
			btn.classList.toggle("is-active", on);
			btn.setAttribute("aria-pressed", on ? "true" : "false");
		});
		syncCarousel(root);
	}

	function currentPage(root) {
		var active = qs(root, ".omniloop-page.is-active");
		return active ? Number(active.getAttribute("data-page") || 1) || 1 : 1;
	}

	function appendMedia(link, item, root) {
		if (item.thumbnail) {
			var media = document.createElement("div");
			media.className = "omniloop-card__media";
			var badge = cardBadges(item, root);
			if (badge) media.appendChild(badge);
			var img = document.createElement("img");
			img.src = item.thumbnail;
			img.alt = item.title || "";
			img.loading = "lazy";
			img.width = 600;
			img.height = 450;
			media.appendChild(img);
			link.appendChild(media);
			return;
		}
		var mediaEmpty = document.createElement("div");
		mediaEmpty.className = "omniloop-card__media omniloop-card__media--empty";
		var emptyBadge = cardBadges(item, root);
		if (emptyBadge) mediaEmpty.appendChild(emptyBadge);
		var letter = document.createElement("span");
		letter.className = "omniloop-card__fallback";
		letter.textContent = (item.title || "•").charAt(0).toUpperCase();
		mediaEmpty.appendChild(letter);
		link.appendChild(mediaEmpty);
	}

	function appendPrice(body, item) {
		if (!item.price) return;
		var price = document.createElement("p");
		price.className = "omniloop-card__price";
		if (item.priceRegular) {
			var was = document.createElement("s");
			was.className = "omniloop-card__price-regular";
			was.textContent = item.priceRegular;
			price.appendChild(was);
			price.appendChild(document.createTextNode(" "));
		}
		price.appendChild(document.createTextNode(item.price));
		body.appendChild(price);
	}

	function renderEmpty(root, grid) {
		var cfg = readConfig(root) || {};
		var empty = cfg.empty && typeof cfg.empty === "object" ? cfg.empty : {};
		var style = empty.style || "box";
		var title = typeof empty.title === "string" && empty.title ? empty.title : "No results found";
		var message = typeof empty.message === "string" ? empty.message : "Try clearing filters or searching for something else.";
		var resetLabel = typeof empty.resetLabel === "string" && empty.resetLabel ? empty.resetLabel : "Reset filters";
		var showReset = empty.showReset !== false;
		var wrap = document.createElement("div");
		wrap.className = "omniloop-empty omniloop-empty--" + style;
		wrap.setAttribute("role", "status");

		var icon = emptyIcon(style);
		if (icon) wrap.appendChild(icon);

		var titleEl = document.createElement("p");
		titleEl.className = "omniloop-empty__title";
		titleEl.textContent = title;
		wrap.appendChild(titleEl);

		if (message) {
			var msgEl = document.createElement("p");
			msgEl.className = "omniloop-empty__message";
			msgEl.textContent = message;
			wrap.appendChild(msgEl);
		}

		if (showReset && activeFilterCount(root)) {
			var emptyReset = document.createElement("button");
			emptyReset.type = "button";
			emptyReset.className = "omniloop-empty__reset";
			emptyReset.textContent = resetLabel;
			wrap.appendChild(emptyReset);
		}

		grid.appendChild(wrap);
	}

	function emptyIcon(style) {
		if (style === "minimal") return null;
		var paths = {
			box: '<rect x="4" y="7" width="16" height="13" rx="2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4 11h16M12 7v13M8.5 7l1-3h5l1 3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
			search: '<circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M16 16l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
			bag: '<path d="M6 9h12l-1 11H7L6 9z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 9V7.5a3 3 0 0 1 6 0V9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
		};
		var span = document.createElement("span");
		span.className = "omniloop-empty__icon";
		span.setAttribute("aria-hidden", "true");
		span.innerHTML = '<svg width="40" height="40" viewBox="0 0 24 24" focusable="false">' + (paths[style] || paths.box) + "</svg>";
		return span;
	}

	function renderGrid(root, posts) {
		var grid = qs(root, ".omniloop-grid");
		if (!grid) return;
		while (grid.firstChild) grid.removeChild(grid.firstChild);

		if (!posts || !posts.length) {
			renderEmpty(root, grid);
			syncCarousel(root);
			return;
		}

		posts.forEach(function (item) {
			grid.appendChild(buildCard(item, root));
		});
		syncCarousel(root);
	}

	function showAddToCart(root) {
		var cfg = readConfig(root) || {};
		return !!cfg.showAddToCart;
	}

	function showQuickView(root) {
		var cfg = readConfig(root) || {};
		return cfg.showQuickView !== false;
	}

	function filterSyncEnabled(root) {
		var cfg = readConfig(root) || {};
		return cfg.filterSync !== false;
	}

	function syncGroupOf(root) {
		var cfg = readConfig(root) || {};
		var group = String(cfg.syncGroup || "default").trim();
		return group || "default";
	}

	function applyBodyToDom(root, body) {
		if (!body || typeof body !== "object") {
			return;
		}

		var search = qs(root, ".omniloop-search__input");
		if (search) {
			search.value = body.search || "";
		}

		var sort = qs(root, ".omniloop-sort__select");
		if (sort) {
			sort.value = body.sort_by || "date";
		}

		qsa(root, "input[data-facet]").forEach(function (input) {
			input.checked = false;
		});

		var facets = body.facets || {};
		Object.keys(facets).forEach(function (key) {
			if (key === "price_range") {
				return;
			}
			var values = facets[key];
			if (!Array.isArray(values)) {
				return;
			}
			values.forEach(function (val) {
				qsa(root, 'input[data-facet="' + key + '"][data-value="' + val + '"]').forEach(function (input) {
					input.checked = true;
				});
			});
		});

		if (facets.price_range && facets.price_range.length >= 2) {
			setPriceInputs(root, facets.price_range[0], facets.price_range[1]);
		} else {
			setPriceInputs(root, null, null);
		}
		syncPriceSlider(root);
	}

	function presetBody(root, config) {
		if (!config.filterPreset || typeof config.filterPreset !== "object") {
			return null;
		}
		var preset = config.filterPreset;
		return {
			post_type: config.postType || "post",
			per_page: Number(config.perPage) || 12,
			page: 1,
			search: preset.search || "",
			sort_by: preset.sort_by || "date",
			facets: preset.facets || {},
			facet_logic: config.facetLogic || "smart",
			sort_meta_key: config.sortMetaKey || ""
		};
	}

	function broadcastSync(root, body) {
		if (!filterSyncEnabled(root)) {
			return;
		}
		if (typeof document === "undefined" || !document.dispatchEvent) {
			return;
		}
		document.dispatchEvent(
			new CustomEvent("omniloop:sync", {
				detail: {
					group: syncGroupOf(root),
					source: root.id || "",
					postType: body.post_type,
					body: body
				}
			})
		);
	}

	var syncListenerBound = false;
	function bindGlobalSync() {
		if (syncListenerBound) {
			return;
		}
		syncListenerBound = true;
		document.addEventListener("omniloop:sync", function (e) {
			var detail = e.detail || {};
			qsa(document, ".omniloop-app").forEach(function (root) {
				if ((root.id || "") === (detail.source || "")) {
					return;
				}
				if (!filterSyncEnabled(root)) {
					return;
				}
				if (syncGroupOf(root) !== (detail.group || "default")) {
					return;
				}
				var cfg = readConfig(root);
				if (!cfg || cfg.postType !== detail.postType) {
					return;
				}
				applyBodyToDom(root, detail.body);
				query(root, cfg, detail.body.page || 1, {
					skipSync: true,
					skipUrl: true,
					body: detail.body
				});
			});
		});
	}

	function paginationStyle(root) {
		var cfg = readConfig(root) || {};
		if (cfg.showPagination === false) {
			return "none";
		}
		var style = String(cfg.paginationStyle || "pages").toLowerCase();
		if (style === "load_more" || style === "load-more") {
			return "load_more";
		}
		if (style === "infinite" || style === "infinite_scroll") {
			return "infinite";
		}
		if (style === "none" || style === "off") {
			return "none";
		}
		return "pages";
	}

	function usesPagedResults(root) {
		var style = paginationStyle(root);
		return style === "pages" || style === "load_more" || style === "infinite";
	}

	function showPagination(root) {
		return paginationStyle(root) === "pages";
	}

	function pagingLabel(root, key, fallback) {
		var cfg = readConfig(root) || {};
		if (cfg[key] && typeof cfg[key] === "string") {
			return cfg[key];
		}
		return fallback;
	}

	function cardPartsOf(root) {
		var cfg = readConfig(root) || {};
		return Array.isArray(cfg.cardParts) ? cfg.cardParts : [];
	}

	function appendRating(body, item) {
		var rating = Number(item.rating) || 0;
		if (rating <= 0) return;
		var stars = Math.max(1, Math.min(5, Math.round(rating)));
		var p = document.createElement("p");
		p.className = "omniloop-card__rating";
		p.setAttribute("aria-label", "Rated " + rating.toFixed(1) + " out of 5");
		var mark = document.createElement("span");
		mark.setAttribute("aria-hidden", "true");
		mark.textContent = "★★★★★".slice(0, stars) + "☆☆☆☆☆".slice(0, 5 - stars);
		p.appendChild(mark);
		body.appendChild(p);
	}

	function buildCard(item, root) {
		var parts = cardPartsOf(root);
		if (parts.length) {
			return buildCustomCard(item, root, parts);
		}

		var article = document.createElement("article");
		article.className = "omniloop-card";
		article.setAttribute("data-id", String(item.id || ""));

		var link = document.createElement("a");
		link.className = "omniloop-card__link";
		link.href = typeof item.permalink === "string" ? item.permalink : "#";

		appendMedia(link, item, root);

		var title = document.createElement("h3");
		title.className = "omniloop-card__title";
		title.textContent = item.title || "";

		var body = document.createElement("div");
		body.className = "omniloop-card__body";
		body.appendChild(title);

		if (item.excerpt) {
			var excerpt = document.createElement("p");
			excerpt.className = "omniloop-card__excerpt";
			excerpt.textContent = String(item.excerpt);
			body.appendChild(excerpt);
		}

		appendPrice(body, item);
		link.appendChild(body);
		article.appendChild(link);
		appendCardActions(article, item, root);
		return article;
	}

	function buildCustomCard(item, root, parts) {
		var article = document.createElement("article");
		article.className = "omniloop-card omniloop-card--custom";
		article.setAttribute("data-id", String(item.id || ""));

		var link = document.createElement("a");
		link.className = "omniloop-card__link";
		link.href = typeof item.permalink === "string" ? item.permalink : "#";

		var body = document.createElement("div");
		body.className = "omniloop-card__body";
		var actionParts = [];
		var hasBody = false;

		parts.forEach(function (part) {
			if (part === "image") {
				appendMedia(link, item, root);
				return;
			}
			if (part === "addToCart" || part === "quickView") {
				actionParts.push(part);
				return;
			}
			if (part === "title") {
				var title = document.createElement("h3");
				title.className = "omniloop-card__title";
				title.textContent = item.title || "";
				body.appendChild(title);
				hasBody = true;
				return;
			}
			if (part === "excerpt" && item.excerpt) {
				var excerpt = document.createElement("p");
				excerpt.className = "omniloop-card__excerpt";
				excerpt.textContent = String(item.excerpt);
				body.appendChild(excerpt);
				hasBody = true;
				return;
			}
			if (part === "price") {
				var before = body.childNodes.length;
				appendPrice(body, item);
				if (body.childNodes.length > before) hasBody = true;
				return;
			}
			if (part === "rating") {
				var beforeR = body.childNodes.length;
				appendRating(body, item);
				if (body.childNodes.length > beforeR) hasBody = true;
			}
		});

		if (hasBody) {
			link.appendChild(body);
		}
		article.appendChild(link);
		appendCardActions(article, item, root, actionParts);
		return article;
	}

	function appendGrid(root, posts) {
		var grid = qs(root, ".omniloop-grid");
		if (!grid || !posts || !posts.length) {
			return;
		}
		posts.forEach(function (item) {
			grid.appendChild(buildCard(item, root));
		});
		syncCarousel(root);
	}

	function appendCardActions(article, item, root, onlyParts) {
		var qvOn = showQuickView(root);
		var atcOn = showAddToCart(root) && item && item.addToCart && typeof item.addToCart === "object";
		if (Array.isArray(onlyParts) && onlyParts.length) {
			qvOn = onlyParts.indexOf("quickView") !== -1;
			atcOn = onlyParts.indexOf("addToCart") !== -1 && item && item.addToCart && typeof item.addToCart === "object";
		}
		if (!qvOn && !atcOn) return;

		var wrap = document.createElement("div");
		wrap.className = "omniloop-card__actions";

		if (qvOn) {
			var qv = document.createElement("button");
			qv.type = "button";
			qv.className = "omniloop-card__qv";
			qv.setAttribute("data-omniloop-qv", "1");
			qv.setAttribute("aria-haspopup", "dialog");
			qv.setAttribute("aria-label", "Quick view");
			qv.textContent = "Quick view";
			wrap.appendChild(qv);
		}

		if (atcOn) {
			var meta = item.addToCart;
			var id = Number(meta.id) || 0;
			var url = typeof meta.url === "string" ? meta.url : "";
			var label = typeof meta.label === "string" ? meta.label : "Add to cart";
			var type = typeof meta.type === "string" && meta.type ? meta.type : "simple";
			var sku = typeof meta.sku === "string" ? meta.sku : "";
			if (id && url) {
				var btn = document.createElement("a");
				btn.href = url;
				btn.className = "omniloop-card__atc button product_type_" + type + " add_to_cart_button";
				if (meta.ajax) btn.className += " ajax_add_to_cart";
				btn.setAttribute("data-quantity", "1");
				btn.setAttribute("data-product_id", String(id));
				btn.setAttribute("data-product_sku", sku);
				btn.setAttribute("aria-label", "Add to cart: " + label);
				btn.rel = "nofollow";
				btn.textContent = label;
				wrap.appendChild(btn);
			}
		}

		if (wrap.childNodes.length) article.appendChild(wrap);
	}

	function ensureQuickView(root) {
		var shell = qs(root, ".omniloop-qv");
		if (shell) return shell;
		shell = document.createElement("div");
		shell.className = "omniloop-qv";
		shell.hidden = true;
		shell.innerHTML =
			'<div class="omniloop-qv__backdrop" data-omniloop-qv-close="1"></div>' +
			'<div class="omniloop-qv__dialog" role="dialog" aria-modal="true" aria-labelledby="' + root.id + '-qv-title" tabindex="-1">' +
			'<button type="button" class="omniloop-qv__close" data-omniloop-qv-close="1" aria-label="Close">&times;</button>' +
			'<div class="omniloop-qv__media"></div>' +
			'<div class="omniloop-qv__content">' +
			'<h3 class="omniloop-qv__title" id="' + root.id + '-qv-title"></h3>' +
			'<p class="omniloop-qv__price" hidden></p>' +
			'<p class="omniloop-qv__excerpt" hidden></p>' +
			'<div class="omniloop-qv__actions"></div>' +
			"</div></div>";
		root.appendChild(shell);
		return shell;
	}

	function closeQuickView(root) {
		var shell = qs(root, ".omniloop-qv");
		if (!shell || shell.hidden) return;
		shell.hidden = true;
		document.body.classList.remove("omniloop-qv-open");
		var opener = root._olQvOpener;
		root._olQvOpener = null;
		if (opener && opener.focus) opener.focus();
	}

	function openQuickView(root, item, opener) {
		if (!item) return;
		var shell = ensureQuickView(root);
		var media = qs(shell, ".omniloop-qv__media");
		var title = qs(shell, ".omniloop-qv__title");
		var price = qs(shell, ".omniloop-qv__price");
		var excerpt = qs(shell, ".omniloop-qv__excerpt");
		var actions = qs(shell, ".omniloop-qv__actions");
		var dialog = qs(shell, ".omniloop-qv__dialog");
		if (!media || !title || !price || !excerpt || !actions) return;

		while (media.firstChild) media.removeChild(media.firstChild);
		if (item.thumbnail) {
			var img = document.createElement("img");
			img.src = item.thumbnail;
			img.alt = item.title || "";
			media.appendChild(img);
		} else {
			var ph = document.createElement("div");
			ph.className = "omniloop-qv__fallback";
			ph.textContent = (item.title || "•").charAt(0).toUpperCase();
			media.appendChild(ph);
		}

		title.textContent = item.title || "";

		while (price.firstChild) price.removeChild(price.firstChild);
		if (item.price) {
			if (item.priceRegular) {
				var was = document.createElement("s");
				was.className = "omniloop-card__price-regular";
				was.textContent = item.priceRegular;
				price.appendChild(was);
				price.appendChild(document.createTextNode(" "));
			}
			price.appendChild(document.createTextNode(item.price));
			price.hidden = false;
		} else {
			price.hidden = true;
		}

		if (item.excerpt) {
			excerpt.textContent = String(item.excerpt);
			excerpt.hidden = false;
		} else {
			excerpt.textContent = "";
			excerpt.hidden = true;
		}

		while (actions.firstChild) actions.removeChild(actions.firstChild);
		if (showAddToCart(root) && item.addToCart && typeof item.addToCart === "object" && item.addToCart.url) {
			var meta = item.addToCart;
			var atc = document.createElement("a");
			atc.href = meta.url;
			atc.className = "omniloop-card__atc button product_type_" + (meta.type || "simple") + " add_to_cart_button";
			if (meta.ajax) atc.className += " ajax_add_to_cart";
			atc.setAttribute("data-quantity", "1");
			atc.setAttribute("data-product_id", String(meta.id || ""));
			atc.setAttribute("data-product_sku", meta.sku || "");
			atc.rel = "nofollow";
			atc.textContent = meta.label || "Add to cart";
			actions.appendChild(atc);
		}
		if (item.permalink) {
			var more = document.createElement("a");
			more.href = item.permalink;
			more.className = "omniloop-qv__more";
			more.textContent = "View details";
			actions.appendChild(more);
		}

		root._olQvOpener = opener || null;
		shell.hidden = false;
		document.body.classList.add("omniloop-qv-open");
		if (dialog && dialog.focus) dialog.focus();
	}

	function itemFromCard(card) {
		if (!card) return null;
		var link = qs(card, ".omniloop-card__link");
		var title = qs(card, ".omniloop-card__title");
		var price = qs(card, ".omniloop-card__price");
		var excerpt = qs(card, ".omniloop-card__excerpt");
		var img = qs(card, ".omniloop-card__media img");
		var atc = qs(card, ".omniloop-card__atc");
		var regular = price ? qs(price, ".omniloop-card__price-regular") : null;
		var priceText = "";
		if (price) {
			var clone = price.cloneNode(true);
			var reg = qs(clone, ".omniloop-card__price-regular");
			if (reg && reg.parentNode) reg.parentNode.removeChild(reg);
			priceText = String(clone.textContent || "").replace(/\s+/g, " ").trim();
		}
		var item = {
			id: Number(card.getAttribute("data-id") || 0) || 0,
			title: title ? String(title.textContent || "").trim() : "",
			permalink: link ? link.href : "",
			thumbnail: img ? img.src : "",
			price: priceText,
			priceRegular: regular ? String(regular.textContent || "").trim() : "",
			excerpt: excerpt ? String(excerpt.textContent || "").trim() : "",
			addToCart: null
		};
		if (atc) {
			item.addToCart = {
				id: Number(atc.getAttribute("data-product_id") || item.id) || 0,
				url: atc.href,
				label: String(atc.textContent || "").trim(),
				ajax: atc.classList.contains("ajax_add_to_cart"),
				type: (atc.className.match(/product_type_([a-z0-9_-]+)/) || [])[1] || "simple",
				sku: atc.getAttribute("data-product_sku") || ""
			};
		}
		return item;
	}

	function bindQuickView(root) {
		if (!showQuickView(root)) return;
		ensureQuickView(root);
		root.addEventListener("click", function (e) {
			var target = e.target;
			if (!target || !target.closest) return;
			if (target.closest("[data-omniloop-qv-close]")) {
				e.preventDefault();
				closeQuickView(root);
				return;
			}
			var btn = target.closest("[data-omniloop-qv]");
			if (!btn || !root.contains(btn)) return;
			e.preventDefault();
			e.stopPropagation();
			var card = btn.closest(".omniloop-card");
			openQuickView(root, itemFromCard(card), btn);
		});
		document.addEventListener("keydown", function (e) {
			if (e.key === "Escape") closeQuickView(root);
		});
	}

	function carouselOptions(root) {
		var shell = qs(root, ".omniloop-carousel");
		var cfg = readConfig(root) || {};
		var c = cfg.carousel && typeof cfg.carousel === "object" ? cfg.carousel : {};
		function flag(attr, fallback) {
			if (!shell || !shell.hasAttribute(attr)) return fallback;
			var raw = String(shell.getAttribute(attr) || "").trim().toLowerCase();
			if (raw === "1" || raw === "on" || raw === "true" || raw === "yes") return true;
			if (raw === "0" || raw === "off" || raw === "false" || raw === "no") return false;
			return fallback;
		}
		var speed = Number(
			String((shell && shell.getAttribute("data-speed")) || c.speed || 4000).trim()
		);
		if (!isFinite(speed) || speed < 1000) speed = 4000;
		return {
			autoplay: flag("data-autoplay", !!c.autoplay),
			speed: speed,
			pauseHover: flag("data-pause-hover", c.pauseHover !== false),
			loop: flag("data-loop", c.loop !== false),
			arrows: flag("data-arrows", c.arrows !== false),
			dots: flag("data-dots", c.dots !== false)
		};
	}

	function carouselGrid(root) {
		var shell = qs(root, ".omniloop-carousel");
		return shell ? qs(shell, ".omniloop-grid") : qs(root, ".omniloop-grid");
	}

	function carouselEnabled(root) {
		return root.classList.contains("omniloop-app--card-carousel") && !!qs(root, ".omniloop-carousel");
	}

	function carouselStep(root) {
		var grid = carouselGrid(root);
		if (!grid) return 0;
		var card = qs(grid, ".omniloop-card");
		if (!card) return Math.max(grid.clientWidth, 1);
		var styles = window.getComputedStyle(grid);
		var gap = parseFloat(styles.columnGap || styles.gap || "0") || 0;
		return card.getBoundingClientRect().width + gap;
	}

	function syncCarousel(root) {
		var shell = qs(root, ".omniloop-carousel");
		if (!shell) return;
		var grid = carouselGrid(root);
		var prev = qs(shell, ".omniloop-carousel__btn--prev");
		var next = qs(shell, ".omniloop-carousel__btn--next");
		var dots = qs(shell, ".omniloop-carousel__dots");
		var opts = carouselOptions(root);
		if (prev) prev.hidden = !opts.arrows;
		if (next) next.hidden = !opts.arrows;
		if (!carouselEnabled(root) || !grid) {
			if (prev) prev.disabled = true;
			if (next) next.disabled = true;
			if (dots) {
				while (dots.firstChild) dots.removeChild(dots.firstChild);
				dots.hidden = true;
			}
			return;
		}

		var maxScroll = Math.max(0, grid.scrollWidth - grid.clientWidth);
		var left = grid.scrollLeft;
		if (prev) prev.disabled = !opts.loop && left <= 2;
		if (next) next.disabled = !opts.loop && left >= maxScroll - 2;

		if (!dots) return;
		if (!opts.dots) {
			while (dots.firstChild) dots.removeChild(dots.firstChild);
			dots.hidden = true;
			return;
		}
		var step = carouselStep(root);
		var pages = step > 0 ? Math.max(1, Math.ceil((maxScroll + step) / step)) : 1;
		if (pages <= 1 || maxScroll <= 2) {
			while (dots.firstChild) dots.removeChild(dots.firstChild);
			dots.hidden = true;
			return;
		}
		dots.hidden = false;
		var active = Math.round(left / Math.max(step, 1));
		if (active < 0) active = 0;
		if (active > pages - 1) active = pages - 1;
		if (dots.childNodes.length !== pages) {
			while (dots.firstChild) dots.removeChild(dots.firstChild);
			for (var i = 0; i < pages; i++) {
				(function (index) {
					var dot = document.createElement("button");
					dot.type = "button";
					dot.className = "omniloop-carousel__dot";
					dot.setAttribute("aria-label", "Slide " + String(index + 1));
					dot.addEventListener("click", function () {
						var live = carouselGrid(root);
						if (!live) return;
						live.scrollTo({ left: index * carouselStep(root), behavior: "smooth" });
					});
					dots.appendChild(dot);
				})(i);
			}
		}
		qsa(dots, ".omniloop-carousel__dot").forEach(function (dot, index) {
			dot.classList.toggle("is-active", index === active);
		});
	}

	function bindCarousel(root) {
		var shell = qs(root, ".omniloop-carousel");
		if (!shell || shell.getAttribute("data-ol-carousel") === "1") return;
		shell.setAttribute("data-ol-carousel", "1");
		var prev = qs(shell, ".omniloop-carousel__btn--prev");
		var next = qs(shell, ".omniloop-carousel__btn--next");

		var timer = 0;
		var paused = false;

		function stopAutoplay() {
			if (timer) {
				window.clearInterval(timer);
				timer = 0;
			}
		}

		function startAutoplay() {
			stopAutoplay();
			var opts = carouselOptions(root);
			if (!opts.autoplay || !carouselEnabled(root)) return;
			timer = window.setInterval(function () {
				if (paused || document.hidden) return;
				move(1);
			}, opts.speed);
		}

		function move(dir) {
			if (!carouselEnabled(root)) return;
			var grid = carouselGrid(root);
			if (!grid) return;
			var opts = carouselOptions(root);
			var step = Math.max(carouselStep(root), 1);
			var maxScroll = Math.max(0, grid.scrollWidth - grid.clientWidth);
			if (maxScroll <= 2) return;
			var left = grid.scrollLeft;
			var atEnd = left >= maxScroll - 2;
			var atStart = left <= 2;
			var target = left + dir * step;
			if (opts.loop) {
				if (dir > 0 && atEnd) target = 0;
				else if (dir < 0 && atStart) target = maxScroll;
			} else {
				if (target < 0) target = 0;
				if (target > maxScroll) target = maxScroll;
				if (Math.abs(target - left) < 1) return;
			}
			if (typeof grid.scrollTo === "function") {
				grid.scrollTo({ left: target, behavior: "smooth" });
			} else {
				grid.scrollLeft = target;
			}
		}

		if (prev) {
			prev.addEventListener("click", function () {
				move(-1);
				startAutoplay();
			});
		}
		if (next) {
			next.addEventListener("click", function () {
				move(1);
				startAutoplay();
			});
		}
		shell.addEventListener("scroll", function (e) {
			if (!e.target || !e.target.classList || !e.target.classList.contains("omniloop-grid")) return;
			window.clearTimeout(shell._olCarouselScroll);
			shell._olCarouselScroll = window.setTimeout(function () {
				syncCarousel(root);
			}, 60);
		}, true);
		shell.addEventListener("mouseenter", function () {
			if (carouselOptions(root).pauseHover) paused = true;
		});
		shell.addEventListener("mouseleave", function () {
			paused = false;
		});
		shell.addEventListener("focusin", function () {
			if (carouselOptions(root).pauseHover) paused = true;
		});
		shell.addEventListener("focusout", function () {
			if (!shell.contains(document.activeElement)) paused = false;
		});
		document.addEventListener("visibilitychange", function () {
			if (!document.hidden) startAutoplay();
		});
		if (typeof ResizeObserver === "function") {
			var ro = new ResizeObserver(function () {
				syncCarousel(root);
				startAutoplay();
			});
			ro.observe(shell);
			var gridNow = carouselGrid(root);
			if (gridNow) ro.observe(gridNow);
		} else {
			window.addEventListener("resize", function () {
				syncCarousel(root);
			});
		}
		syncCarousel(root);
		window.setTimeout(startAutoplay, 120);
		root._olCarouselRestart = startAutoplay;
	}

	function pagerItems(page, pages) {
		page = Math.max(1, Math.min(pages, page || 1));
		if (pages <= 7) {
			var all = [];
			for (var n = 1; n <= pages; n++) all.push(n);
			return all;
		}
		var set = {};
		set[1] = true;
		set[pages] = true;
		set[page] = true;
		if (page > 1) set[page - 1] = true;
		if (page < pages) set[page + 1] = true;
		var nums = Object.keys(set).map(Number).sort(function (a, b) { return a - b; });
		var out = [];
		var prev = 0;
		nums.forEach(function (num) {
			if (num < 1 || num > pages) return;
			if (prev && num - prev > 1) out.push("…");
			out.push(num);
			prev = num;
		});
		return out;
	}

	function renderPager(root, data, onPage) {
		var nav = qs(root, ".omniloop-pagination");
		if (!nav) return;
		if (!showPagination(root)) {
			nav.hidden = true;
			while (nav.firstChild) nav.removeChild(nav.firstChild);
			return;
		}
		while (nav.firstChild) nav.removeChild(nav.firstChild);

		var pages = Number(data.total_pages) || 0;
		var page = Number(data.page) || 1;
		if (pages <= 1) {
			nav.hidden = true;
			return;
		}
		if (page < 1) page = 1;
		if (page > pages) page = pages;
		nav.hidden = false;
		nav.setAttribute("aria-label", "Pagination");

		function addBtn(label, target, extraClass, disabled, aria) {
			var btn = document.createElement("button");
			btn.type = "button";
			btn.className = "omniloop-page" + (extraClass ? " " + extraClass : "");
			btn.textContent = label;
			if (aria) btn.setAttribute("aria-label", aria);
			if (disabled) {
				btn.disabled = true;
			} else if (target) {
				btn.setAttribute("data-page", String(target));
				btn.addEventListener("click", function () {
					onPage(target);
				});
			}
			nav.appendChild(btn);
			return btn;
		}

		addBtn("‹", page - 1, "omniloop-page--nav", page <= 1, "Previous page");

		pagerItems(page, pages).forEach(function (item) {
			if (item === "…") {
				var gap = document.createElement("span");
				gap.className = "omniloop-page omniloop-page--gap";
				gap.textContent = "…";
				gap.setAttribute("aria-hidden", "true");
				nav.appendChild(gap);
				return;
			}
			var btn = addBtn(String(item), item, item === page ? "is-active" : "", false, "Page " + item);
			if (item === page) btn.setAttribute("aria-current", "page");
		});

		addBtn("›", page + 1, "omniloop-page--nav", page >= pages, "Next page");
	}

	function priceWrap(root) {
		return qs(root, ".omniloop-price");
	}

	function priceBounds(root) {
		var wrap = priceWrap(root);
		if (!wrap) return null;
		return {
			min: Number(wrap.getAttribute("data-bound-min") || 0),
			max: Number(wrap.getAttribute("data-bound-max") || 0),
			currency: wrap.getAttribute("data-currency") || ""
		};
	}

	function parsePriceToken(raw) {
		var text = String(raw || "").trim();
		if (!text) return null;
		var match = text.match(/^(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)$/);
		if (match) {
			var a = Number(match[1]);
			var b = Number(match[2]);
			return a > b ? [b, a] : [a, b];
		}
		var max = Number(text);
		if (!isFinite(max) || max <= 0) return null;
		return [0, max];
	}

	function priceRangeOf(root) {
		var bounds = priceBounds(root);
		var minEl = qs(root, ".omniloop-price__min");
		var maxEl = qs(root, ".omniloop-price__max");
		if (!bounds || !minEl || !maxEl || !(bounds.max > 0)) return null;
		var from = Number(minEl.value);
		var to = Number(maxEl.value);
		if (!isFinite(from)) from = bounds.min;
		if (!isFinite(to)) to = bounds.max;
		from = Math.max(bounds.min, Math.min(bounds.max, from));
		to = Math.max(bounds.min, Math.min(bounds.max, to));
		if (from > to) {
			var tmp = from;
			from = to;
			to = tmp;
		}
		if (from <= bounds.min && to >= bounds.max) return null;
		return [from, to];
	}

	function setPriceInputs(root, from, to) {
		var bounds = priceBounds(root);
		var minEl = qs(root, ".omniloop-price__min");
		var maxEl = qs(root, ".omniloop-price__max");
		if (!bounds || !minEl || !maxEl) return;
		var lo = from == null ? bounds.min : Number(from);
		var hi = to == null ? bounds.max : Number(to);
		if (!isFinite(lo)) lo = bounds.min;
		if (!isFinite(hi)) hi = bounds.max;
		lo = Math.max(bounds.min, Math.min(bounds.max, lo));
		hi = Math.max(bounds.min, Math.min(bounds.max, hi));
		if (lo > hi) {
			var swap = lo;
			lo = hi;
			hi = swap;
		}
		lo = Math.round(lo);
		hi = Math.round(hi);
		minEl.value = String(lo);
		maxEl.value = String(hi);
		syncPriceSlider(root, lo, hi);
	}

	function syncPriceSlider(root, from, to) {
		var bounds = priceBounds(root);
		var wrap = priceWrap(root);
		var slider = wrap ? qs(wrap, ".omniloop-price__slider") : null;
		var thumbMin = wrap ? qs(wrap, ".omniloop-price__thumb--min") : null;
		var thumbMax = wrap ? qs(wrap, ".omniloop-price__thumb--max") : null;
		if (!bounds || !slider || !thumbMin || !thumbMax) return;
		var lo = from == null ? Number(qs(root, ".omniloop-price__min") && qs(root, ".omniloop-price__min").value) : Number(from);
		var hi = to == null ? Number(qs(root, ".omniloop-price__max") && qs(root, ".omniloop-price__max").value) : Number(to);
		if (!isFinite(lo)) lo = bounds.min;
		if (!isFinite(hi)) hi = bounds.max;
		lo = Math.max(bounds.min, Math.min(bounds.max, lo));
		hi = Math.max(bounds.min, Math.min(bounds.max, hi));
		if (lo > hi) {
			var tmp = lo;
			lo = hi;
			hi = tmp;
		}
		thumbMin.value = String(Math.round(lo));
		thumbMax.value = String(Math.round(hi));
		var span = bounds.max - bounds.min;
		var pctLo = span > 0 ? ((lo - bounds.min) / span) * 100 : 0;
		var pctHi = span > 0 ? ((hi - bounds.min) / span) * 100 : 100;
		slider.style.setProperty("--ol-range-lo", pctLo.toFixed(2) + "%");
		slider.style.setProperty("--ol-range-hi", pctHi.toFixed(2) + "%");
		thumbMin.style.zIndex = lo > bounds.min + span * 0.5 ? "4" : "3";
		thumbMax.style.zIndex = "3";
	}

	function formatPriceChip(root, range) {
		var bounds = priceBounds(root);
		var cur = bounds ? bounds.currency : "";
		return cur + Math.round(range[0]) + " – " + cur + Math.round(range[1]);
	}

	function updateFacetCounts(root, counts) {
		if (!counts || typeof counts !== "object") return;
		qsa(root, "input[data-facet]").forEach(function (input) {
			var key = input.getAttribute("data-facet") || "";
			var value = input.getAttribute("data-value") || "";
			var map = counts[key] && typeof counts[key] === "object" ? counts[key] : {};
			var n = Number(map[value] || 0);
			var em = input.parentNode && input.parentNode.querySelector("em");
			if (em) em.textContent = String(n);
			var label = input.closest ? input.closest(".omniloop-check") : input.parentNode;
			if (label) {
				var hide = n === 0 && !input.checked;
				label.hidden = hide;
				input.disabled = hide;
			}
		});
		qsa(root, ".omniloop-facet").forEach(function (facet) {
			var checks = qsa(facet, ".omniloop-check");
			if (!checks.length) return;
			var visible = checks.some(function (el) { return !el.hidden; });
			facet.hidden = !visible;
		});
	}

	function restartCarousel(root) {
		syncCarousel(root);
		if (typeof root._olCarouselRestart === "function") {
			root._olCarouselRestart();
		}
	}

	function apply(root, data, append) {
		root.classList.remove("omniloop-app--loading");
		root._olLoadingMore = false;
		if (append) {
			appendGrid(root, data.posts || []);
		} else {
			renderGrid(root, data.posts || []);
		}
		root._olPage = Number(data.page) || 1;
		setCount(root, Number(data.total_posts) || 0);
		updateFacetCounts(root, data.facets_counts || {});
		syncFacetMore(root);
		renderChips(root);
		updateOpenLabel(root);
		restartCarousel(root);
		updatePaging(root, data);
	}

	function setLoadMoreLoading(root, loading) {
		var wrap = qs(root, ".omniloop-loadmore");
		if (!wrap) return;
		var btn = qs(wrap, ".omniloop-loadmore__btn");
		if (!btn) return;
		btn.disabled = !!loading;
		btn.textContent = loading
			? pagingLabel(root, "loadingLabel", "Loading…")
			: pagingLabel(root, "loadMoreLabel", "Load more");
		if (loading) {
			wrap.classList.add("is-loading");
		} else {
			wrap.classList.remove("is-loading");
		}
	}

	function renderLoadMore(root, data) {
		var wrap = qs(root, ".omniloop-loadmore");
		if (!wrap || paginationStyle(root) !== "load_more") {
			return;
		}
		var pages = Number(data.total_pages) || 0;
		var page = Number(data.page) || 1;
		wrap.setAttribute("data-pages", String(pages));
		wrap.setAttribute("data-page", String(page));
		var btn = qs(wrap, ".omniloop-loadmore__btn");
		var status = qs(wrap, ".omniloop-loadmore__status");
		if (pages <= 1 || page >= pages) {
			wrap.hidden = true;
			return;
		}
		wrap.hidden = false;
		if (btn) {
			btn.disabled = !!root._olLoadingMore;
			btn.textContent = root._olLoadingMore
				? pagingLabel(root, "loadingLabel", "Loading…")
				: pagingLabel(root, "loadMoreLabel", "Load more");
		}
		if (status) {
			var cfg = readConfig(root) || {};
			var perPage = Number(cfg.perPage) || 12;
			var total = Number(data.total_posts) || 0;
			var shown = Math.min(page * perPage, total);
			status.textContent = "Showing " + shown + " of " + total;
			status.hidden = false;
		}
	}

	function updateInfiniteSentinel(root, data) {
		var sentinel = qs(root, ".omniloop-infinite-sentinel");
		if (!sentinel || paginationStyle(root) !== "infinite") {
			return;
		}
		var pages = Number(data.total_pages) || 0;
		var page = Number(data.page) || 1;
		sentinel.setAttribute("data-pages", String(pages));
		sentinel.setAttribute("data-page", String(page));
		sentinel.hidden = pages <= 1 || page >= pages;
	}

	function updatePaging(root, data) {
		var style = paginationStyle(root);
		if (style === "pages") {
			renderPager(root, data, function (p) {
				query(root, readConfig(root), p);
			});
			return;
		}
		if (style === "load_more") {
			renderLoadMore(root, data);
			return;
		}
		if (style === "infinite") {
			updateInfiniteSentinel(root, data);
		}
	}

	function bindLoadMore(root, config) {
		var wrap = qs(root, ".omniloop-loadmore");
		if (!wrap) return;
		var btn = qs(wrap, ".omniloop-loadmore__btn");
		if (!btn || btn._olBound) return;
		btn._olBound = true;
		btn.addEventListener("click", function () {
			if (paginationStyle(root) !== "load_more") return;
			var page = Number(wrap.getAttribute("data-page") || root._olPage || 1);
			var pages = Number(wrap.getAttribute("data-pages") || 0);
			if (page >= pages || root._olLoadingMore) return;
			root._olLoadingMore = true;
			setLoadMoreLoading(root, true);
			query(root, config, page + 1, { append: true });
		});
	}

	function bindInfiniteScroll(root, config) {
		if (paginationStyle(root) !== "infinite") return;
		var sentinel = qs(root, ".omniloop-infinite-sentinel");
		if (!sentinel || root._olInfiniteBound) return;
		root._olInfiniteBound = true;
		if (typeof IntersectionObserver !== "function") {
			return;
		}
		var observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) return;
					if (root._olLoadingMore) return;
					var pages = Number(sentinel.getAttribute("data-pages") || 0);
					var page = Number(sentinel.getAttribute("data-page") || root._olPage || 1);
					if (pages <= 1 || page >= pages) return;
					root._olLoadingMore = true;
					query(root, config, page + 1, { append: true });
				});
			},
			{ rootMargin: "240px 0px" }
		);
		observer.observe(sentinel);
		root._olInfiniteObserver = observer;
	}

	function skeletonCount(root, config) {
		var grid = qs(root, ".omniloop-grid");
		var existing = grid ? qsa(grid, ".omniloop-card:not(.omniloop-card--skeleton)").length : 0;
		if (existing > 0) {
			return Math.min(existing, 12);
		}
		var n = Number(config && config.perPage) || 12;
		return Math.min(Math.max(n, 4), 12);
	}

	function renderSkeleton(root, config) {
		var grid = qs(root, ".omniloop-grid");
		if (!grid) return;
		var n = skeletonCount(root, config);
		while (grid.firstChild) grid.removeChild(grid.firstChild);
		for (var i = 0; i < n; i++) {
			var article = document.createElement("article");
			article.className = "omniloop-card omniloop-card--skeleton";
			article.setAttribute("aria-hidden", "true");
			var media = document.createElement("div");
			media.className = "omniloop-card__media omniloop-card__media--ph";
			var body = document.createElement("div");
			body.className = "omniloop-card__body";
			var title = document.createElement("span");
			title.className = "omniloop-skeleton-line omniloop-skeleton-line--title";
			var price = document.createElement("span");
			price.className = "omniloop-skeleton-line omniloop-skeleton-line--price";
			body.appendChild(title);
			body.appendChild(price);
			article.appendChild(media);
			article.appendChild(body);
			grid.appendChild(article);
		}
		root.classList.add("omniloop-app--loading");
		syncCarousel(root);
	}

	function chipFacetLabel(input) {
		var facet = input.closest ? input.closest(".omniloop-facet") : null;
		var title = facet ? qs(facet, ".omniloop-facet__title") : null;
		if (title && title.textContent) {
			return String(title.textContent).replace(/[▾▸]/g, "").replace(/\s+/g, " ").trim();
		}
		return input.getAttribute("data-facet") || "";
	}

	function chipValueLabel(input) {
		var wrap = input.closest ? input.closest(".omniloop-check") : input.parentNode;
		var span = wrap ? qs(wrap, "span") : null;
		if (span && span.textContent) {
			return String(span.textContent).replace(/\s+/g, " ").trim();
		}
		return input.getAttribute("data-value") || "";
	}

	function addChip(row, attrs, text) {
		var btn = document.createElement("button");
		btn.type = "button";
		btn.className = "omniloop-chip";
		Object.keys(attrs).forEach(function (key) {
			btn.setAttribute(key, attrs[key]);
		});
		btn.setAttribute("aria-label", "Remove " + text);
		var label = document.createElement("span");
		label.className = "omniloop-chip__label";
		label.textContent = text;
		var close = document.createElement("span");
		close.className = "omniloop-chip__remove";
		close.setAttribute("aria-hidden", "true");
		close.textContent = "×";
		btn.appendChild(label);
		btn.appendChild(close);
		row.appendChild(btn);
	}

	function renderChips(root) {
		var row = qs(root, ".omniloop-chips");
		if (!row) return;
		while (row.firstChild) row.removeChild(row.firstChild);

		qsa(root, "input[data-facet]:checked").forEach(function (input) {
			var key = input.getAttribute("data-facet") || "";
			var value = input.getAttribute("data-value") || "";
			if (!key || !value) return;
			var name = chipFacetLabel(input);
			var display = chipValueLabel(input);
			addChip(row, { "data-kind": "facet", "data-facet": key, "data-value": value }, name + ": " + display);
		});

		var search = qs(root, ".omniloop-search__input");
		var q = search ? String(search.value || "").trim() : "";
		if (q) {
			addChip(row, { "data-kind": "search" }, "Search: " + q);
		}

		var price = priceRangeOf(root);
		if (price) {
			addChip(row, { "data-kind": "price" }, "Price: " + formatPriceChip(root, price));
		}

		if (row.childNodes.length > 1) {
			var clear = document.createElement("button");
			clear.type = "button";
			clear.className = "omniloop-chip omniloop-chip--clear";
			clear.textContent = "Clear all";
			row.appendChild(clear);
		}

		row.hidden = !row.childNodes.length;
	}

	function resetAll(root) {
		qsa(root, "input[data-facet]").forEach(function (input) {
			input.checked = false;
		});
		var search = qs(root, ".omniloop-search__input");
		if (search) search.value = "";
		var sort = qs(root, ".omniloop-sort__select");
		if (sort) sort.value = "date";
		setPriceInputs(root, null, null);
	}

	function bindView(root, config) {
		qsa(root, ".omniloop-view__btn").forEach(function (btn) {
			btn.addEventListener("click", function () {
				applyView(root, btn.getAttribute("data-view") || "grid");
				writeUrl(root, payload(root, config, currentPage(root)));
			});
		});
	}

	function bindChips(root, config) {
		var row = qs(root, ".omniloop-chips");
		if (!row) return;
		row.addEventListener("click", function (e) {
			var btn = e.target && e.target.closest ? e.target.closest(".omniloop-chip") : null;
			if (!btn || !row.contains(btn)) return;
			if (btn.classList.contains("omniloop-chip--clear")) {
				resetAll(root);
				query(root, config, 1);
				return;
			}
			var kind = btn.getAttribute("data-kind") || "facet";
			if (kind === "search") {
				var search = qs(root, ".omniloop-search__input");
				if (search) search.value = "";
			} else if (kind === "price") {
				setPriceInputs(root, null, null);
			} else {
				var key = btn.getAttribute("data-facet") || "";
				var value = btn.getAttribute("data-value") || "";
				qsa(root, "input[data-facet]").forEach(function (input) {
					if (input.getAttribute("data-facet") === key && input.getAttribute("data-value") === value) {
						input.checked = false;
					}
				});
			}
			query(root, config, 1);
		});
	}

	function syncFacetMore(root) {
		qsa(root, ".omniloop-facet").forEach(function (facet) {
			var btn = qs(facet, ".omniloop-facet__more");
			if (!btn) return;
			var extras = qsa(facet, ".omniloop-check--extra").filter(function (el) {
				return !el.hidden;
			});
			var collapsed = extras.filter(function (el) {
				var input = qs(el, "input");
				return !(input && input.checked);
			});
			var expanded = facet.classList.contains("is-expanded");
			btn.hidden = extras.length === 0 || (!expanded && collapsed.length === 0);
			if (btn.hidden) {
				facet.classList.remove("is-expanded");
			}
			expanded = facet.classList.contains("is-expanded");
			btn.setAttribute("aria-expanded", expanded ? "true" : "false");
			var more = btn.getAttribute("data-more") || "Show more";
			var less = btn.getAttribute("data-less") || "Show less";
			btn.textContent = expanded ? less : (more + " (" + collapsed.length + ")");
		});
	}

	function activeFilterCount(root) {
		var n = qsa(root, "input[data-facet]:checked").length;
		var search = qs(root, ".omniloop-search__input");
		if (search && String(search.value || "").trim()) n += 1;
		if (priceRangeOf(root)) n += 1;
		return n;
	}

	function updateOpenLabel(root) {
		var btn = qs(root, ".omniloop-filters__open");
		if (!btn) return;
		var label = btn.getAttribute("data-label") || "Filters";
		var n = activeFilterCount(root);
		btn.textContent = n ? (label + " (" + n + ")") : label;
	}

	function bindDrawer(root) {
		var wrap = qs(root, ".omniloop-filters-wrap");
		var openBtn = qs(root, ".omniloop-filters__open");
		var closeBtn = qs(root, ".omniloop-filters__close");
		var backdrop = qs(root, ".omniloop-filters-backdrop");
		if (!wrap || !openBtn) return;

		function isDrawerMode() {
			if (root.classList.contains("omniloop-app--filter-chipsonly")) return true;
			return window.matchMedia && window.matchMedia("(max-width: 720px)").matches;
		}

		function setOpen(open) {
			var wasOpen = wrap.classList.contains("is-open");
			wrap.classList.toggle("is-open", open);
			openBtn.setAttribute("aria-expanded", open ? "true" : "false");
			if (backdrop) backdrop.hidden = !open;
			document.body.classList.toggle("omniloop-drawer-open", open && isDrawerMode());
			if (open && closeBtn) {
				closeBtn.focus();
			} else if (wasOpen && !open && isDrawerMode()) {
				openBtn.focus();
			}
		}

		openBtn.addEventListener("click", function () {
			setOpen(true);
		});
		if (closeBtn) {
			closeBtn.addEventListener("click", function () {
				setOpen(false);
			});
		}
		if (backdrop) {
			backdrop.addEventListener("click", function () {
				setOpen(false);
			});
		}
		document.addEventListener("keydown", function (e) {
			if (e.key === "Escape" && wrap.classList.contains("is-open")) {
				setOpen(false);
			}
		});
		if (window.matchMedia) {
			var mq = window.matchMedia("(min-width: 721px)");
			var onChange = function () {
				if (mq.matches && !root.classList.contains("omniloop-app--filter-chipsonly")) {
					setOpen(false);
				}
			};
			if (mq.addEventListener) mq.addEventListener("change", onChange);
			else if (mq.addListener) mq.addListener(onChange);
		}
	}

	function bindAccordion(root) {
		if (!root.classList.contains("omniloop-app--filter-accordion")) return;
		qsa(root, ".omniloop-facet").forEach(function (facet, index) {
			var title = qs(facet, ".omniloop-facet__title");
			if (!title) return;
			title.setAttribute("role", "button");
			title.setAttribute("tabindex", "0");
			if (index > 0) {
				facet.classList.add("is-collapsed");
				title.setAttribute("aria-expanded", "false");
			} else {
				title.setAttribute("aria-expanded", "true");
			}
			function toggle() {
				facet.classList.toggle("is-collapsed");
				title.setAttribute("aria-expanded", facet.classList.contains("is-collapsed") ? "false" : "true");
			}
			title.addEventListener("click", toggle);
			title.addEventListener("keydown", function (e) {
				if (e.key === "Enter" || e.key === " ") {
					e.preventDefault();
					toggle();
				}
			});
		});
	}

	function seoConfig(root) {
		var cfg = readConfig(root) || {};
		return cfg.seo && typeof cfg.seo === "object" ? cfg.seo : { enabled: false, keys: {}, applied: false };
	}

	function seoBasePath() {
		var path = window.location.pathname || "/";
		var idx = path.indexOf("/ol/");
		if (idx !== -1) path = path.slice(0, idx);
		if (!path) path = "/";
		if (path.charAt(path.length - 1) !== "/") path += "/";
		return path;
	}

	function reverseSeoKeys(keys) {
		var out = {};
		Object.keys(keys || {}).forEach(function (facetKey) {
			out[keys[facetKey]] = facetKey;
		});
		return out;
	}

	function parseSeoPath(keys) {
		var out = { facets: {}, page: 1, search: "", sort: "", view: "", price: "" };
		var path = window.location.pathname || "/";
		var idx = path.indexOf("/ol/");
		if (idx === -1) return out;
		var rest = path.slice(idx + 4).replace(/\/+$/, "");
		if (!rest) return out;
		var reverse = reverseSeoKeys(keys);
		var parts = rest.split("/").filter(Boolean);
		var i = 0;
		while (i < parts.length) {
			var alias = parts[i] || "";
			var raw = parts[i + 1] || "";
			try {
				alias = decodeURIComponent(alias);
				raw = decodeURIComponent(raw);
			} catch (err) {}
			i += 2;
			if (!alias || raw === "") continue;
			if (alias === "p") {
				out.page = Number(raw) || 1;
				continue;
			}
			if (alias === "q") {
				out.search = raw;
				continue;
			}
			if (alias === "sort") {
				out.sort = raw;
				continue;
			}
			if (alias === "view") {
				out.view = raw;
				continue;
			}
			if (alias === "price") {
				out.price = raw;
				continue;
			}
			var facetKey = reverse[alias] || "";
			if (!facetKey) continue;
			out.facets[facetKey] = raw.split("+").map(function (item) {
				return item.trim();
			}).filter(Boolean);
		}
		return out;
	}

	function clearOmniParams(url) {
		["ol_q", "ol_sort", "ol_page", "ol_price", "ol_price_min", "ol_price_max", "ol_view", "q", "sort", "view"].forEach(function (key) {
			url.searchParams.delete(key);
		});
		var stale = [];
		url.searchParams.forEach(function (_value, key) {
			if (key.indexOf("ol_f_") === 0) stale.push(key);
		});
		stale.forEach(function (key) {
			url.searchParams.delete(key);
		});
	}

	function writeUrl(root, body) {
		if (!window.history || !window.history.replaceState) return;
		var url = new URL(window.location.href);
		var seo = seoConfig(root);
		clearOmniParams(url);
		if (!seo.enabled) {
			if (body.search) url.searchParams.set("ol_q", body.search);
			if (body.sort_by && body.sort_by !== "date") url.searchParams.set("ol_sort", body.sort_by);
			if (usesPagedResults(root) && body.page && Number(body.page) > 1) url.searchParams.set("ol_page", String(body.page));
			if (viewOf(root) !== defaultViewOf(root)) {
				url.searchParams.set("ol_view", viewOf(root));
			}
			var legacyFacets = body.facets || {};
			Object.keys(legacyFacets).forEach(function (key) {
				if (key === "price_range") {
					if (legacyFacets[key] && legacyFacets[key].length >= 2) {
						url.searchParams.set(
							"ol_price",
							String(Math.round(Number(legacyFacets[key][0]) || 0)) + "-" + String(Math.round(Number(legacyFacets[key][1]) || 0))
						);
					}
					return;
				}
				if (legacyFacets[key] && legacyFacets[key].length) {
					url.searchParams.set("ol_f_" + key, legacyFacets[key].join(","));
				}
			});
			window.history.replaceState(null, "", url.pathname + url.search + url.hash);
			return;
		}

		var keys = seo.keys || {};
		var facets = body.facets || {};
		var pairs = [];
		Object.keys(facets).forEach(function (key) {
			if (key === "price_range") {
				if (facets[key] && facets[key].length >= 2) {
					pairs.push({
						alias: "price",
						value: String(Math.round(Number(facets[key][0]) || 0)) + "-" + String(Math.round(Number(facets[key][1]) || 0))
					});
				}
				return;
			}
			var alias = keys[key];
			if (!alias || !facets[key] || !facets[key].length) return;
			var values = facets[key].slice().map(String).filter(Boolean).sort();
			pairs.push({
				alias: alias,
				value: values.map(encodeURIComponent).join("+")
			});
		});
		pairs.sort(function (a, b) {
			if (a.alias < b.alias) return -1;
			if (a.alias > b.alias) return 1;
			return 0;
		});
		var page = usesPagedResults(root) ? (Number(body.page) || 1) : 1;
		if (page > 1) {
			pairs.push({ alias: "p", value: String(page) });
		}
		var path = seoBasePath();
		if (pairs.length) {
			var segs = [];
			pairs.forEach(function (pair) {
				segs.push(encodeURIComponent(pair.alias));
				segs.push(pair.value);
			});
			path += "ol/" + segs.join("/") + "/";
		}
		if (body.search) url.searchParams.set("q", body.search);
		if (body.sort_by && body.sort_by !== "date") url.searchParams.set("sort", body.sort_by);
		if (viewOf(root) !== defaultViewOf(root)) {
			url.searchParams.set("view", viewOf(root));
		}
		window.history.replaceState(null, "", path + url.search + url.hash);
	}

	function applyFromUrl(root) {
		var seo = seoConfig(root);
		var params = new URLSearchParams(window.location.search);
		var parsed = seo.enabled ? parseSeoPath(seo.keys || {}) : { facets: {}, page: 1, search: "", sort: "", view: "", price: "" };
		var dirty = false;
		var searchVal = parsed.search || params.get("q") || params.get("ol_q") || "";
		var sortVal = parsed.sort || params.get("sort") || params.get("ol_sort") || "";
		var viewVal = parsed.view || params.get("view") || params.get("ol_view") || "";
		var pageVal = usesPagedResults(root)
			? (parsed.page > 1 ? parsed.page : (Number(params.get("ol_page") || 0) || 1))
			: 1;
		var priceVal = parsed.price || params.get("ol_price") || "";
		if (!priceVal && (params.has("ol_price_min") || params.has("ol_price_max"))) {
			priceVal = String(params.get("ol_price_min") || "0") + "-" + String(params.get("ol_price_max") || "0");
		}

		var search = qs(root, ".omniloop-search__input");
		if (search && searchVal) {
			search.value = searchVal;
			dirty = true;
		}
		var sort = qs(root, ".omniloop-sort__select");
		if (sort && sortVal) {
			sort.value = sortVal;
			dirty = true;
		}
		var priceParts = parsePriceToken(priceVal);
		if (priceParts && priceWrap(root)) {
			setPriceInputs(root, priceParts[0], priceParts[1]);
			dirty = true;
		}
		qsa(root, "input[data-facet]").forEach(function (input) {
			var key = input.getAttribute("data-facet") || "";
			var values = parsed.facets[key];
			if (!values && params.has("ol_f_" + key)) {
				values = (params.get("ol_f_" + key) || "").split(",");
			}
			if (!values) return;
			dirty = true;
			input.checked = values.indexOf(input.getAttribute("data-value") || "") !== -1;
		});
		if (pageVal > 1) dirty = true;
		if (viewVal === "list" || viewVal === "grid") {
			applyView(root, viewVal);
			dirty = true;
		}
		return {
			dirty: dirty,
			page: pageVal
		};
	}

	function query(root, config, page, opts) {
		opts = opts || {};
		var append = !!opts.append;
		if (!usesPagedResults(root)) {
			page = 1;
			append = false;
		}
		var body = opts.body || payload(root, config, page);
		if (!opts.skipUrl) {
			writeUrl(root, body);
		}
		if (!opts.skipSync) {
			broadcastSync(root, body);
		}
		var key = cacheKey(body);
		if (cache[key]) {
			apply(root, cache[key], append);
			return;
		}

		var grid = qs(root, ".omniloop-grid");
		if (!append) {
			renderSkeleton(root, config);
			closeQuickView(root);
		} else {
			setLoadMoreLoading(root, true);
		}
		if (grid) grid.setAttribute("aria-busy", "true");

		if (root._olAbort && root._olAbort.abort) {
			root._olAbort.abort();
		}
		var ac = typeof AbortController === "function" ? new AbortController() : null;
		root._olAbort = ac;

		fetch(config.restUrl, {
			method: "POST",
			credentials: "same-origin",
			headers: {
				"Content-Type": "application/json",
				"X-WP-Nonce": config.nonce || ""
			},
			body: JSON.stringify(body),
			signal: ac ? ac.signal : undefined
		})
			.then(function (res) {
				if (!res.ok) throw new Error("query failed");
				return res.json();
			})
			.then(function (data) {
				if (!data || data.status !== "success") return;
				cache[key] = data;
				apply(root, data, append);
			})
			.catch(function (err) {
				if (err && err.name === "AbortError") return;
				root.classList.remove("omniloop-app--loading");
				root._olLoadingMore = false;
				setLoadMoreLoading(root, false);
			})
			.then(function () {
				if (grid && root._olAbort === ac) grid.removeAttribute("aria-busy");
			});
	}

	function bind(root) {
		var config = readConfig(root);
		if (!config) return;

		var form = qs(root, ".omniloop-filters");
		if (form) {
			form.addEventListener("submit", function (e) {
				e.preventDefault();
			});
		}

		bindDrawer(root);
		bindAccordion(root);
		bindChips(root, config);
		bindView(root, config);
		bindCarousel(root);
		bindQuickView(root);
		bindLoadMore(root, config);
		bindInfiniteScroll(root, config);

		root.addEventListener("click", function (e) {
			var target = e.target;
			if (!target || !target.closest) return;
			var emptyReset = target.closest(".omniloop-empty__reset");
			if (!emptyReset || !root.contains(emptyReset)) return;
			var resetBtn = qs(root, ".omniloop-reset");
			if (resetBtn) resetBtn.click();
		});

		var searchTimer = 0;
		var search = qs(root, ".omniloop-search__input");
		if (search) {
			search.addEventListener("input", function () {
				window.clearTimeout(searchTimer);
				searchTimer = window.setTimeout(function () {
					query(root, config, 1);
				}, 250);
			});
		}

		qsa(root, "input[data-facet], .omniloop-sort__select").forEach(function (el) {
			el.addEventListener("change", function () {
				if (el.getAttribute && el.getAttribute("data-facet") === "rating" && el.checked) {
					qsa(root, "input[data-facet=\"rating\"]").forEach(function (other) {
						if (other !== el) other.checked = false;
					});
				}
				query(root, config, 1);
			});
		});

		var priceTimer = 0;
		function schedulePriceQuery() {
			window.clearTimeout(priceTimer);
			priceTimer = window.setTimeout(function () {
				query(root, config, 1);
			}, 80);
		}
		function commitPriceFromInputs(source) {
			var bounds = priceBounds(root);
			if (!bounds) return;
			var minEl = qs(root, ".omniloop-price__min");
			var maxEl = qs(root, ".omniloop-price__max");
			if (!minEl || !maxEl) return;
			var from = Number(minEl.value);
			var to = Number(maxEl.value);
			if (!isFinite(from)) from = bounds.min;
			if (!isFinite(to)) to = bounds.max;
			if (from > to) {
				if (source === minEl) to = from;
				else from = to;
			}
			setPriceInputs(root, from, to);
			schedulePriceQuery();
		}
		qsa(root, ".omniloop-price__min, .omniloop-price__max").forEach(function (input) {
			input.addEventListener("change", function () {
				commitPriceFromInputs(input);
			});
			input.addEventListener("keydown", function (e) {
				if (e.key === "Enter") {
					e.preventDefault();
					input.blur();
				}
			});
		});
		qsa(root, ".omniloop-price__thumb").forEach(function (thumb) {
			thumb.addEventListener("input", function () {
				var bounds = priceBounds(root);
				var wrap = priceWrap(root);
				if (!bounds || !wrap) return;
				var thumbMin = qs(wrap, ".omniloop-price__thumb--min");
				var thumbMax = qs(wrap, ".omniloop-price__thumb--max");
				if (!thumbMin || !thumbMax) return;
				var from = Number(thumbMin.value);
				var to = Number(thumbMax.value);
				if (!isFinite(from)) from = bounds.min;
				if (!isFinite(to)) to = bounds.max;
				if (thumb === thumbMin && from > to) from = to;
				if (thumb === thumbMax && to < from) to = from;
				setPriceInputs(root, from, to);
				schedulePriceQuery();
			});
		});
		syncPriceSlider(root);

		qsa(root, ".omniloop-facet__find-input").forEach(function (input) {
			input.addEventListener("input", function () {
				var facet = input.closest ? input.closest(".omniloop-facet") : null;
				if (!facet) return;
				var q = String(input.value || "").trim().toLowerCase();
				qsa(facet, ".omniloop-check").forEach(function (opt) {
					var label = String(opt.getAttribute("title") || opt.textContent || "").toLowerCase();
					var match = !q || label.indexOf(q) !== -1;
					opt.classList.toggle("is-facet-hidden", !match);
					opt.hidden = !match;
				});
			});
		});

		var reset = qs(root, ".omniloop-reset");
		if (reset) {
			reset.addEventListener("click", function () {
				resetAll(root);
				query(root, config, 1);
			});
		}

		qsa(root, ".omniloop-facet__more").forEach(function (btn) {
			btn.addEventListener("click", function () {
				var facet = btn.closest ? btn.closest(".omniloop-facet") : btn.parentNode;
				if (!facet || !facet.classList) return;
				facet.classList.toggle("is-expanded");
				syncFacetMore(root);
			});
		});

		var fromUrl = applyFromUrl(root);
		syncFacetMore(root);
		renderChips(root);
		updateOpenLabel(root);
		var nav = qs(root, ".omniloop-pagination");
		var loadMore = qs(root, ".omniloop-loadmore");
		var infinite = qs(root, ".omniloop-infinite-sentinel");
		var pages = nav
			? Number(nav.getAttribute("data-pages") || 0)
			: loadMore
				? Number(loadMore.getAttribute("data-pages") || 0)
				: infinite
					? Number(infinite.getAttribute("data-pages") || 0)
					: 0;
		var seo = seoConfig(root);
		if (fromUrl.dirty && !seo.applied) {
			query(root, config, fromUrl.page);
			return;
		}
		if (!fromUrl.dirty && !seo.applied) {
			var preset = presetBody(root, config);
			if (preset) {
				applyBodyToDom(root, preset);
				query(root, config, 1, { skipSync: true, body: preset });
				return;
			}
		}
		var page = fromUrl.page
			|| Number((nav && nav.getAttribute("data-page")) || (loadMore && loadMore.getAttribute("data-page")) || (infinite && infinite.getAttribute("data-page")) || 1)
			|| 1;
		updatePaging(root, { total_pages: pages, page: page, total_posts: 0 });
	}

	function boot() {
		bindGlobalSync();
		qsa(document, ".omniloop-app").forEach(bind);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}
})();
