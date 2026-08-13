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
		var range = qs(root, ".omniloop-price__range");
		if (range) {
			facets.price_range = [Number(range.min || 0), Number(range.value || range.max || 0)];
		}
		return facets;
	}

	function payload(root, config, page) {
		var searchEl = qs(root, ".omniloop-search__input");
		var sortEl = qs(root, ".omniloop-sort__select");
		return {
			post_type: config.postType || "post",
			per_page: Number(config.perPage) || 12,
			page: page || 1,
			search: searchEl ? String(searchEl.value || "").slice(0, 120) : "",
			sort_by: sortEl ? sortEl.value : "date",
			facets: selectedFacets(root)
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

	function renderGrid(root, posts) {
		var grid = qs(root, ".omniloop-grid");
		if (!grid) return;
		while (grid.firstChild) grid.removeChild(grid.firstChild);

		if (!posts || !posts.length) {
			var empty = document.createElement("p");
			empty.className = "omniloop-empty";
			empty.textContent = "No results.";
			grid.appendChild(empty);
			return;
		}

		posts.forEach(function (item) {
			var article = document.createElement("article");
			article.className = "omniloop-card";
			article.setAttribute("data-id", String(item.id || ""));

			var link = document.createElement("a");
			link.className = "omniloop-card__link";
			link.href = typeof item.permalink === "string" ? item.permalink : "#";

			if (item.thumbnail) {
				var media = document.createElement("div");
				media.className = "omniloop-card__media";
				var img = document.createElement("img");
				img.src = item.thumbnail;
				img.alt = item.title || "";
				img.loading = "lazy";
				img.width = 600;
				img.height = 450;
				media.appendChild(img);
				link.appendChild(media);
			} else {
				var mediaEmpty = document.createElement("div");
				mediaEmpty.className = "omniloop-card__media omniloop-card__media--empty";
				var letter = document.createElement("span");
				letter.className = "omniloop-card__fallback";
				letter.textContent = (item.title || "•").charAt(0).toUpperCase();
				mediaEmpty.appendChild(letter);
				link.appendChild(mediaEmpty);
			}

			var title = document.createElement("h3");
			title.className = "omniloop-card__title";
			title.textContent = item.title || "";
			link.appendChild(title);

			if (item.price) {
				var price = document.createElement("p");
				price.className = "omniloop-card__price";
				price.textContent = item.price;
				link.appendChild(price);
			}

			article.appendChild(link);
			grid.appendChild(article);
		});
	}

	function renderPager(root, data, onPage) {
		var nav = qs(root, ".omniloop-pagination");
		if (!nav) return;
		while (nav.firstChild) nav.removeChild(nav.firstChild);

		var pages = Number(data.total_pages) || 0;
		var page = Number(data.page) || 1;
		if (pages <= 1) {
			nav.hidden = true;
			return;
		}
		nav.hidden = false;

		for (var i = 1; i <= pages && i <= 20; i++) {
			var btn = document.createElement("button");
			btn.type = "button";
			btn.className = "omniloop-page" + (i === page ? " is-active" : "");
			btn.textContent = String(i);
			btn.setAttribute("data-page", String(i));
			btn.addEventListener("click", function (ev) {
				onPage(Number(ev.currentTarget.getAttribute("data-page") || 1));
			});
			nav.appendChild(btn);
		}
	}

	function apply(root, data) {
		renderGrid(root, data.posts || []);
		setCount(root, Number(data.total_posts) || 0);
	}

	function writeUrl(body) {
		if (!window.history || !window.history.replaceState) return;
		var url = new URL(window.location.href);
		["ol_q", "ol_sort", "ol_page", "ol_price"].forEach(function (key) {
			url.searchParams.delete(key);
		});
		var stale = [];
		url.searchParams.forEach(function (_value, key) {
			if (key.indexOf("ol_f_") === 0) stale.push(key);
		});
		stale.forEach(function (key) {
			url.searchParams.delete(key);
		});
		if (body.search) url.searchParams.set("ol_q", body.search);
		if (body.sort_by && body.sort_by !== "date") url.searchParams.set("ol_sort", body.sort_by);
		if (body.page && Number(body.page) > 1) url.searchParams.set("ol_page", String(body.page));
		var facets = body.facets || {};
		Object.keys(facets).forEach(function (key) {
			if (key === "price_range") {
				if (facets[key] && facets[key][1] != null) {
					url.searchParams.set("ol_price", String(facets[key][1]));
				}
				return;
			}
			if (facets[key] && facets[key].length) {
				url.searchParams.set("ol_f_" + key, facets[key].join(","));
			}
		});
		window.history.replaceState(null, "", url.pathname + url.search + url.hash);
	}

	function applyFromUrl(root) {
		var params = new URLSearchParams(window.location.search);
		var dirty = false;
		var search = qs(root, ".omniloop-search__input");
		if (search && params.has("ol_q")) {
			search.value = params.get("ol_q") || "";
			dirty = true;
		}
		var sort = qs(root, ".omniloop-sort__select");
		if (sort && params.has("ol_sort")) {
			sort.value = params.get("ol_sort") || "date";
			dirty = true;
		}
		var range = qs(root, ".omniloop-price__range");
		var rangeLabel = qs(root, ".omniloop-price__value");
		if (range && params.has("ol_price")) {
			range.value = params.get("ol_price") || range.max;
			if (rangeLabel) rangeLabel.textContent = range.value;
			dirty = true;
		}
		qsa(root, "input[data-facet]").forEach(function (input) {
			var key = input.getAttribute("data-facet") || "";
			if (!params.has("ol_f_" + key)) return;
			dirty = true;
			var values = (params.get("ol_f_" + key) || "").split(",");
			input.checked = values.indexOf(input.getAttribute("data-value") || "") !== -1;
		});
		if (params.has("ol_page")) dirty = true;
		return {
			dirty: dirty,
			page: Number(params.get("ol_page") || 1) || 1
		};
	}

	function query(root, config, page) {
		var body = payload(root, config, page);
		writeUrl(body);
		var key = cacheKey(body);
		if (cache[key]) {
			apply(root, cache[key]);
			renderPager(root, cache[key], function (p) { query(root, config, p); });
			return;
		}

		var grid = qs(root, ".omniloop-grid");
		if (grid) grid.setAttribute("aria-busy", "true");

		fetch(config.restUrl, {
			method: "POST",
			credentials: "same-origin",
			headers: {
				"Content-Type": "application/json",
				"X-WP-Nonce": config.nonce || ""
			},
			body: JSON.stringify(body)
		})
			.then(function (res) {
				if (!res.ok) throw new Error("query failed");
				return res.json();
			})
			.then(function (data) {
				if (!data || data.status !== "success") return;
				cache[key] = data;
				apply(root, data);
				renderPager(root, data, function (p) { query(root, config, p); });
			})
			.catch(function () {})
			.then(function () {
				if (grid) grid.removeAttribute("aria-busy");
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
				query(root, config, 1);
			});
		});

		var range = qs(root, ".omniloop-price__range");
		var rangeLabel = qs(root, ".omniloop-price__value");
		if (range) {
			range.addEventListener("input", function () {
				if (rangeLabel) rangeLabel.textContent = range.value;
			});
			range.addEventListener("change", function () {
				query(root, config, 1);
			});
		}

		var reset = qs(root, ".omniloop-reset");
		if (reset) {
			reset.addEventListener("click", function () {
				qsa(root, "input[data-facet]").forEach(function (input) {
					input.checked = false;
				});
				if (search) search.value = "";
				if (range) {
					range.value = range.max;
					if (rangeLabel) rangeLabel.textContent = range.max;
				}
				var sort = qs(root, ".omniloop-sort__select");
				if (sort) sort.value = "date";
				query(root, config, 1);
			});
		}

		var fromUrl = applyFromUrl(root);
		var nav = qs(root, ".omniloop-pagination");
		var pages = nav ? Number(nav.getAttribute("data-pages") || 0) : 0;
		if (fromUrl.dirty) {
			query(root, config, fromUrl.page);
			return;
		}
		renderPager(root, { total_pages: pages, page: 1 }, function (p) {
			query(root, config, p);
		});
	}

	function boot() {
		qsa(document, ".omniloop-app").forEach(bind);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}
})();
