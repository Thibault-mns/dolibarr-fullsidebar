/* Copyright (C) 2026 TH Investissements / Matelas No Stress - GPL v3+
 *
 * FullSidebar - expand/collapse of the left menu tree.
 *
 * Loaded from <head> by main.inc.php (module_parts['js']), so everything waits for
 * DOMContentLoaded. No jQuery dependency: the script must keep working whatever the
 * theme ships.
 */
(function () {
	'use strict';

	// Legacy key (<= 1.5.0), shared by every user and every Dolibarr instance of the
	// same browser origin. Dropped on first run, see storageKey().
	var LEGACY_STORAGE_KEY = 'fullsidebar.opened';

	/**
	 * Storage key of the opened branches. Scoped by url root and user id: two users on a
	 * shared workstation, or a production and a staging instance served under the same
	 * domain, must not restore each other's tree.
	 *
	 * @param {Element} nav the nav#fsb element
	 * @return {string}
	 */
	function storageKey(nav) {
		return 'fullsidebar.opened.v2:' + storageScope(nav);
	}

	/**
	 * Url root + user id part of the storage key.
	 *
	 * @param {Element} nav the nav#fsb element
	 * @return {string}
	 */
	function storageScope(nav) {
		return (nav.getAttribute('data-fsb-urlroot') || '/') + ':' + (nav.getAttribute('data-fsb-user') || '0');
	}

	/**
	 * Read the list of branches the user opened. Any storage error (private mode,
	 * cookies blocked, quota) must leave the menu usable, hence the try/catch.
	 *
	 * @param {string} key storage key, see storageKey()
	 * @return {Object} map of key => true
	 */
	function readOpened(key, nav) {
		try {
			window.localStorage.removeItem(LEGACY_STORAGE_KEY);
			// 1.5.1 key: it stored full-path branch keys, replaced by short ones since.
			window.localStorage.removeItem('fullsidebar.opened:' + storageScope(nav));
			var raw = window.localStorage.getItem(key);
			if (!raw) {
				return {};
			}
			var parsed = JSON.parse(raw);
			return (parsed && typeof parsed === 'object') ? parsed : {};
		} catch (e) {
			return {};
		}
	}

	/**
	 * Persist the list of opened branches.
	 *
	 * @param {string} key    storage key, see storageKey()
	 * @param {Object} opened map of key => true
	 * @return {void}
	 */
	function writeOpened(key, opened) {
		try {
			window.localStorage.setItem(key, JSON.stringify(opened));
		} catch (e) {
			/* nothing we can do, and nothing that should break the menu */
		}
	}

	/**
	 * Open or close one branch.
	 *
	 * @param {Element} item    the li.fsb-item
	 * @param {boolean} open    target state
	 * @param {boolean} persist store the new state
	 * @param {Object}  opened  current map (mutated when persist is true)
	 * @return {void}
	 */
	function setOpen(item, open, persist, opened) {
		var toggle = item.querySelector(':scope > .fsb-row > .fsb-tg');
		var sub = item.querySelector(':scope > .fsb-sub');
		if (!sub) {
			return;
		}

		item.classList.toggle('fsb-open', open);
		sub.hidden = !open;
		if (toggle) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		}

		if (persist) {
			var key = item.getAttribute('data-fsb-key');
			if (key) {
				if (open) {
					opened[key] = true;
				} else {
					delete opened[key];
				}
			}
		}
	}

	/**
	 * Nearest ancestor (the element itself included) that both allows vertical
	 * scrolling and actually overflows. Null when nothing scrolls, so callers can
	 * skip rather than scroll the wrong box.
	 *
	 * @param {Element} el starting element
	 * @return {Element|null}
	 */
	function scrollParentOf(el) {
		var node = el;
		while (node && node !== document.body && node !== document.documentElement) {
			var oy = window.getComputedStyle(node).overflowY;
			if ((oy === 'auto' || oy === 'scroll') && node.scrollHeight > node.clientHeight + 1) {
				return node;
			}
			node = node.parentElement;
		}
		return null;
	}

	/**
	 * Wire the tree.
	 *
	 * @return {void}
	 */
	function init() {
		var nav = document.getElementById('fsb');
		if (!nav) {
			return;
		}

		var remember = nav.getAttribute('data-fsb-remember') === '1';
		var expandAll = nav.classList.contains('fsb-expandall');
		var key = storageKey(nav);
		var opened = remember ? readOpened(key, nav) : {};

		/**
		 * Open every ancestor of an item, so a branch is never left open inside a
		 * closed parent.
		 *
		 * @param {Element} item the li.fsb-item
		 * @return {void}
		 */
		function openAncestors(item) {
			var parent = item.parentElement;
			while (parent && parent !== nav) {
				if (parent.classList && parent.classList.contains('fsb-item')) {
					setOpen(parent, true, false, opened);
				}
				parent = parent.parentElement;
			}
		}

		// Restore what the user had opened. The path to the current page was already
		// opened server side and stays open whatever the stored state says.
		if (remember && !expandAll) {
			var items = nav.querySelectorAll('.fsb-item[data-fsb-key]');
			Array.prototype.forEach.call(items, function (item) {
				if (!item.querySelector(':scope > .fsb-sub')) {
					return;
				}
				if (item.classList.contains('fsb-open')) {
					return;
				}
				if (opened[item.getAttribute('data-fsb-key')]) {
					openAncestors(item);
					setOpen(item, true, false, opened);
				}
			});
		}

		// Consistency pass: whatever opened a branch - this script or the server - its
		// ancestors must be open as well, otherwise an expanded subtree stays invisible
		// inside a collapsed one and the tree reads as inconsistent.
		Array.prototype.forEach.call(nav.querySelectorAll('.fsb-item.fsb-open'), openAncestors);

		nav.addEventListener('click', function (ev) {
			var toggle = ev.target.closest('.fsb-tg');
			if (!toggle || !nav.contains(toggle)) {
				return;
			}
			ev.preventDefault();

			var item = toggle.closest('.fsb-item');
			if (!item) {
				return;
			}

			var willOpen = !item.classList.contains('fsb-open');
			setOpen(item, willOpen, remember, opened);
			if (remember) {
				writeOpened(key, opened);
			}
		});

		// Bring the current page into view when the sidebar is taller than the screen.
		//
		// The element that scrolls is NOT always the tree: on desktop the whole
		// .side-nav column scrolls (search form, bookmarks and tree together), on a
		// phone it is the tree inside the drawer. Scrolling `nav` blindly - what this
		// did before - moved nothing on desktop, because nav itself no longer
		// overflows. So the nearest ancestor that actually overflows is looked up.
		var active = nav.querySelector('.fsb-active > .fsb-row > .fsb-link');
		var box = active ? scrollParentOf(nav) : null;
		if (active && box) {
			var offset = active.getBoundingClientRect().top - box.getBoundingClientRect().top;
			if (offset > box.clientHeight - 40 || offset < 0) {
				box.scrollTop += offset - (box.clientHeight / 3);
			}
		}

		renderBreadcrumb();
	}

	/**
	 * Render a breadcrumb in the freed top bar (when the top menu is hidden), from the
	 * active path already flagged in the tree (fsb-active -> fsb-current ancestors).
	 */
	function renderBreadcrumb() {
		// Only when the top menu entries are hidden (FULLSIDEBAR_HIDE_TOPMENU). The flag
		// is written by the server: a computed-style probe on the first entry is wrong
		// when the user has no top entry at all (external users).
		var nav = document.getElementById('fsb');
		if (!nav || nav.getAttribute('data-fsb-hidetop') !== '1') {
			return;
		}
		var urlroot = nav.getAttribute('data-fsb-urlroot') || '';
		var active = nav.querySelector('.fsb-item.fsb-active');
		var current = nav.querySelector('.fsb-item.fsb-current');
		var start = active || current;
		var pageTitle = null;

		// Card pages reached without a mainmenu param (e.g. from the global search)
		// leave the session mainmenu stale ("home"). Detect the real module from the URL
		// and append the page title as the last crumb.
		if (!active && current && isHomeModule(current) && !isHomeUrl(urlroot)) {
			var detected = detectModuleFromUrl(nav, urlroot);
			if (detected) {
				start = detected;
				pageTitle = getPageTitle();
			}
		}

		if (!start) {
			return;
		}

		var crumbs = [];
		var el = start;
		while (el && el !== nav) {
			if (el.classList && el.classList.contains('fsb-item')) {
				var link = el.querySelector(':scope > .fsb-row .fsb-link');
				var tx = el.querySelector(':scope > .fsb-row .fsb-tx');
				if (tx) {
					crumbs.unshift({
						text: tx.textContent.trim(),
						href: link ? link.getAttribute('href') : null
					});
				}
			}
			el = el.parentElement;
		}
		if (!crumbs.length) {
			return;
		}
		// Always start from Home, taken from the Home module of the tree (translated label
		// and its own link), unless the path already starts there.
		var home = homeCrumb(nav);
		if (home && !isHomeModule(start.closest('.fsb-item.fsb-d0') || start)) {
			crumbs.unshift(home);
		}
		// Append the page title as the last crumb when the active entry is absent (card pages).
		if (pageTitle) {
			crumbs.push({ text: pageTitle, href: null });
		}

		// The core prints <div class="tmenudiv"><ul class="tmenu">: div.tmenu does not
		// exist, it is kept only as a fallback for a theme that would print one.
		var bar = document.querySelector('header#id-top div.tmenudiv, header#id-top div.tmenu');
		if (!bar) {
			return;
		}
		var bc = bar.querySelector('.topbar-breadcrumb');
		if (!bc) {
			bc = document.createElement('nav');
			bc.className = 'topbar-breadcrumb';
			bc.setAttribute('aria-label', nav.getAttribute('data-fsb-breadcrumb') || 'Breadcrumb');
			bar.appendChild(bc);
		}
		bc.textContent = '';

		for (var i = 0; i < crumbs.length; i++) {
			if (i > 0) {
				var sep = document.createElement('span');
				sep.className = 'bc-sep';
				sep.setAttribute('aria-hidden', 'true');
				sep.textContent = '›';
				bc.appendChild(sep);
			}
			var c = crumbs[i];
			var isLast = (i === crumbs.length - 1);
			if (isLast || !c.href) {
				var span = document.createElement('span');
				span.className = 'bc-crumb bc-current';
				span.textContent = c.text;
				bc.appendChild(span);
			} else {
				var a = document.createElement('a');
				a.className = 'bc-crumb';
				a.href = c.href;
				a.textContent = c.text;
				bc.appendChild(a);
			}
		}
	}

	/**
	 * First crumb: the Home module of the tree (depth 0, key fsb-home).
	 *
	 * @param {Element} nav the nav#fsb element
	 * @return {{text: string, href: ?string}|null} null when the user has no Home entry
	 */
	function homeCrumb(nav) {
		var mods = nav.querySelectorAll('.fsb-item.fsb-d0');
		for (var i = 0; i < mods.length; i++) {
			if (!isHomeModule(mods[i])) {
				continue;
			}
			var link = mods[i].querySelector(':scope > .fsb-row .fsb-link');
			var tx = mods[i].querySelector(':scope > .fsb-row .fsb-tx');
			if (!tx) {
				return null;
			}
			return {
				text: tx.textContent.trim(),
				href: (link && link.getAttribute('href')) || null
			};
		}
		return null;
	}

	/**
	 * Whether the given fsb item is the "Home" module (root).
	 */
	function isHomeModule(item) {
		return item.getAttribute('data-fsb-mm') === 'home';
	}

	/**
	 * Whether the current URL is the dashboard/home page.
	 */
	function isHomeUrl(urlroot) {
		var p = relativePath(urlroot);
		return p === '' || p === '/' || p === '/index.php';
	}

	/**
	 * Current path with the Dolibarr url root removed ("/htdocs/societe/card.php" ->
	 * "/societe/card.php"). Without it, every page starts with the same segment on an
	 * install served from a sub-directory, and module detection always picks the first
	 * module.
	 *
	 * @param {string} urlroot DOL_URL_ROOT as given by the server
	 * @return {string}
	 */
	function relativePath(urlroot) {
		var p = window.location.pathname || '';
		if (urlroot && p.indexOf(urlroot) === 0) {
			p = p.substring(urlroot.length);
		}
		return p;
	}

	/**
	 * Detect the module (a depth-0 fsb item) whose link shares the current URL's first
	 * path segment. Fallback for card pages reached without a mainmenu param.
	 */
	function detectModuleFromUrl(nav, urlroot) {
		var parts = relativePath(urlroot).split('/');
		var seg = (parts[1] || '').toLowerCase();
		// External modules live under /custom/<module>/: use the module segment.
		if (seg === 'custom' && parts[2]) {
			seg = parts[2].toLowerCase();
		}
		if (!seg || seg === 'index.php') {
			return null;
		}
		var mods = nav.querySelectorAll('.fsb-item.fsb-d0');
		for (var i = 0; i < mods.length; i++) {
			var link = mods[i].querySelector(':scope > .fsb-row .fsb-link');
			if (link) {
				var href = link.getAttribute('href') || '';
				if (href.indexOf('/' + seg + '/') !== -1) {
					return mods[i];
				}
			}
		}
		return null;
	}

	/**
	 * Best-effort page title: Dolibarr titles are "Page title - ...", keep the first part.
	 */
	function getPageTitle() {
		var t = (document.title || '').trim();
		if (!t) {
			return null;
		}
		var parts = t.split(' - ');
		return parts[0].trim() || t;
	}

	// Run init() before the first paint (not at DOMContentLoaded) so the tree does not
	// flash collapsed and then pop open when the remembered branches are restored.
	// Same anti-FOUC pattern the theme uses for its burger button.
	var started = false;
	function startWhenTreeReady() {
		if (started) {
			return true;
		}
		// Flag the page as soon as the tree starts being parsed (before the first paint):
		// the layout rules of fullsidebar.css (sticky column, wider mobile drawer) are
		// scoped on html.fsb-on, so they never touch the stock eldy menu.
		var fsb = document.getElementById('fsb');
		if (fsb) {
			document.documentElement.classList.add('fsb-on');
			// Theme flag (fsb-theme-eldy, fsb-theme-md...): the column layout rules only
			// apply to the theme they were written for.
			var theme = (fsb.getAttribute('data-fsb-theme') || '').replace(/[^a-z0-9_-]/gi, '');
			if (theme) {
				document.documentElement.classList.add('fsb-theme-' + theme);
			}
		}
		// #fsb-end is printed right after </nav>: its presence means the whole tree
		// is in the DOM. Testing for the first .fsb-item would start on a half-parsed
		// tree and restore / measure only what was parsed so far.
		if (!document.getElementById('fsb-end')) {
			return false;
		}
		started = true;
		init();
		return true;
	}

	if (!startWhenTreeReady()) {
		var observer = null;
		if (window.MutationObserver) {
			observer = new MutationObserver(function () {
				if (startWhenTreeReady()) {
					observer.disconnect();
				}
			});
			observer.observe(document.documentElement, { childList: true, subtree: true });
		}
		// Last chance once the document is parsed, then stop observing in any case: on a
		// page without the tree (popup, hidden left menu, external user, handler switched
		// off in the setup) #fsb-end never appears, and the observer would otherwise watch
		// every DOM mutation for the whole life of the page.
		document.addEventListener('DOMContentLoaded', function () {
			startWhenTreeReady();
			if (observer) {
				observer.disconnect();
			}
		});
	}
})();
