(function () {
	"use strict";

	function $(id) {
		return document.getElementById(id);
	}

	function setStatus(text) {
		var el = $("omniloop-reindex-status");
		if (el) el.textContent = text;
	}

	function reindex(reset) {
		var cfg = window.omniloopAdmin;
		if (!cfg || !cfg.restUrl) return;

		var btn = $("omniloop-reindex");
		if (btn) btn.disabled = true;

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
				setStatus(String(data.processed || 0) + " / " + String(data.total || 0));
				if (data.done) {
					setStatus((cfg.i18n && cfg.i18n.done) || "Done");
					if (btn) btn.disabled = false;
					return;
				}
				reindex(false);
			})
			.catch(function () {
				setStatus((cfg.i18n && cfg.i18n.error) || "Error");
				if (btn) btn.disabled = false;
			});
	}

	document.addEventListener("DOMContentLoaded", function () {
		var btn = $("omniloop-reindex");
		if (!btn) return;
		btn.addEventListener("click", function () {
			setStatus("…");
			reindex(true);
		});
	});
})();
