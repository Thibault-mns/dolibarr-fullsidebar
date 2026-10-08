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
		var sub = item.querySelector(':scope > .fsb-sub');
		if (!sub) {
			return;
		}

		item.classList.toggle('fsb-open', open);
		sub.hidden = !open;
		var control = item.closest('.fsb-click-title')
			? item.querySelector(':scope > .fsb-row > .fsb-link')
			: item.querySelector(':scope > .fsb-row > .fsb-tg');
		if (control) {
			control.setAttribute('aria-expanded', open ? 'true' : 'false');
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
	 * Flag the page as laid out with the drawer: the theme shows its hamburger (its own
	 * breakpoint, which depends on the number of top menu entries) or the screen is narrow.
	 *
	 * @return {void}
	 */
	function flagDrawer() {
		var burger = document.querySelector('.menuhider');
		var on = window.innerWidth <= 767
			|| !!(burger && window.getComputedStyle(burger).display !== 'none');
		document.documentElement.classList.toggle('fsb-drawer', on);
	}

	/**
	 * Navigate to a menu link, honouring its target.
	 *
	 * @param {HTMLAnchorElement} link the a.fsb-link
	 * @return {void}
	 */
	function followLink(link) {
		if (link.target && link.target !== '_self') {
			window.open(link.href, link.target);
		} else {
			window.location.href = link.href;
		}
	}

	/**
	 * Title mode: the label toggles the branch, so it owns the disclosure state and the
	 * small button only opens the entry's own page. Entries without a page lose the button.
	 *
	 * @param {Element} nav the nav#fsb element
	 * @return {void}
	 */
	function wireTitleMode(nav) {
		var openLabel = nav.getAttribute('data-fsb-openlabel') || '';
		Array.prototype.forEach.call(nav.querySelectorAll('.fsb-tg'), function (btn) {
			var row = btn.parentElement;
			var link = row.querySelector(':scope > .fsb-link');
			var sub = row.parentElement.querySelector(':scope > .fsb-sub');
			if (link && sub) {
				link.setAttribute('aria-expanded', btn.getAttribute('aria-expanded') || 'false');
				link.setAttribute('aria-controls', sub.id);
			}
			btn.removeAttribute('aria-expanded');
			btn.removeAttribute('aria-controls');
			btn.removeAttribute('aria-labelledby');
			btn.setAttribute('aria-label', openLabel);
			btn.title = openLabel;
			if (!row.querySelector(':scope > a.fsb-link')) {
				btn.hidden = true;
			}
		});
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
		var mode = nav.getAttribute('data-fsb-click');
		if (mode !== 'title' && mode !== 'dblclick') {
			mode = 'arrow';
		}
		if (mode === 'title') {
			nav.classList.add('fsb-click-title');
			wireTitleMode(nav);
		}

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

		function toggleItem(item) {
			setOpen(item, !item.classList.contains('fsb-open'), remember, opened);
			if (remember) {
				writeOpened(key, opened);
			}
		}

		function hasSub(item) {
			return !!(item && item.querySelector(':scope > .fsb-sub'));
		}

		nav.addEventListener('click', function (ev) {
			var toggle = ev.target.closest('.fsb-tg');
			if (toggle && nav.contains(toggle)) {
				ev.preventDefault();
				var owner = toggle.closest('.fsb-item');
				if (mode === 'title') {
					var own = owner && owner.querySelector(':scope > .fsb-row > a.fsb-link');
					if (own) {
						followLink(own);
					}
				} else if (owner) {
					toggleItem(owner);
				}
				return;
			}

			var plain = ev.button === 0 && !ev.ctrlKey && !ev.metaKey && !ev.shiftKey && !ev.altKey;
			var title = ev.target.closest('.fsb-link');
			if (mode === 'arrow' || !plain || !title || !nav.contains(title)) {
				return;
			}
			var item = title.closest('.fsb-item');
			if (hasSub(item)) {
				ev.preventDefault();
				toggleItem(item);
			}
		});

		if (mode === 'dblclick') {
			nav.addEventListener('dblclick', function (ev) {
				var link = ev.target.closest('a.fsb-link');
				if (link && nav.contains(link) && hasSub(link.closest('.fsb-item'))) {
					followLink(link);
				}
			});
		}

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

		// Phone drawer: the CSS bounds the tree to 100dvh minus a fixed offset, but the
		// real offset (top bar + the tools block the theme keeps above the tree) varies
		// by theme and content. Too small an offset and the last entries sit below the
		// screen with nothing left to scroll, so the height is measured instead.
		function fitHeight() {
			if (!document.documentElement.classList.contains('fsb-drawer')) {
				nav.style.maxHeight = '';
				return;
			}
			if (nav.offsetParent === null) {
				return;
			}
			var top = Math.max(nav.getBoundingClientRect().top, 0);
			nav.style.maxHeight = Math.max(160, window.innerHeight - top - 8) + 'px';
		}
		// The theme keeps a fixed padding on the right of the top bar for the tools block
		// (180 to 385px depending on the tools enabled), whatever its real width: the CSS
		// drops it with the top entries hidden. The breadcrumb is capped to the room left
		// between its own left edge and the tools block, measured on screen: that holds
		// whether the block is laid over the bar (eldy) or sits beside it in a flex row.
		var tools = document.querySelector('header#id-top .login_block');
		function fitTopBar() {
			var bc = document.querySelector('.topbar-breadcrumb');
			if (nav.getAttribute('data-fsb-hidetop') !== '1' || !tools || !bc) {
				return;
			}
			if (document.documentElement.classList.contains('fsb-drawer') || !tools.offsetWidth) {
				bc.style.maxWidth = '';
				return;
			}
			var free = tools.getBoundingClientRect().left - bc.getBoundingClientRect().left - 12;
			bc.style.maxWidth = Math.max(free, 120) + 'px';
		}

		function refresh() {
			flagDrawer();
			fitHeight();
			fitTopBar();
			fitBreadcrumb();
		}
		refresh();
		if (window.ResizeObserver && tools) {
			new ResizeObserver(fitTopBar).observe(tools);
		}
		window.addEventListener('resize', refresh);
		window.addEventListener('orientationchange', refresh);
		window.addEventListener('load', refresh);
		if (window.MutationObserver) {
			new MutationObserver(refresh).observe(document.body, { attributes: true, attributeFilter: ['class'] });
		}

		renderBreadcrumb();
		refresh();
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

		if (window.ResizeObserver && !bc.fsbObserved) {
			bc.fsbObserved = true;
			new ResizeObserver(fitBreadcrumb).observe(bc);
		}
		fitBreadcrumb();
	}

	/**
	 * Make the breadcrumb fit its box: when it overflows, middle crumbs are replaced by a
	 * single ellipsis (first and last stay). Squeezing every crumb equally, which is what
	 * flex shrinking does, leaves "A... O... T... L...".
	 *
	 * @return {void}
	 */
	function fitBreadcrumb() {
		var bc = document.querySelector('.topbar-breadcrumb');
		if (!bc) {
			return;
		}
		var gap = bc.querySelector('.bc-gap');
		if (gap) {
			gap.parentNode.removeChild(gap);
		}
		var nodes = Array.prototype.slice.call(bc.children);
		nodes.forEach(function (n) { n.classList.remove('bc-hidden'); });

		// children are crumb, separator, crumb, separator, ..., crumb
		var count = (nodes.length + 1) / 2;
		if (count < 3 || bc.scrollWidth <= bc.clientWidth) {
			return;
		}

		gap = document.createElement('span');
		gap.className = 'bc-gap bc-crumb';
		gap.textContent = '\u2026';
		bc.insertBefore(gap, nodes[2]);

		// First step hides crumb 1 only (its separator now follows the ellipsis); the next
		// ones hide the crumb and the separator before it.
		for (var k = 1; k < count - 1 && bc.scrollWidth > bc.clientWidth; k++) {
			nodes[2 * k].classList.add('bc-hidden');
			if (k > 1) {
				nodes[2 * k - 1].classList.add('bc-hidden');
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
			flagDrawer();
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
