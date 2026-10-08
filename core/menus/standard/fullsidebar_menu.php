<?php
/* Copyright (C) 2026	TH Investissements / Matelas No Stress
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       custom/fullsidebar/core/menus/standard/fullsidebar_menu.php
 *	\brief      Menu handler rendering the WHOLE menu tree in the left sidebar
 *
 *	Loaded by main.inc.php through $conf->modules_parts['menus'] when the constant
 *	MAIN_MENU_STANDARD is set to 'fullsidebar_menu.php'. No core file is modified.
 *
 *	Top menu, 'topnb' (used by the theme to size the header) and 'jmobile' keep the
 *	stock eldy behaviour; only the left menu is replaced by a collapsible tree that
 *	lists every module and its submenus.
 */


/**
 *	Class to manage the "full sidebar" menu
 *
 *	@phan-suppress PhanRedefineClass
 */
class MenuManager
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var int<0,1>	0 for internal users, 1 for external users
	 */
	public $type_user;

	/**
	 * @var string		Default target to use for links
	 */
	public $atarget = "";

	/**
	 * @var string Menu name
	 */
	public $name = "fullsidebar";

	/**
	 * @var Menu
	 */
	public $menu;

	/**
	 * @var array<mixed> Deprecated menu entries given by the page to left_menu()
	 */
	public $menu_array;

	/**
	 * @var array<mixed> Deprecated menu entries given by the page to left_menu()
	 */
	public $menu_array_after;

	/**
	 * @var array<mixed> Menu entries of llx_menu, conditions evaluated in the CURRENT context
	 */
	public $tabMenu;

	/**
	 * @var ?array<mixed> Same entries, conditions evaluated with leftmenu='all' (whole
	 *                    tree). Null until loadMenuAll() fills it on demand.
	 */
	public $tabMenuAll = null;

	/**
	 * @var string Main menu of the page being rendered (used to highlight/expand)
	 */
	public $currentmainmenu = '';

	/**
	 * @var string Left menu of the page being rendered
	 */
	public $currentleftmenu = '';

	/**
	 * @var int Incremented to build unique DOM ids
	 */
	private $domcounter = 0;

	/**
	 * @var array<string,bool> Storage keys already given to a node in this tree
	 */
	private $usedkeys = array();

	/**
	 * @var array<string,string> Short keys already printed => full key, see shortKey()
	 */
	private $printedkeys = array();


	/**
	 *  Constructor
	 *
	 *  @param	DoliDB		$db     	Database handler
	 *  @param	int<0,1>	$type_user	Type of user
	 */
	public function __construct($db, $type_user)
	{
		$this->type_user = $type_user;
		$this->db = $db;
	}


	/**
	 * Load this->tabMenu (the wider this->tabMenuAll is loaded on demand, see loadMenuAll())
	 *
	 * Session handling is a verbatim copy of eldy_menu.php so that switching handler
	 * does not change how mainmenu/leftmenu are memorised.
	 *
	 * @param	string	$forcemainmenu		To force mainmenu to load
	 * @param	string	$forceleftmenu		To force leftmenu to load
	 * @return	void
	 */
	public function loadMenu($forcemainmenu = '', $forceleftmenu = '')
	{
		// We save into session the main menu selected
		if (GETPOSTISSET("mainmenu")) {
			$_SESSION["mainmenu"] = GETPOST("mainmenu", 'aZ09');
		}
		if (GETPOSTISSET("idmenu")) {
			$_SESSION["idmenu"] = GETPOSTINT("idmenu");
		}

		// Read now mainmenu and leftmenu that define which menu to show
		if (GETPOSTISSET("mainmenu")) {
			$mainmenu = GETPOST("mainmenu", 'aZ09');
			$_SESSION["mainmenu"] = $mainmenu;
			$_SESSION["leftmenuopened"] = "";
		} else {
			$mainmenu = isset($_SESSION["mainmenu"]) ? $_SESSION["mainmenu"] : '';
		}
		if (!empty($forcemainmenu)) {
			$mainmenu = $forcemainmenu;
		}

		if (GETPOSTISSET("leftmenu")) {
			$leftmenu = GETPOST("leftmenu", 'aZ09');
			$_SESSION["leftmenu"] = $leftmenu;

			if ($_SESSION["leftmenuopened"] == $leftmenu) {	// To collapse
				$_SESSION["leftmenuopened"] = "";
			} else {
				$_SESSION["leftmenuopened"] = $leftmenu;
			}
		} else {
			$leftmenu = isset($_SESSION["leftmenu"]) ? $_SESSION["leftmenu"] : '';
		}
		if (!empty($forceleftmenu)) {
			$leftmenu = $forceleftmenu;
		}

		$this->currentmainmenu = $mainmenu;
		$this->currentleftmenu = $leftmenu;

		require_once DOL_DOCUMENT_ROOT.'/core/class/menubase.class.php';

		// Exactly one pass here, same cost as eldy_menu.php. The second, wider pass that
		// the full tree needs is deliberately NOT done now: loadMenu() is also called by
		// theme/*/style.css.php to compute $nbtopmenuentries, and that stylesheet is
		// render-blocking. Doing the extra pass there delays the whole page CSS, and the
		// browser paints the raw HTML meanwhile - the sidebar shows up full width then
		// snaps into place. See loadMenuAll().
		$tabMenu = array();
		$menuArbo = new Menubase($this->db, 'eldy');
		$menuArbo->menuLoad($mainmenu, $leftmenu, $this->type_user, 'eldy', $tabMenu);
		$this->tabMenu = $tabMenu;
	}


	/**
	 * Load this->tabMenuAll, the menu entries evaluated as if every left menu was open.
	 *
	 * Menubase::menuLoad() evaluates the 'enabled' and 'perms' condition of each llx_menu
	 * row at load time, using $mainmenu/$leftmenu as globals. With leftmenu='all' it
	 * rewrites every "$leftmenu == 'x'" test to true, which is what unlocks the submenus
	 * of the modules we are NOT browsing.
	 *
	 * Called on demand, only when the tree is really rendered. menuLoad() leaves
	 * $mainmenu/$leftmenu set as globals, and by then the page has run and may have set
	 * its own: they are saved and restored, so no page code ever sees 'all'.
	 *
	 * @return	void
	 */
	private function loadMenuAll()
	{
		global $mainmenu, $leftmenu;

		if (is_array($this->tabMenuAll)) {
			return;
		}

		require_once DOL_DOCUMENT_ROOT.'/core/class/menubase.class.php';

		$savedmainmenu = isset($mainmenu) ? $mainmenu : null;
		$savedleftmenu = isset($leftmenu) ? $leftmenu : null;

		$tabMenuAll = array();
		$menuArbo = new Menubase($this->db, 'eldy');
		$menuArbo->menuLoad($this->currentmainmenu, 'all', $this->type_user, 'eldy', $tabMenuAll);
		$this->tabMenuAll = $tabMenuAll;

		$mainmenu = $savedmainmenu;
		$leftmenu = $savedleftmenu;
	}


	/**
	 *  Output menu on screen.
	 *
	 *	@param	'top'|'topnb'|'left'|'leftdropdown'|'jmobile'	$mode		Rendering mode
	 *  @param	?array<string,string>							$moredata	An array with more data to output
	 *  @return int<0,max>											0 or nb of top menu entries if $mode = 'topnb'
	 */
	public function showmenu($mode, $moredata = null)
	{
		global $conf, $langs, $user;

		require_once DOL_DOCUMENT_ROOT.'/core/menus/standard/eldy.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/menu.class.php';

		if ($this->type_user == 1) {
			$conf->global->MAIN_SEARCHFORM_SOCIETE_DISABLED = 1;
			$conf->global->MAIN_SEARCHFORM_CONTACT_DISABLED = 1;
		}

		$this->menu = new Menu();

		if ($mode == 'topnb') {
			// Called by theme/*/style.css.php to size the header. Must stay identical to eldy.
			print_eldy_menu($this->db, $this->atarget, $this->type_user, $this->tabMenu, $this->menu, 1, $mode);
			return $this->menu->getNbOfVisibleMenuEntries();
		}

		// MAIN_MENU_INVERT swaps the horizontal and vertical menus. A tree of every
		// module cannot be laid out horizontally, so the stock rendering is kept there.
		$invert = (bool) getDolGlobalString('MAIN_MENU_INVERT');
		if ($invert) {
			$conf->global->MAIN_SHOW_LOGO = 0;	// As eldy_menu.php does in that mode
		}

		if ($mode == 'top') {
			if (!$invert) {
				$this->printTopMenuHider();
			}
			if ($invert) {
				print_left_eldy_menu($this->db, $this->menu_array, $this->menu_array_after, $this->tabMenu, $this->menu, 0, '', '', $moredata, $this->type_user);
			} else {
				print_eldy_menu($this->db, $this->atarget, $this->type_user, $this->tabMenu, $this->menu, 0, $mode);
			}
		}

		if ($mode == 'left' || $mode == 'leftdropdown') {
			if ($invert) {
				print_eldy_menu($this->db, $this->atarget, $this->type_user, $this->tabMenu, $this->menu, 0, 'left');
			} else {
				$this->printFullTree($moredata);
			}
		}

		if ($mode == 'jmobile') {
			// Legacy webview menu (core/get_menudiv.php). Same tree, stock jmobile markup.
			$this->printJMobileTree();
		}

		unset($this->menu);

		return 0;
	}


	/**
	 * Hide the entries of the horizontal menu when FULLSIDEBAR_HIDE_TOPMENU is on.
	 *
	 * Every module is reachable from the sidebar, so the top entries only duplicate it.
	 * Only the entries go: the bar itself stays, and with it the company logo, the
	 * search box, the user block - and above all the hamburger (li.menuhider), which is
	 * the ONLY way to open the sidebar drawer on a narrow screen. Hiding the whole bar
	 * would lock a phone user out of the menu entirely.
	 *
	 * Printed before the menu markup (top_menu() runs early in the page) so the entries
	 * are never painted, instead of flashing and disappearing as a JS toggle would.
	 *
	 * @return	void
	 */
	private function printTopMenuHider()
	{
		global $conf;

		if (!getDolGlobalInt('FULLSIDEBAR_HIDE_TOPMENU')) {
			return;
		}

		print "\n".'<!-- FullSidebar: top menu entries hidden by FULLSIDEBAR_HIDE_TOPMENU -->'."\n";
		print '<style'.(function_exists('getNonce') ? ' nonce="'.getNonce().'"' : '').'>'."\n";
		print 'ul.tmenu > li:not(.menuhider):not(.tmenuend):not(#mainmenutd_companylogo) { display: none !important; }'."\n";
		// Make the (now empty) tmenu a flex row so the breadcrumb sits next to the
		// burger instead of wrapping below it on phones.
		// NB: the core prints <div class="tmenudiv"><ul class="tmenu">; there is no div.tmenu.
		// eldy only - fixed height: once its entries are hidden, the bar shrinks to the
		// height of the burger and the search pill. md positions its fixed left column at
		// 45px from the top on its own: a taller bar would overlap it.
		$fixedheight = ((string) $conf->theme === 'eldy') ? ' height: 55px !important;' : '';
		print 'header#id-top div.tmenudiv, header#id-top div.tmenu { display: flex !important; align-items: center !important;'.$fixedheight.' }'."\n";
		// The theme reserves a fixed right padding on #tmenu_tooltip for the tools block, whatever
		// its width; the breadcrumb is capped by fullsidebar.js instead (drawer layout excluded).
		print 'html:not(.fsb-drawer) header#id-top div#tmenu_tooltip { padding-right: 0 !important; }'."\n";
		// Promote the global search to a visible pill input in the freed top bar
		// (instead of the loupe dropdown), so the bar is not empty.
		print 'header#id-top #topmenu-global-search-dropdown > a.dropdown-toggle { display: none !important; }'."\n";
		print 'header#id-top #topmenu-global-search-dropdown .dropdown-menu.dropdown-search { position: static !important; display: block !important; float: none !important; border: 0 !important; box-shadow: none !important; background: transparent !important; margin: 0 !important; padding: 0 !important; width: clamp(180px, 20vw, 300px) !important; min-width: 0 !important; max-width: 300px !important; }'."\n";
		print 'header#id-top #topmenu-global-search-dropdown .search-dropdown-header { position: relative !important; padding: 0 !important; }'."\n";
		print 'header#id-top #topmenu-global-search-dropdown .search-dropdown-header::before { content: "\\f002"; font-family: "Font Awesome 5 Free", "FontAwesome"; font-weight: 900; position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--colortext, #9aa4b0); opacity: 0.55; font-size: 13px; pointer-events: none; }'."\n";
		print 'header#id-top #topmenu-global-search-dropdown .dropdown-search-input { width: 100% !important; height: 34px !important; padding: 6px 14px 6px 34px !important; border: 1px solid var(--inputbordercolor, rgba(0,0,0,0.08)) !important; border-radius: 999px !important; background: var(--inputbackgroundcolor, #fff) !important; color: var(--colortext, #2b2b2b) !important; font-size: 13px !important; box-sizing: border-box !important; }'."\n";
		print 'header#id-top #topmenu-global-search-dropdown .dropdown-search-input::placeholder { color: var(--colortext, #9aa4b0) !important; opacity: 0.55; }'."\n";
		print 'header#id-top #topmenu-global-search-dropdown .search-dropdown-body { display: none !important; }'."\n";
		// Results list styled like the native quick-add list: no forced text colour (it
		// used to target every descendant with two ids, which beat the theme's
		// .dropdown-item:hover and erased the coloured icons), no caret before each item,
		// same vertical padding and font size.
		print 'header#id-top #topmenu-global-search-dropdown .search-dropdown-body { padding: 10px 0 !important; }'."\n";
		print 'header#id-top #topmenu-global-search-dropdown .global-search-item { font-size: 1.1em; }'."\n";
		print 'header#id-top #topmenu-global-search-dropdown .global-search-item::before { content: none !important; }'."\n";
		// Explicit hover / keyboard focus, same colours as the theme's .dropdown-item:hover.
		print 'header#id-top #topmenu-global-search-dropdown .global-search-item { cursor: pointer; }'."\n";
		print 'header#id-top #topmenu-global-search-dropdown .global-search-item:hover, header#id-top #topmenu-global-search-dropdown .global-search-item:focus { background: var(--colorbackhmenu1, #3a5a78) !important; color: var(--colortextbackhmenu, #fff) !important; }'."\n";
		// Shown while the form has the focus, AND while the pointer is over the list:
		// Firefox on macOS and Safari do not focus a <button> on click, so the input loses
		// the focus on mousedown, the list vanished before the click was registered, and
		// the core handler (which submits the form to the item's data-target) never ran.
		print 'header#id-top #topmenu-global-search-dropdown .dropdown-search:focus-within .search-dropdown-body, header#id-top #topmenu-global-search-dropdown .dropdown-search .search-dropdown-body:hover { display: block !important; position: absolute !important; top: 100%; left: 0; min-width: 240px !important; background: var(--colorbackbody, #fff) !important; border: 1px solid var(--inputbordercolor, #e0e4ea) !important; border-radius: 8px !important; box-shadow: 0 8px 24px rgba(0,0,0,0.15) !important; z-index: 1050 !important; margin-top: 6px !important; }'."\n";
		// Narrower search pill on phones so it fits next to the burger + breadcrumb.
		print '@media screen and (max-width: 767px) { header#id-top #topmenu-global-search-dropdown .dropdown-menu.dropdown-search { width: 140px !important; min-width: 140px !important; max-width: 140px !important; } }'."\n";
		print '</style>'."\n";
	}


	/* -------------------------------------------------------------------------- */
	/* Tree building                                                              */
	/* -------------------------------------------------------------------------- */

	/**
	 * Build the complete menu tree: one root per top menu entry, its whole left menu below.
	 *
	 * @return array<array<string,mixed>>	Nested nodes (see buildNode())
	 */
	private function buildTree()
	{
		global $user;

		require_once DOL_DOCUMENT_ROOT.'/core/menus/standard/eldy.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/menu.class.php';

		$showunauthorized = getDolGlobalInt('FULLSIDEBAR_SHOW_UNAUTHORIZED');

		// Pay for the wide menu pass here, where the tree is actually built.
		$this->loadMenuAll();

		// Top entries. Mode 'jmobile' is passed so print_eldy_menu() skips the hamburger
		// entry it prepends for the real top bar; $noout=1 means "fill the object only".
		$topmenu = new Menu();
		print_eldy_menu($this->db, $this->atarget, $this->type_user, $this->tabMenu, $topmenu, 1, 'jmobile');

		$tree = array();
		foreach ($topmenu->liste as $top) {
			$mainmenu = (string) (isset($top['mainmenu']) ? $top['mainmenu'] : '');
			if ($mainmenu === '' || $mainmenu === 'xxx') {
				continue;	// 'xxx' is the menu hider pseudo-entry
			}
			$enabled = (int) $top['enabled'];
			if ($enabled === 0 && !$showunauthorized) {
				continue;
			}

			$children = array();
			// $enabled is the value of isVisibleToUserType(): 0 = hidden, 1 = usable,
			// 2 = "shown but greyed", returned when the entry's 'enabled' condition
			// passes but its 'perms' does not (internal user, MAIN_MENU_HIDE_UNAUTHORIZED
			// off). Testing "=== 1" dropped the WHOLE submenu of those modules: the top
			// entry stayed, every child vanished. The core does the opposite - eldy tests
			// the value for truth, so 2 keeps its submenu.
			//
			// Building the children of a greyed parent is safe: each child carries its
			// own 'enabled', and nestFlatList() filters them individually. A child the
			// user may not see is still dropped there - only the ones they ARE allowed
			// to reach come back.
			if ($enabled !== 0) {
				// A menu handler runs on EVERY page: one module whose left menu throws
				// (a broken hook, a dictionary query failing, a missing lang file...) must
				// degrade to "that module has no submenu", never to a white page.
				try {
					$children = $this->buildBranch($mainmenu);
				} catch (\Throwable $e) {
					dol_syslog(__METHOD__.': left menu of "'.$mainmenu.'" failed: '.$e->getMessage(), LOG_ERR);
					$children = array();
				}
			}

			$node = $this->buildNode($top, 0, $mainmenu, 'fsb-'.$mainmenu);
			$node['children'] = $children;
			$node['ismodule'] = true;
			$tree[] = $node;
		}

		// Entries passed by the page itself to left_menu() (deprecated API, but a few
		// modules still use it). They have no top menu, so they become extra roots.
		foreach (array($this->menu_array, $this->menu_array_after) as $extra) {
			if (!is_array($extra) || empty($extra)) {
				continue;
			}
			$flat = $this->flattenExtra($extra);
			foreach ($this->nestFlatList($flat, '', 'fsb-extra') as $node) {
				$tree[] = $node;
			}
		}

		return $tree;
	}

	/**
	 * Build the full left menu of one main menu entry, as a nested tree.
	 *
	 * @param	string	$mainmenu	Main menu code ('companies', 'products', ...)
	 * @return	array<array<string,mixed>>
	 */
	private function buildBranch($mainmenu)
	{
		global $hookmanager;

		require_once DOL_DOCUMENT_ROOT.'/core/class/menu.class.php';

		$this->loadMenuAll();	// No-op when already loaded by buildTree()

		$submenu = new Menu();

		// $forceleftmenu='all' makes print_left_eldy_menu() reset its internal $leftmenu
		// to '', and every hardcoded branch of eldy.lib.php is guarded by
		// "$usemenuhider || empty($leftmenu) || $leftmenu == 'x'" - so all of them are
		// added. Combined with $this->tabMenuAll (conditions evaluated with 'all'), this
		// yields the complete tree of that module.
		print_left_eldy_menu($this->db, null, null, $this->tabMenuAll, $submenu, 1, $mainmenu, 'all', null, $this->type_user);

		$flat = $submenu->liste;

		// print_left_eldy_menu() runs the 'menuLeftMenuItems' hook and the positionfull
		// sort only on its LOCAL copy, which it throws away when $noout=1. Both are
		// replayed here, otherwise third party entries and the declared ordering would
		// be lost. (Consequence: the hook fires twice per main menu - it is expected to
		// be a pure array transformation, as in the core.)
		if (is_object($hookmanager)) {
			$parameters = array('mainmenu' => $mainmenu);
			$hook_items = $flat;
			$reshook = $hookmanager->executeHooks('menuLeftMenuItems', $parameters, $hook_items);
			if (is_numeric($reshook)) {
				if ($reshook == 0 && !empty($hookmanager->resArray)) {
					$flat[] = $hookmanager->resArray;
				} elseif ($reshook == 1) {
					$flat = $hookmanager->resArray;
				}
			}
		}

		$flat = $this->sortOnPositionFull($flat);

		return $this->nestFlatList($flat, $mainmenu, 'fsb-'.$mainmenu);
	}

	/**
	 * Reorder a flat menu list on 'position' without breaking the parent/child order.
	 * Verbatim logic of print_left_eldy_menu().
	 *
	 * @param	array<array<string,mixed>>	$menu_array		Flat list
	 * @return	array<array<string,mixed>>					Sorted list
	 */
	private function sortOnPositionFull($menu_array)
	{
		if (!is_array($menu_array) || empty($menu_array)) {
			return array();
		}

		$counter = 0;
		$currentpositionlevel = 0;
		$currentpositionpath = '';
		foreach ($menu_array as $key => $val) {
			$counter++;
			$level = empty($menu_array[$key]['level']) ? 0 : (int) $menu_array[$key]['level'];
			$position = empty($menu_array[$key]['position']) ? 0 : $menu_array[$key]['position'];
			if ($level <= $currentpositionlevel) {
				$tmparray = explode('_', $currentpositionpath);
				$currentpositionpath = '';
				for ($i = 0; $i < $level; $i++) {
					$currentpositionpath .= (isset($tmparray[$i]) ? $tmparray[$i] : '0').'_';
				}
				$currentpositionpath .= $position.'.'.$counter;
			} else {
				$currentpositionpath .= '_'.$position.'.'.$counter;
			}
			$menu_array[$key]['positionfull'] = $currentpositionpath;
			$currentpositionlevel = $level;
		}

		return dol_sort_array($menu_array, 'positionfull', 'asc', 1, 0, 1);
	}

	/**
	 * Normalise entries coming from the deprecated $menu_array parameter of left_menu().
	 *
	 * @param	array<mixed>	$extra	Raw entries
	 * @return	array<array<string,mixed>>
	 */
	private function flattenExtra($extra)
	{
		$out = array();
		foreach ($extra as $val) {
			if (!is_array($val) || !isset($val['titre'])) {
				continue;
			}
			$out[] = $val + array(
				'url' => '', 'level' => 0, 'enabled' => 1, 'target' => '',
				'mainmenu' => '', 'leftmenu' => '', 'position' => 0, 'prefix' => '',
			);
		}
		return $out;
	}

	/**
	 * Turn a flat list of entries carrying a 'level' into a nested tree.
	 *
	 * A level is never trusted to be exactly parent+1: some menus jump from 1 to 3.
	 * A jump down is therefore clamped to "one level below the previous entry", which
	 * is what the flat renderer of eldy visually suggests.
	 *
	 * @param	array<array<string,mixed>>	$flat		Flat list
	 * @param	string						$mainmenu	Main menu the entries belong to
	 * @param	string						$keyprefix	Prefix for the DOM/storage key
	 * @return	array<array<string,mixed>>				Nested nodes
	 */
	private function nestFlatList($flat, $mainmenu, $keyprefix)
	{
		$showunauthorized = getDolGlobalInt('FULLSIDEBAR_SHOW_UNAUTHORIZED');

		$roots = array();
		$stack = array();	// depth => reference to the node list to append to
		$lastdepth = -1;
		$skipdepth = -1;	// below this depth, entries are children of a hidden parent

		foreach ($flat as $entry) {
			if (!is_array($entry) || !isset($entry['titre'])) {
				continue;
			}

			$level = empty($entry['level']) ? 0 : (int) $entry['level'];
			if ($level < 0) {
				$level = 0;
			}
			if ($level > $lastdepth + 1) {
				$level = $lastdepth + 1;
			}

			if ($skipdepth >= 0) {
				if ($level > $skipdepth) {
					continue;	// child of a hidden entry
				}
				$skipdepth = -1;
			}

			// Entries added by a 'menuLeftMenuItems' hook may omit keys Menu::add() always sets.
			$enabled = isset($entry['enabled']) ? (int) $entry['enabled'] : 1;
			if ($enabled === 0 && !$showunauthorized) {
				$skipdepth = $level;
				$lastdepth = $level;
				continue;
			}

			$parentkey = $keyprefix;
			if ($level > 0 && isset($stack[$level - 1]['key'])) {
				$parentkey = $stack[$level - 1]['key'];
			}

			$node = $this->buildNode($entry, $level, $mainmenu, $parentkey);
			$node['children'] = array();

			if ($level === 0) {
				$roots[] = $node;
				$stack = array(0 => array('key' => $node['key'], 'ref' => &$roots[count($roots) - 1]));
			} else {
				if (!isset($stack[$level - 1])) {
					// No parent at the expected depth: attach at root to avoid losing it.
					$roots[] = $node;
					$stack = array(0 => array('key' => $node['key'], 'ref' => &$roots[count($roots) - 1]));
					$level = 0;
				} else {
					$parent = &$stack[$level - 1]['ref'];
					$parent['children'][] = $node;
					$stack[$level] = array(
						'key' => $node['key'],
						'ref' => &$parent['children'][count($parent['children']) - 1],
					);
					unset($parent);
				}
			}

			// Anything deeper than the entry we just placed is stale.
			foreach (array_keys($stack) as $d) {
				if ($d > $level) {
					unset($stack[$d]);
				}
			}

			$lastdepth = $level;
		}

		return $roots;
	}

	/**
	 * Build one tree node from a Menu::liste entry.
	 *
	 * @param	array<string,mixed>	$entry		Raw entry
	 * @param	int					$level		Depth inside the branch
	 * @param	string				$mainmenu	Main menu the entry belongs to
	 * @param	string				$parentkey	Key of the parent node
	 * @return	array<string,mixed>
	 */
	private function buildNode($entry, $level, $mainmenu, $parentkey)
	{
		global $user, $langs;

		$titre = (string) $entry['titre'];

		$substitarray = array(
			'__LOGIN__' => $user->login,
			'__USER_ID__' => $user->id,
			'__USERID__' => $user->id,	// For backward compatibility
			'__USER_SUPERVISOR_ID__' => $user->fk_user,
		);
		$rawurl = make_substitutions((string) (isset($entry['url']) ? $entry['url'] : ''), $substitarray);

		$entrymainmenu = empty($entry['mainmenu']) ? $mainmenu : (string) $entry['mainmenu'];
		$href = $this->buildHref($rawurl, $entrymainmenu, $mainmenu);

		$this->domcounter++;

		// Storage key of the branch (localStorage, "remember opened branches"). Built on
		// the url rather than the label: labels change with the interface language and
		// carry accents, a url does neither, so what the user opened survives a language
		// switch. Two "List" entries under the same parent still differ by their url.
		// Entries without url (pure group headers) fall back to a slug of the label.
		if ($rawurl !== '' && $rawurl !== '#') {
			$slug = preg_replace('/[?&](mainmenu|leftmenu|idmenu)=[^&]*/', '', str_replace('&amp;', '&', $rawurl));
			$slug = trim(preg_replace('/[^a-z0-9_.\/=&-]+/i', '-', $slug), '-?&/');
		} else {
			$slug = dol_string_nospecial(strtolower(dol_string_nohtmltag($titre)), '-');
			$slug = trim(preg_replace('/-+/', '-', $slug), '-');
		}
		if ($slug === '') {
			$slug = 'e'.$this->domcounter;
		}

		// Two entries may share a url (a group header and its "list" child, a hook adding
		// a duplicate...). The key must stay unique in the tree - and deterministic across
		// page loads, which it is since the menu order is stable.
		$key = $parentkey.'/'.$slug;
		if (isset($this->usedkeys[$key])) {
			$n = 2;
			while (isset($this->usedkeys[$key.'~'.$n])) {
				$n++;
			}
			$key .= '~'.$n;
		}
		$this->usedkeys[$key] = true;

		return array(
			'key' => $key,
			'id' => 'fsbn'.$this->domcounter,
			'titre' => $titre,
			'href' => $href,
			'rawurl' => $rawurl,
			'prefix' => isset($entry['prefix']) ? (string) $entry['prefix'] : '',
			'enabled' => isset($entry['enabled']) ? (int) $entry['enabled'] : 1,
			'target' => empty($entry['target']) ? '' : (string) $entry['target'],
			'mainmenu' => $entrymainmenu,
			'leftmenu' => empty($entry['leftmenu']) ? '' : (string) $entry['leftmenu'],
			'level' => $level,
			'ismodule' => false,
			'children' => array(),
		);
	}

	/**
	 * Turn a menu url into a clickable href.
	 *
	 * Same rules as print_left_eldy_menu(), with one addition: when the entry carries no
	 * mainmenu parameter, the one of its ROOT module is forced. Without it, clicking
	 * "Third parties > List" from the Home page would leave $_SESSION['mainmenu'] on
	 * 'home' - the whole point of this sidebar is to jump across modules.
	 *
	 * The 'leftmenu' / 'mainmenu' tests are deliberately loose (not anchored on '&'),
	 * because a fair number of core menu urls are written with '&amp;' separators.
	 *
	 * @param	string	$rawurl			Url as declared by the menu entry
	 * @param	string	$entrymainmenu	Main menu of the entry
	 * @param	string	$rootmainmenu	Main menu of the root node
	 * @return	string					Href ready for the <a> tag (not yet escaped)
	 */
	private function buildHref($rawurl, $entrymainmenu, $rootmainmenu)
	{
		if ($rawurl === '' || $rawurl === '#') {
			return '';
		}
		if (preg_match("/^(http:\/\/|https:\/\/)/i", $rawurl)) {
			return $rawurl;
		}

		$tmp = explode('?', $rawurl, 2);
		$path = $tmp[0];
		$param = isset($tmp[1]) ? $tmp[1] : '';

		// eldy.lib.php writes its menu urls with a mix of '&' and '&amp;' separators.
		// Leaving an '&amp;' in place means the href gets escaped a second time at print
		// time ('&amp;amp;'), and the browser then reads a parameter literally named
		// 'amp;search_status' - the filter silently does nothing. Normalise to '&' here
		// and let the single escaping done when printing produce a valid href.
		$param = str_replace('&amp;', '&', $param);

		$targetmainmenu = $entrymainmenu !== '' ? $entrymainmenu : $rootmainmenu;
		if (!preg_match('/mainmenu/i', $param) && $targetmainmenu !== '') {
			$param .= ($param ? '&' : '').'mainmenu='.$targetmainmenu;
		}
		if (!preg_match('/leftmenu/i', $param)) {
			$param .= ($param ? '&' : '').'leftmenu=';
		}

		return dol_buildpath($path, 1).($param ? '?'.$param : '');
	}


	/* -------------------------------------------------------------------------- */
	/* Rendering                                                                  */
	/* -------------------------------------------------------------------------- */

	/**
	 * Print the search form / bookmarks blocks then the whole tree.
	 *
	 * @param	?array<string,string>	$moredata	Data given by left_menu()
	 * @return	void
	 */
	private function printFullTree($moredata)
	{
		global $conf, $langs, $user;

		$langs->loadLangs(array('fullsidebar@fullsidebar'));

		// Same blocks, same ids as eldy: the theme styles them and other modules hook on them.
		if (is_array($moredata) && !empty($moredata['searchform'])) {
			print "\n<!-- Begin SearchForm -->\n";
			print '<div id="blockvmenusearch" class="blockvmenusearch">'."\n";
			print $moredata['searchform'];
			print '</div>'."\n";
			print "<!-- End SearchForm -->\n";
		}
		if (is_array($moredata) && !empty($moredata['bookmarks'])) {
			print "\n<!-- Begin Bookmarks -->\n";
			print '<div id="blockvmenubookmarks" class="blockvmenubookmarks">'."\n";
			print $moredata['bookmarks'];
			print '</div>'."\n";
			print "<!-- End Bookmarks -->\n";
		}

		$tbuild = microtime(true);
		$tree = $this->buildTree();
		$tbuild = microtime(true) - $tbuild;
		if (empty($tree)) {
			return;
		}
		$this->markPaths($tree);

		$expandall = getDolGlobalInt('FULLSIDEBAR_EXPAND_ALL');
		$remember = getDolGlobalString('FULLSIDEBAR_REMEMBER_OPENED', '1');

		print "\n".'<!-- Begin FullSidebar tree -->'."\n";
		print '<nav class="fsb'.($expandall ? ' fsb-expandall' : '').'"'
			.' id="fsb"'
			.' data-fsb-remember="'.($remember === '1' ? '1' : '0').'"'
			.' data-fsb-mainmenu="'.dol_escape_htmltag($this->currentmainmenu).'"'
			// Facts the JS must not guess: the url root (to read module names out of
			// window.location on an install served under /htdocs), whether the top menu
			// entries are hidden (breadcrumb only makes sense then), and a translated label.
			.' data-fsb-urlroot="'.dol_escape_htmltag((string) DOL_URL_ROOT).'"'
			// Scopes the browser storage of opened branches: a shared workstation must not
			// restore another user's tree.
			.' data-fsb-user="'.((int) $user->id).'"'
			// The layout rules of fullsidebar.css (sticky column, wider drawer) are written
			// for eldy's layout and scoped on it: md keeps its own fixed column.
			.' data-fsb-theme="'.dol_escape_htmltag(preg_replace('/[^a-z0-9_-]/i', '', (string) $conf->theme)).'"'
			.' data-fsb-click="'.dol_escape_htmltag(getDolGlobalString('FULLSIDEBAR_CLICK_MODE', 'arrow')).'"'
			.' data-fsb-openlabel="'.dol_escape_htmltag($langs->transnoentities("FullSidebarOpenPage")).'"'
			.' data-fsb-hidetop="'.(getDolGlobalInt('FULLSIDEBAR_HIDE_TOPMENU') ? '1' : '0').'"'
			.' data-fsb-breadcrumb="'.dol_escape_htmltag($langs->trans("FullSidebarBreadcrumb")).'"'
			.' aria-label="'.dol_escape_htmltag($langs->trans("FullSidebarNavLabel")).'">'."\n";
		$tprint = microtime(true);
		ob_start();
		$this->printNodes($tree, 0, $expandall);
		$treehtml = ob_get_clean();
		$tprint = microtime(true) - $tprint;
		print $treehtml;
		print '</nav>'."\n";
		// Cost of the tree on this page, for admins only (view source): time spent building
		// it (the wide llx_menu pass + the left menu of every module + the hook replays),
		// time spent printing it, and its size. Enough to judge whether a cache is worth it.
		if (!empty($user->admin)) {
			print '<!-- FullSidebar: '.$this->domcounter.' entries, build '.round($tbuild * 1000, 1).' ms, print '
				.round($tprint * 1000, 1).' ms, '.round(strlen($treehtml) / 1024, 1).' KB -->'."\n";
		}
		// Sentinel: the JS starts as soon as this element exists, i.e. once the WHOLE tree
		// is parsed - starting on the first li (as a MutationObserver on .fsb-item would)
		// restores and measures a half-parsed tree.
		print '<span id="fsb-end" hidden></span>'."\n";
		print '<!-- End FullSidebar tree -->'."\n";
	}

	/**
	 * Short, stable storage key of a node for the data-fsb-key attribute.
	 *
	 * The full key repeats the url path of every ancestor (up to ~200 chars on deep
	 * entries) and was printed on every li: a large share of the page weight. The JS
	 * only needs an opaque identifier that is stable across page loads, so a base36
	 * crc32 of the full key is printed instead. Uniqueness is still guaranteed on the
	 * full key (buildNode()); a crc collision - unlikely on a few hundred entries - is
	 * resolved with a suffix, deterministically since the menu order is stable.
	 *
	 * @param	string	$key	Full key built by buildNode()
	 * @return	string			Short key
	 */
	private function shortKey($key)
	{
		$short = base_convert(sprintf('%u', crc32($key)), 10, 36);
		if (isset($this->printedkeys[$short]) && $this->printedkeys[$short] !== $key) {
			$n = 2;
			while (isset($this->printedkeys[$short.'-'.$n]) && $this->printedkeys[$short.'-'.$n] !== $key) {
				$n++;
			}
			$short .= '-'.$n;
		}
		$this->printedkeys[$short] = $key;

		return $short;
	}

	/**
	 * Recursively print a list of nodes.
	 *
	 * The markup is printed compact - no indentation, no line breaks, no title
	 * attribute repeating the visible label, the expander named by the label it sits
	 * next to (aria-labelledby) instead of a translated copy of it - because this tree
	 * holds every entry of every module and is sent with every page.
	 *
	 * @param	array<array<string,mixed>>	$nodes		Nodes to print
	 * @param	int							$depth		Current depth (0 = modules)
	 * @param	int							$expandall	Expand every branch
	 * @return	void
	 */
	private function printNodes($nodes, $depth, $expandall)
	{
		print '<ul class="fsb-list fsb-list'.$depth.'"'.($depth > 0 ? ' role="group"' : '').'>';

		foreach ($nodes as $node) {
			$haschildren = !empty($node['children']);
			$iscurrent = $this->isCurrentBranch($node);
			$isactive = !empty($node['active']);
			// Only what leads to the page being displayed is expanded (plus the module
			// itself). Expanding the whole branch of the current module - what the flat
			// eldy menu does - drowns the tree: every sibling group ends up open.
			$open = ($expandall || $iscurrent || !empty($node['onpath'])) && $haschildren;

			$classes = array('fsb-item', 'fsb-d'.$depth);
			if ($haschildren) {
				$classes[] = 'fsb-has-children';
			}
			if ($open) {
				$classes[] = 'fsb-open';
			}
			if ($iscurrent) {
				$classes[] = 'fsb-current';
			}
			if ($isactive) {
				$classes[] = 'fsb-active';
			}
			if ((int) $node['enabled'] !== 1) {
				$classes[] = 'fsb-disabled';
			}

			print '<li class="'.implode(' ', $classes).'" data-fsb-key="'.$this->shortKey($node['key']).'"'
				// Module code on the roots only: the breadcrumb looks the Home module up by it.
				.($depth === 0 ? ' data-fsb-mm="'.dol_escape_htmltag($node['mainmenu']).'"' : '')
				.'><span class="fsb-row">';

			// Icon + label, as a link when the entry is reachable. The label carries an id
			// only when an expander has to reference it.
			$prefix = $this->renderPrefix($node, $depth);
			$label = ($prefix === '' ? '<span class="fsb-ic fsb-ic-dot"></span>' : '<span class="fsb-ic">'.$prefix.'</span>')
				.'<span class="fsb-tx"'.($haschildren ? ' id="'.$node['id'].'t"' : '').'>'.ucfirst($node['titre']).'</span>';

			if ((int) $node['enabled'] === 1 && $node['href'] !== '') {
				print '<a class="fsb-link" href="'.dol_escape_htmltag($node['href']).'"'
					.($node['target'] ? ' target="'.dol_escape_htmltag($node['target']).'"' : '')
					.($isactive ? ' aria-current="page"' : '')
					.'>'.$label.'</a>';
			} else {
				print '<span class="fsb-link fsb-nolink">'.$label.'</span>';
			}

			// Expander: named by the label (aria-labelledby), its state given by
			// aria-expanded - the standard disclosure pattern.
			if ($haschildren) {
				print '<button type="button" class="fsb-tg" aria-expanded="'.($open ? 'true' : 'false').'"'
					.' aria-controls="'.$node['id'].'" aria-labelledby="'.$node['id'].'t">'
					.'</button>';
			}

			print '</span>';

			if ($haschildren) {
				print '<div class="fsb-sub" id="'.$node['id'].'"'.($open ? '' : ' hidden').'>';
				$this->printNodes($node['children'], $depth + 1, $expandall);
				print '</div>';
			}

			print '</li>';
		}

		print '</ul>';
	}

	/**
	 * Render the FontAwesome prefix of an entry, or '' when it has none (the spacer
	 * keeping labels aligned is then drawn by CSS, see .fsb-ic-dot).
	 *
	 * @param	array<string,mixed>	$node	Node
	 * @param	int					$depth	Depth
	 * @return	string						HTML
	 */
	private function renderPrefix($node, $depth)
	{
		$prefix = $node['prefix'];
		if (!empty($prefix)) {
			$reg = array();
			if (preg_match('/^(fa[rsb]? )?fa-/', $prefix, $reg)) {
				return '<span class="'.(empty($reg[1]) ? 'fa ' : '').$prefix.'"></span>';
			}
			// Menu prefixes built by the core carry an empty style="" (img_picto()).
			return str_replace(' style=""', '', $prefix);
		}

		return '';
	}

	/**
	 * Flag every node that leads to the page being displayed.
	 *
	 * Sets 'active' (this very entry is the page) and 'onpath' (this entry, or one of
	 * its descendants, is the page) so printNodes() expands that path and nothing else.
	 *
	 * A node also counts as on the path when its 'leftmenu' matches the one of the
	 * request: on a card page (/compta/facture/card.php?id=12) no menu url matches, but
	 * the session still carries leftmenu=customers_bills, which is enough to open the
	 * right group.
	 *
	 * @param	array<array<string,mixed>>	$nodes	Nodes, modified in place
	 * @return	bool								True when the subtree holds the page
	 */
	private function markPaths(&$nodes)
	{
		$found = false;

		foreach ($nodes as &$node) {
			$childhit = false;
			if (!empty($node['children'])) {
				$childhit = $this->markPaths($node['children']);
			}

			$node['active'] = $this->isActiveEntry($node);

			$onpath = ($node['active'] || $childhit);
			if (!$onpath && $node['leftmenu'] !== '' && $node['leftmenu'] === $this->currentleftmenu
				&& !empty($node['mainmenu']) && $node['mainmenu'] === $this->currentmainmenu) {
				$onpath = true;
			}

			$node['onpath'] = $onpath;
			if ($onpath) {
				$found = true;
			}
		}
		unset($node);

		return $found;
	}

	/**
	 * Is this node part of the module currently browsed?
	 *
	 * @param	array<string,mixed>	$node	Node
	 * @return	bool
	 */
	private function isCurrentBranch($node)
	{
		if ($this->currentmainmenu === '') {
			return false;
		}
		if (!empty($node['ismodule'])) {
			return $node['mainmenu'] === $this->currentmainmenu;
		}

		return false;
	}

	/**
	 * Is this node the page currently displayed?
	 *
	 * Matches on the script path, then requires every parameter of the menu url to be
	 * present with the same value in the request - so "/societe/list.php?type=c" only
	 * lights up on the customer list, not on the whole third parties list.
	 *
	 * @param	array<string,mixed>	$node	Node
	 * @return	bool
	 */
	private function isActiveEntry($node)
	{
		if ($node['rawurl'] === '' || preg_match("/^(http:\/\/|https:\/\/)/i", $node['rawurl'])) {
			return false;
		}

		$self = empty($_SERVER['PHP_SELF']) ? '' : $_SERVER['PHP_SELF'];
		if ($self === '') {
			return false;
		}

		$tmp = explode('?', $node['rawurl'], 2);
		$path = dol_buildpath($tmp[0], 1);
		if ($path === '' || $path !== $self) {
			return false;
		}

		if (!isset($tmp[1]) || $tmp[1] === '') {
			return true;
		}

		$params = array();
		parse_str(str_replace('&amp;', '&', $tmp[1]), $params);
		foreach ($params as $k => $v) {
			if (in_array($k, array('mainmenu', 'leftmenu', 'idmenu'), true)) {
				continue;
			}
			if (!is_scalar($v) || $v === '') {
				continue;
			}
			// A request parameter may be an array (search_options[]): never a match then.
			if (!isset($_GET[$k]) || !is_scalar($_GET[$k]) || (string) $_GET[$k] !== (string) $v) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Print the tree with the legacy jmobile markup (ul.ulmenu / li.lilevelN).
	 *
	 * @return	void
	 */
	private function printJMobileTree()
	{
		$tree = $this->buildTree();

		print '<!-- Generate menu list from menu handler '.$this->name.' -->'."\n";
		print '<ul class="ulmenu ullevel0" data-inset="true">'."\n";
		foreach ($tree as $node) {
			print '<li class="lilevel0">';
			if ((int) $node['enabled'] === 1 && $node['href'] !== '') {
				print '<a class="alilevel0" href="'.dol_escape_htmltag($node['href']).'">';
				print $this->renderPrefix($node, 0).ucfirst($node['titre']);
				print '</a>'."\n";
			} else {
				print '<span class="spanlilevel0 vsmenudisabled">'.$this->renderPrefix($node, 0).ucfirst($node['titre']).'</span>';
			}
			if (!empty($node['children'])) {
				$this->printJMobileNodes($node['children'], 1);
			}
			print '</li>'."\n";
		}
		print '</ul>'."\n";
	}

	/**
	 * Recursive part of printJMobileTree().
	 *
	 * @param	array<array<string,mixed>>	$nodes	Nodes
	 * @param	int							$level	jmobile level (1 = first submenu)
	 * @return	void
	 */
	private function printJMobileNodes($nodes, $level)
	{
		print '<ul class="ullevel'.$level.'">'."\n";
		foreach ($nodes as $node) {
			$disabled = ((int) $node['enabled'] === 1) ? '' : ' vsmenudisabled';
			print '<li class="lilevel'.$level.$disabled.'">';
			if ((int) $node['enabled'] === 1 && $node['href'] !== '') {
				print '<a href="'.dol_escape_htmltag($node['href']).'">'.ucfirst($node['titre']).'</a>';
			} else {
				print '<span class="vsmenudisabled">'.ucfirst($node['titre']).'</span>';
			}
			if (!empty($node['children'])) {
				$this->printJMobileNodes($node['children'], $level + 1);
			}
			print '</li>'."\n";
		}
		print '</ul>'."\n";
	}
}
