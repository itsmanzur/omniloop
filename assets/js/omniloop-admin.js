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

	document.addEventListener("DOMContentLoaded", function () {
		var primary = $("omniloop-reindex");
		var secondary = $("omniloop-reindex-secondary");
		if (primary) primary.addEventListener("click", startRebuild);
		if (secondary) secondary.addEventListener("click", startRebuild);

		var tabs = document.querySelectorAll(".omniloop-admin-tab");
		if (!tabs.length) return;

		function showTab(id) {
			tabs.forEach(function (tab) {
				tab.classList.toggle("is-active", tab.getAttribute("data-tab") === id);
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
		});

		var hash = (window.location.hash || "").replace("#", "");
		if (hash) showTab(hash);
	});
})();
