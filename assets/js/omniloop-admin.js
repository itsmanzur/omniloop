(function () {
	"use strict";

	function $(id) {
		return document.getElementById(id);
	}

	function setStatus(text, tone) {
		var el = $("omniloop-reindex-status");
		if (!el) return;
		el.textContent = text || "";
		el.classList.remove("is-ok", "is-error");
		if (tone === "ok") el.classList.add("is-ok");
		if (tone === "error") el.classList.add("is-error");
	}

	function setBusy(busy) {
		["omniloop-reindex", "omniloop-reindex-secondary"].forEach(function (id) {
			var btn = $(id);
			if (btn) btn.disabled = !!busy;
		});
		var progress = $("omniloop-reindex-progress");
		if (progress) {
			progress.classList.toggle("is-on", !!busy);
			progress.setAttribute("aria-hidden", busy ? "false" : "true");
		}
		if (!busy) {
			var bar = $("omniloop-reindex-bar");
			if (bar) bar.style.width = "0%";
		}
	}

	function setProgress(processed, total) {
		var bar = $("omniloop-reindex-bar");
		if (!bar) return;
		var pct = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 8;
		bar.style.width = String(pct) + "%";
	}

	function formatCount(n) {
		try {
			return Number(n).toLocaleString();
		} catch (e) {
			return String(n);
		}
	}

	function reindex(reset) {
		var cfg = window.omniloopAdmin;
		if (!cfg || !cfg.restUrl) return;

		fetch(cfg.restUrl, {
			method: "POST",
			credentials: "same-origin",
			headers: {
				"Content-Type": "application/json",
				"X-WP-Nonce": cfg.nonce || ""
			},
			body: JSON.stringify({ reset: !!reset })
		})
			.then(function (res) {
				if (!res.ok) throw new Error("fail");
				return res.json();
			})
			.then(function (data) {
				if (!data) throw new Error("fail");
				var processed = Number(data.processed) || 0;
				var total = Number(data.total) || 0;
				setProgress(processed, total);
				setStatus(String(processed) + " / " + String(total));
				if (data.done) {
					setStatus((cfg.i18n && cfg.i18n.done) || "Done", "ok");
					setBusy(false);
					var count = $("omniloop-indexed-count");
					if (count && typeof data.indexed_rows !== "undefined") {
						count.textContent = formatCount(data.indexed_rows);
						count.setAttribute("data-count", String(data.indexed_rows));
					}
					return;
				}
				reindex(false);
			})
			.catch(function () {
				setStatus((cfg.i18n && cfg.i18n.error) || "Error", "error");
				setBusy(false);
			});
	}

	function startRebuild() {
		var cfg = window.omniloopAdmin;
		setBusy(true);
		setStatus((cfg && cfg.i18n && cfg.i18n.rebuilding) || "…");
		setProgress(0, 1);
		reindex(true);
	}

	function cfgI18n(key, fallback) {
		var cfg = window.omniloopAdmin;
		if (cfg && cfg.i18n && cfg.i18n[key]) return cfg.i18n[key];
		return fallback;
	}

	function restGet(url, params) {
		var cfg = window.omniloopAdmin || {};
		var query = new URL(url, window.location.origin);
		Object.keys(params || {}).forEach(function (key) {
			if (params[key] !== "" && params[key] != null) {
				query.searchParams.set(key, String(params[key]));
			}
		});
		return fetch(query.toString(), {
			credentials: "same-origin",
			headers: { "X-WP-Nonce": cfg.nonce || "" }
		}).then(function (res) {
			if (!res.ok) throw new Error("fail");
			return res.json();
		});
	}

	function cloneTemplate(id) {
		var tpl = document.getElementById(id);
		if (!tpl || !tpl.content || !tpl.content.firstElementChild) return null;
		return tpl.content.firstElementChild.cloneNode(true);
	}

	function applyRepeatNames(item, index) {
		item.querySelectorAll("[data-name]").forEach(function (el) {
			var tpl = el.getAttribute("data-name");
			if (tpl) el.setAttribute("name", tpl.replace(/__i__/g, String(index)));
		});
		item.querySelectorAll("[data-facet-builder]").forEach(function (builder) {
			var nameTpl = builder.getAttribute("data-name-tpl") || "";
			builder.setAttribute("data-name", nameTpl.replace(/__i__/g, String(index)));
			rewriteFacetInputNames(builder);
		});
	}

	function reindexRepeat(list) {
		var items = [];
		for (var i = 0; i < list.children.length; i++) {
			if (list.children[i].hasAttribute("data-repeat-item")) {
				items.push(list.children[i]);
			}
		}
		items.forEach(function (item, index) {
			applyRepeatNames(item, index);
		});
	}

	function rewriteFacetInputNames(builder) {
		var prefix = builder.getAttribute("data-name") || "";
		builder.querySelectorAll("[data-facet-row]").forEach(function (row) {
			var select = row.querySelector(".omniloop-facet-row__key");
			var key = select ? select.value : "";
			row.querySelectorAll("[data-facet-values] input").forEach(function (input) {
				if (!key) {
					input.removeAttribute("name");
					return;
				}
				if (key === "price_range") {
					var slot = input.getAttribute("data-price-slot") || "0";
					input.name = prefix + "[price_range][" + slot + "]";
				} else {
					input.name = prefix + "[" + key + "][]";
				}
			});
		});
	}

	function priceMarkup(selected) {
		var from = selected && selected[0] != null ? selected[0] : "";
		var to = selected && selected[1] != null ? selected[1] : "";
		return (
			'<div class="omniloop-facet-price">' +
			"<label>" +
			cfgI18n("from", "From") +
			' <input type="number" step="0.01" min="0" data-price-slot="0" value="' +
			String(from).replace(/"/g, "&quot;") +
			'"></label>' +
			"<label>" +
			cfgI18n("to", "To") +
			' <input type="number" step="0.01" min="0" data-price-slot="1" value="' +
			String(to).replace(/"/g, "&quot;") +
			'"></label>' +
			"</div>"
		);
	}

	function checksMarkup(options, selected) {
		var picked = {};
		(selected || []).forEach(function (value) {
			picked[String(value)] = true;
		});
		if (!options || !options.length) {
			return '<p class="omniloop-admin-hint">' + cfgI18n("noResults", "No matches") + "</p>";
		}
		var html = '<div class="omniloop-facet-checks">';
		options.forEach(function (opt) {
			var value = String(opt.value || "");
			var label = String(opt.label || value);
			var on = picked[value] ? " checked" : "";
			html +=
				'<label class="omniloop-facet-check"><input type="checkbox" value="' +
				value.replace(/"/g, "&quot;") +
				'"' +
				on +
				"><span>" +
				label.replace(/</g, "&lt;") +
				"</span></label>";
		});
		html += "</div>";
		return html;
	}

	function selectedValues(row) {
		var keySelect = row.querySelector(".omniloop-facet-row__key");
		var key = keySelect ? keySelect.value : "";
		if (key === "price_range") {
			var inputs = row.querySelectorAll("[data-price-slot]");
			if (!inputs.length) {
				inputs = row.querySelectorAll("[data-facet-values] input[type='number']");
			}
			if (inputs.length >= 2) {
				return [inputs[0].value, inputs[1].value];
			}
			return [];
		}
		var out = [];
		row.querySelectorAll("[data-facet-values] input[type='checkbox']:checked").forEach(function (el) {
			out.push(el.value);
		});
		return out;
	}

	function loadFacetRow(row, builder) {
		var select = row.querySelector(".omniloop-facet-row__key");
		var box = row.querySelector("[data-facet-values]");
		if (!select || !box) return;
		var key = select.value;
		var picked = selectedValues(row);
		if (!key) {
			box.innerHTML = "";
			rewriteFacetInputNames(builder);
			return;
		}
		if (key === "price_range") {
			box.innerHTML = priceMarkup(picked);
			rewriteFacetInputNames(builder);
			return;
		}
		var cfg = window.omniloopAdmin || {};
		if (!cfg.facetValuesUrl) return;
		box.innerHTML = '<p class="omniloop-admin-hint">' + cfgI18n("loading", "Loading…") + "</p>";
		var typeSelect = builder.closest("[data-repeat-item]");
		var postType = builder.getAttribute("data-post-type") || "product";
		if (typeSelect) {
			var typeEl = typeSelect.querySelector(".omniloop-preset-type");
			if (typeEl && typeEl.value) postType = typeEl.value;
		}
		restGet(cfg.facetValuesUrl, { post_type: postType, key: key })
			.then(function (data) {
				box.innerHTML = checksMarkup((data && data.values) || [], picked);
				rewriteFacetInputNames(builder);
			})
			.catch(function () {
				box.innerHTML = checksMarkup([], picked);
				rewriteFacetInputNames(builder);
			});
	}

	function bindFacetBuilder(builder) {
		if (builder.getAttribute("data-bound") === "1") return;
		builder.setAttribute("data-bound", "1");
		builder.addEventListener("click", function (event) {
			var add = event.target.closest("[data-facet-add]");
			if (add) {
				var wrap = builder.querySelector("[data-facet-rows]");
				var node = cloneTemplate("omniloop-template-facet-row");
				if (wrap && node) {
					wrap.appendChild(node);
					rewriteFacetInputNames(builder);
				}
				return;
			}
			var remove = event.target.closest("[data-facet-remove]");
			if (remove) {
				var row = remove.closest("[data-facet-row]");
				var wrap = builder.querySelector("[data-facet-rows]");
				if (row && wrap) {
					if (wrap.querySelectorAll("[data-facet-row]").length < 2) {
						row.querySelectorAll("select").forEach(function (el) {
							el.selectedIndex = 0;
						});
						var box = row.querySelector("[data-facet-values]");
						if (box) box.innerHTML = "";
					} else {
						row.remove();
					}
					rewriteFacetInputNames(builder);
				}
			}
		});
		builder.addEventListener("change", function (event) {
			if (event.target.classList.contains("omniloop-facet-row__key")) {
				loadFacetRow(event.target.closest("[data-facet-row]"), builder);
			}
		});
		builder.querySelectorAll("[data-facet-row]").forEach(function (row) {
			var select = row.querySelector(".omniloop-facet-row__key");
			if (select && select.value && select.value !== "price_range") {
				loadFacetRow(row, builder);
			} else {
				row.querySelectorAll("[data-facet-values] input[type='number']").forEach(function (input, slot) {
					input.setAttribute("data-price-slot", String(slot));
				});
				rewriteFacetInputNames(builder);
			}
		});
	}

	function initRepeaters() {
		document.querySelectorAll("[data-repeat-add]").forEach(function (btn) {
			btn.addEventListener("click", function () {
				var kind = btn.getAttribute("data-repeat-add");
				var list = document.querySelector("[data-repeat='" + kind + "']");
				var node = cloneTemplate("omniloop-template-" + kind);
				if (!list || !node) return;
				list.appendChild(node);
				reindexRepeat(list);
				node.querySelectorAll("[data-facet-builder]").forEach(bindFacetBuilder);
			});
		});
		document.addEventListener("click", function (event) {
			var remove = event.target.closest("[data-repeat-remove]");
			if (!remove) return;
			var item = remove.closest("[data-repeat-item]");
			var list = item && item.parentElement;
			if (!item || !list || !list.hasAttribute("data-repeat")) return;
			event.preventDefault();
			if (list.querySelectorAll("[data-repeat-item]").length < 2) {
				item.querySelectorAll("input[type='text'], input[type='search'], textarea").forEach(function (el) {
					el.value = "";
				});
				item.querySelectorAll("select").forEach(function (el) {
					el.selectedIndex = 0;
				});
				item.querySelectorAll("[data-facet-values]").forEach(function (box) {
					box.innerHTML = "";
				});
				reindexRepeat(list);
				return;
			}
			item.remove();
			reindexRepeat(list);
		});
		document.querySelectorAll("[data-repeat]").forEach(reindexRepeat);
		document.querySelectorAll("[data-facet-builder]").forEach(bindFacetBuilder);
		document.addEventListener("change", function (event) {
			if (!event.target.classList.contains("omniloop-preset-type")) return;
			var card = event.target.closest("[data-repeat-item]");
			if (!card) return;
			card.querySelectorAll("[data-facet-builder]").forEach(function (builder) {
				builder.setAttribute("data-post-type", event.target.value);
				builder.querySelectorAll("[data-facet-row]").forEach(function (row) {
					var select = row.querySelector(".omniloop-facet-row__key");
					if (select && select.value) loadFacetRow(row, builder);
				});
			});
		});
	}

	function pinChip(id, title) {
		var span = document.createElement("span");
		span.className = "omniloop-pin-chip";
		span.setAttribute("data-id", String(id));
		span.innerHTML =
			'<input type="hidden" name="omniloop_settings[merchandising][pin_ids][]" value="' +
			String(id) +
			'"><span></span><button type="button" class="omniloop-pin-chip__remove" aria-label="' +
			cfgI18n("unpin", "Unpin") +
			'">&times;</button>';
		span.querySelector("span").textContent = title || "#" + id;
		return span;
	}

	function initPinPickers() {
		document.querySelectorAll(".omniloop-pin-picker").forEach(function (picker) {
			var search = picker.querySelector(".omniloop-pin-picker__search");
			var results = picker.querySelector(".omniloop-pin-picker__results");
			var chips = picker.querySelector("[data-pin-chips]");
			if (!search || !results || !chips) return;
			var timer = null;

			function pinnedIds() {
				var ids = {};
				chips.querySelectorAll("[data-id]").forEach(function (chip) {
					ids[chip.getAttribute("data-id")] = true;
				});
				return ids;
			}

			function hideResults() {
				results.hidden = true;
				results.innerHTML = "";
			}

			search.addEventListener("input", function () {
				clearTimeout(timer);
				var q = search.value.trim();
				if (q.length < 1) {
					hideResults();
					return;
				}
				timer = setTimeout(function () {
					var cfg = window.omniloopAdmin || {};
					if (!cfg.searchUrl) return;
					restGet(cfg.searchUrl, {
						q: q,
						post_type: picker.getAttribute("data-post-type") || "product"
					})
						.then(function (data) {
							var posts = (data && data.posts) || [];
							var taken = pinnedIds();
							results.innerHTML = "";
							posts.forEach(function (post) {
								if (taken[String(post.id)]) return;
								var btn = document.createElement("button");
								btn.type = "button";
								btn.className = "omniloop-pin-picker__hit";
								btn.textContent = post.title;
								btn.setAttribute("data-id", String(post.id));
								results.appendChild(btn);
							});
							if (!results.children.length) {
								var empty = document.createElement("p");
								empty.className = "omniloop-admin-hint";
								empty.textContent = cfgI18n("noResults", "No matches");
								results.appendChild(empty);
							}
							results.hidden = false;
						})
						.catch(hideResults);
				}, 220);
			});

			results.addEventListener("click", function (event) {
				var hit = event.target.closest(".omniloop-pin-picker__hit");
				if (!hit) return;
				var id = hit.getAttribute("data-id");
				if (!id || pinnedIds()[id]) return;
				chips.appendChild(pinChip(id, hit.textContent));
				search.value = "";
				hideResults();
			});

			chips.addEventListener("click", function (event) {
				var btn = event.target.closest(".omniloop-pin-chip__remove");
				if (!btn) return;
				var chip = btn.closest(".omniloop-pin-chip");
				if (chip) chip.remove();
			});

			document.addEventListener("click", function (event) {
				if (!picker.contains(event.target)) hideResults();
			});
		});
	}

	document.addEventListener("DOMContentLoaded", function () {
		var primary = $("omniloop-reindex");
		var secondary = $("omniloop-reindex-secondary");
		if (primary) primary.addEventListener("click", startRebuild);
		if (secondary) secondary.addEventListener("click", startRebuild);

		var tabs = document.querySelectorAll(".omniloop-admin-tab");
		if (!tabs.length) return;

		function showTab(id) {
			tabs.forEach(function (tab) {
				var on = tab.getAttribute("data-tab") === id;
				tab.classList.toggle("is-active", on);
				tab.setAttribute("aria-selected", on ? "true" : "false");
				tab.setAttribute("tabindex", on ? "0" : "-1");
			});
			document.querySelectorAll("[data-tab-panel]").forEach(function (panel) {
				var on = panel.getAttribute("data-tab-panel") === id;
				if (on) {
					panel.removeAttribute("hidden");
					panel.classList.add("is-active");
				} else {
					panel.setAttribute("hidden", "hidden");
					panel.classList.remove("is-active");
				}
			});
		}

		tabs.forEach(function (tab) {
			tab.addEventListener("click", function () {
				var id = tab.getAttribute("data-tab") || "index";
				showTab(id);
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, "", "#" + id);
				}
			});
			tab.addEventListener("keydown", function (event) {
				var keys = { ArrowLeft: -1, ArrowRight: 1, Home: "start", End: "end" };
				var move = keys[event.key];
				if (move === undefined) return;
				event.preventDefault();
				var list = Array.prototype.slice.call(tabs);
				var index = list.indexOf(tab);
				if (move === "start") index = 0;
				else if (move === "end") index = list.length - 1;
				else index = (index + move + list.length) % list.length;
				list[index].focus();
				list[index].click();
			});
		});

		var hash = (window.location.hash || "").replace("#", "");
		var valid = { index: 1, seo: 1, facets: 1, analytics: 1, tools: 1 };
		showTab(valid[hash] ? hash : "index");

		var videoInput = $("omniloop-intro-video");
		var videoPick = $("omniloop-intro-video-pick");
		var videoClear = $("omniloop-intro-video-clear");
		if (videoPick && videoInput && window.wp && wp.media) {
			var frame;
			var videoI18n = window.omniloopAdmin && window.omniloopAdmin.i18n ? window.omniloopAdmin.i18n : {};
			videoPick.addEventListener("click", function () {
				if (!frame) {
					frame = wp.media({
						title: videoI18n.videoTitle || "Select intro video",
						button: { text: videoI18n.videoUse || "Use this video" },
						library: { type: "video" },
						multiple: false
					});
					frame.on("select", function () {
						var file = frame.state().get("selection").first();
						if (!file) return;
						var json = file.toJSON();
						if (json && json.url) videoInput.value = json.url;
					});
				}
				frame.open();
			});
		}
		if (videoClear && videoInput) {
			videoClear.addEventListener("click", function () {
				videoInput.value = "";
				videoInput.focus();
			});
		}

		initRepeaters();
		initPinPickers();
	});
})();
