(function () {
	"use strict";

	var root = document.getElementById("omniloop-docs-app");
	if (!root) return;

	var sections = Array.prototype.slice.call(root.querySelectorAll(".omniloop-docs-section"));
	var nav = document.getElementById("omniloop-docs-nav");
	var search = document.getElementById("omniloop-docs-search");
	var empty = document.getElementById("omniloop-docs-empty");
	var prevBtn = document.getElementById("omniloop-docs-prev");
	var nextBtn = document.getElementById("omniloop-docs-next");
	var visible = sections.slice();
	var current = 0;

	function sectionId(section) {
		return section.getAttribute("data-section") || "";
	}

	function buildNav() {
		if (!nav) return;
		nav.innerHTML = "";
		sections.forEach(function (section, index) {
			var li = document.createElement("li");
			var btn = document.createElement("button");
			btn.type = "button";
			btn.textContent = section.getAttribute("data-title") || sectionId(section);
			btn.setAttribute("data-goto", String(index));
			btn.addEventListener("click", function () {
				showIndex(index, true);
			});
			li.appendChild(btn);
			nav.appendChild(li);
		});
	}

	function navButtons() {
		return nav ? Array.prototype.slice.call(nav.querySelectorAll("button")) : [];
	}

	function showIndex(index, updateHash) {
		if (!visible.length) return;
		if (index < 0) index = 0;
		if (index >= visible.length) index = visible.length - 1;
		current = index;

		sections.forEach(function (section) {
			section.classList.remove("is-active");
		});
		visible[current].classList.add("is-active");

		var activeId = sectionId(visible[current]);
		navButtons().forEach(function (btn) {
			var section = sections[Number(btn.getAttribute("data-goto") || -1)];
			btn.classList.toggle("is-active", section && sectionId(section) === activeId);
		});

		if (prevBtn) prevBtn.disabled = current <= 0;
		if (nextBtn) nextBtn.disabled = current >= visible.length - 1;

		if (updateHash) {
			var id = activeId;
			if (id && history.replaceState) {
				history.replaceState(null, "", "#" + id);
			} else if (id) {
				window.location.hash = id;
			}
		}

		visible[current].scrollIntoView({ block: "nearest", behavior: "smooth" });
	}

	function applySearch(query) {
		var q = String(query || "").trim().toLowerCase();
		visible = [];
		sections.forEach(function (section, index) {
			var hay = (
				(section.getAttribute("data-title") || "") +
				" " +
				(section.getAttribute("data-keywords") || "") +
				" " +
				(section.textContent || "")
			).toLowerCase();
			var match = !q || hay.indexOf(q) !== -1;
			var btn = navButtons()[index];
			if (btn) btn.classList.toggle("is-hidden", !match);
			if (match) visible.push(section);
		});

		if (empty) empty.classList.toggle("is-on", visible.length === 0);
		if (!visible.length) {
			sections.forEach(function (section) {
				section.classList.remove("is-active");
			});
			if (prevBtn) prevBtn.disabled = true;
			if (nextBtn) nextBtn.disabled = true;
			return;
		}
		showIndex(0, false);
	}

	function bindPicks() {
		Array.prototype.slice.call(root.querySelectorAll("[data-pick-group]")).forEach(function (group) {
			var name = group.getAttribute("data-pick-group");
			var preview = root.querySelector('[data-preview="' + name + '"]');
			var picks = Array.prototype.slice.call(group.querySelectorAll(".omniloop-docs-pick"));
			picks.forEach(function (pick) {
				pick.addEventListener("click", function () {
					picks.forEach(function (other) {
						other.classList.remove("is-active");
					});
					pick.classList.add("is-active");
					if (!preview) return;
					var title = preview.querySelector(".omniloop-docs-preview__title");
					var body = preview.querySelector(".omniloop-docs-preview__body");
					if (title) title.textContent = pick.getAttribute("data-title") || "";
					if (body) body.textContent = pick.getAttribute("data-body") || "";
				});
			});
		});
	}

	function bindChecklist() {
		Array.prototype.slice.call(root.querySelectorAll("[data-checklist]")).forEach(function (list) {
			var key = "omniloopDocsChecklist:" + (list.getAttribute("data-checklist") || "default");
			var saved = {};
			try {
				saved = JSON.parse(window.localStorage.getItem(key) || "{}") || {};
			} catch (e) {
				saved = {};
			}
			Array.prototype.slice.call(list.querySelectorAll('input[type="checkbox"]')).forEach(function (input) {
				if (saved[input.id]) input.checked = true;
				input.addEventListener("change", function () {
					saved[input.id] = !!input.checked;
					try {
						window.localStorage.setItem(key, JSON.stringify(saved));
					} catch (err) {}
				});
			});
		});
	}

	function openFromHash() {
		var hash = String(window.location.hash || "").replace(/^#/, "");
		if (!hash) {
			showIndex(0, false);
			return;
		}
		var idx = visible.findIndex(function (section) {
			return sectionId(section) === hash;
		});
		showIndex(idx >= 0 ? idx : 0, false);
	}

	buildNav();
	bindPicks();
	bindChecklist();

	if (search) {
		search.addEventListener("input", function () {
			applySearch(search.value);
		});
	}

	if (prevBtn) {
		prevBtn.addEventListener("click", function () {
			showIndex(current - 1, true);
		});
	}
	if (nextBtn) {
		nextBtn.addEventListener("click", function () {
			showIndex(current + 1, true);
		});
	}

	document.addEventListener("keydown", function (event) {
		if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
			if (!search) return;
			event.preventDefault();
			search.focus();
			search.select();
		}
	});

	window.addEventListener("hashchange", openFromHash);
	applySearch("");
	openFromHash();
})();
