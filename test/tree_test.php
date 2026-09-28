<?php
/* Copyright (C) 2026 TH Investissements / Matelas No Stress - GPL v3+ */

/**
 * \file        custom/fullsidebar/test/tree_test.php
 * \ingroup     fullsidebar
 * \brief       Standalone checks of the pure-array part of the menu handler
 *
 * Run from the command line:   php htdocs/custom/fullsidebar/test/tree_test.php
 *
 * The Dolibarr helpers the tested methods rely on are stubbed with the minimum
 * behaviour, so this runs without a database or a configured install. It covers the
 * tree building (levels -> nesting, hidden parents, key uniqueness), href building
 * (mainmenu forcing, '&amp;' normalisation) and the expansion rule (active path only).
 * It does NOT exercise the Dolibarr menu API itself - only a real install does.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('CLI only');
}

define('DOL_DOCUMENT_ROOT', '/nonexistent');

$GLOBALS['__consts'] = array();
function getDolGlobalInt($k, $d = 0)
{
	return isset($GLOBALS['__consts'][$k]) ? (int) $GLOBALS['__consts'][$k] : $d;
}
function getDolGlobalString($k, $d = '')
{
	return isset($GLOBALS['__consts'][$k]) ? (string) $GLOBALS['__consts'][$k] : $d;
}
function make_substitutions($s, $a)
{
	return strtr((string) $s, $a);
}
function dol_buildpath($p, $t = 0)
{
	return '/dolibarr'.$p;
}
function dol_string_nospecial($s, $new = '_')
{
	return preg_replace('/[^a-z0-9]/i', $new, (string) $s);
}
function dol_string_nohtmltag($s, $rm = 1)
{
	return strip_tags((string) $s);
}
function dol_escape_htmltag($s)
{
	return htmlspecialchars((string) $s, ENT_QUOTES);
}
function dolPrintHTMLForAttribute($s)
{
	return htmlspecialchars((string) $s, ENT_QUOTES);
}
function dol_sort_array(&$a, $i, $o = 'asc', $n = 0, $c = 0, $k = 0)
{
	return $a;
}

class FakeUser
{
	public $login = 'demo';
	public $id = 1;
	public $fk_user = 0;
}
$user = new FakeUser();
$langs = null;

require_once __DIR__.'/../core/menus/standard/fullsidebar_menu.php';

/**
 * Call a private method.
 *
 * @param object $obj    Instance
 * @param string $method Method name
 * @param array  $args   Arguments
 * @return mixed
 */
function callPrivate($obj, $method, $args)
{
	$r = new ReflectionMethod(get_class($obj), $method);
	$r->setAccessible(true);
	return $r->invokeArgs($obj, $args);
}

/**
 * Render a tree as an indented text outline, for comparison.
 *
 * @param array $nodes Nodes
 * @param int   $d     Depth
 * @return string
 */
function outline($nodes, $d = 0)
{
	$out = '';
	foreach ($nodes as $n) {
		$out .= str_repeat('  ', $d).$n['titre']."\n";
		if (!empty($n['children'])) {
			$out .= outline($n['children'], $d + 1);
		}
	}
	return $out;
}

/**
 * Build a flat menu entry.
 *
 * @param string $titre   Label
 * @param int    $level   Level
 * @param int    $enabled Enabled flag
 * @return array
 */
function e($titre, $level, $enabled = 1)
{
	return array(
		'url' => '/x/list.php', 'titre' => $titre, 'level' => $level, 'enabled' => $enabled,
		'target' => '', 'mainmenu' => 'companies', 'leftmenu' => '', 'position' => 0, 'prefix' => '',
	);
}

/**
 * Build a flat menu entry with an explicit url.
 *
 * @param string $titre    Label
 * @param int    $level    Level
 * @param string $url      Url
 * @param string $leftmenu Left menu code
 * @return array
 */
function u($titre, $level, $url, $leftmenu = '')
{
	return array(
		'url' => $url, 'titre' => $titre, 'level' => $level, 'enabled' => 1, 'target' => '',
		'mainmenu' => 'billing', 'leftmenu' => $leftmenu, 'position' => 0, 'prefix' => '',
	);
}

$mm = new MenuManager(null, 0);
$fails = 0;

/**
 * Assert two strings are equal.
 *
 * @param string $name     Test name
 * @param string $expected Expected
 * @param string $got      Got
 * @return void
 */
function check($name, $expected, $got)
{
	global $fails;
	if (trim($expected) === trim($got)) {
		echo "OK   $name\n";
	} else {
		$fails++;
		echo "FAIL $name\n--- expected ---\n$expected\n--- got ---\n$got\n";
	}
}

// 1. Real shape of the "Third parties" left menu of eldy.
$flat = array(
	e('ThirdParty', 0), e('NewThirdParty', 1), e('List', 1),
	e('Prospects', 2), e('NewProspect', 3),
	e('Customers', 2), e('NewCustomer', 3),
	e('Contacts', 0), e('NewContact', 1), e('List', 1), e('Prospects', 2),
);
check('nested third parties', "ThirdParty\n  NewThirdParty\n  List\n    Prospects\n      NewProspect\n    Customers\n      NewCustomer\nContacts\n  NewContact\n  List\n    Prospects\n",
	outline(callPrivate($mm, 'nestFlatList', array($flat, 'companies', 'fsb-companies'))));

// 2. A level jump down (1 -> 3) must be clamped, never dropped.
$flat = array(e('A', 0), e('B', 1), e('C', 3), e('D', 1));
check('level jump clamped', "A\n  B\n    C\n  D\n",
	outline(callPrivate($mm, 'nestFlatList', array($flat, 'x', 'fsb-x'))));

// 3. A list that starts at level 1 with no parent must not lose the entry.
$flat = array(e('Orphan', 1), e('Root', 0), e('Child', 1));
check('orphan kept', "Orphan\nRoot\n  Child\n",
	outline(callPrivate($mm, 'nestFlatList', array($flat, 'x', 'fsb-x'))));

// 4. A disabled parent hides its whole subtree (default config).
$flat = array(e('Visible', 0), e('Hidden', 1, 0), e('HiddenChild', 2), e('NextSibling', 1));
check('disabled subtree hidden', "Visible\n  NextSibling\n",
	outline(callPrivate($mm, 'nestFlatList', array($flat, 'x', 'fsb-x'))));

// 5. Same input with FULLSIDEBAR_SHOW_UNAUTHORIZED keeps everything.
$GLOBALS['__consts']['FULLSIDEBAR_SHOW_UNAUTHORIZED'] = 1;
check('disabled subtree shown when asked', "Visible\n  Hidden\n    HiddenChild\n  NextSibling\n",
	outline(callPrivate($mm, 'nestFlatList', array($flat, 'x', 'fsb-x'))));
$GLOBALS['__consts']['FULLSIDEBAR_SHOW_UNAUTHORIZED'] = 0;

// 5b. enabled = 2 is NOT "disabled". isVisibleToUserType() returns it when the
// entry's 'enabled' condition passes but its 'perms' does not: the core shows the
// entry greyed and keeps its subtree (eldy tests the value for truth, so 2 counts).
// Reading it as "off" cost a real regression - buildTree() tested "=== 1" and
// dropped the WHOLE submenu of every module a user lacked the top permission for:
// the module stayed in the sidebar, all its links vanished. The children carry
// their own 'enabled' and are filtered individually, which is what makes keeping
// them safe.
$flat = array(e('Greyed', 0, 2), e('StillThere', 1), e('AlsoThere', 1));
check('greyed entry (enabled=2) keeps its children', "Greyed\n  StillThere\n  AlsoThere\n",
	outline(callPrivate($mm, 'nestFlatList', array($flat, 'x', 'fsb-x'))));

// 6. Keys must be unique per branch so localStorage does not mix two "List" entries.
$flat = array(e('ThirdParty', 0), e('List', 1), e('Contacts', 0), e('List', 1));
$tree = callPrivate($mm, 'nestFlatList', array($flat, 'companies', 'fsb-companies'));
$k1 = $tree[0]['children'][0]['key'];
$k2 = $tree[1]['children'][0]['key'];
check('keys distinguish identical labels', 'different', ($k1 === $k2 ? 'same: '.$k1 : 'different'));

// 7. buildHref forces the root mainmenu so cross-module links switch context.
$href = callPrivate($mm, 'buildHref', array('/societe/list.php?leftmenu=thirdparties', '', 'companies'));
check('href forces mainmenu', '/dolibarr/societe/list.php?leftmenu=thirdparties&mainmenu=companies', $href);

// eldy writes some urls with '&amp;'. They must be normalised, otherwise printing
// escapes them a second time into '&amp;amp;' and the browser reads a parameter
// literally named 'amp;search_status' - the filter would silently do nothing.
$href = callPrivate($mm, 'buildHref', array('/compta/facture/list.php?leftmenu=customers_bills_draft&amp;search_status=0', '', 'billing'));
check('href normalises &amp; separators', '/dolibarr/compta/facture/list.php?leftmenu=customers_bills_draft&search_status=0&mainmenu=billing', $href);
check('href escapes to a single &amp;', 'no double escaping', (strpos(dol_escape_htmltag($href), '&amp;amp;') === false ? 'no double escaping' : 'DOUBLE: '.dol_escape_htmltag($href)));

$href = callPrivate($mm, 'buildHref', array('https://example.org/x', '', 'companies'));
check('href leaves external url alone', 'https://example.org/x', $href);

// 10. Only the path to the displayed page is expanded, not the whole module branch.
//     Each entry gets its own url so exactly one of them can be the current page.
$flat = array(
	u('Factures clients', 0, '/compta/facture/index.php'),
	u('Nouvelle facture', 1, '/compta/facture/card.php'),
	u('Liste', 1, '/compta/facture/list.php'),
	u('Brouillon', 2, '/compta/facture/list.php?search_status=0'),
	u('Factures fournisseur', 0, '/fourn/facture/index.php'),
	u('Liste', 1, '/fourn/facture/list.php'),
	u('Brouillon', 2, '/fourn/facture/list.php?search_status=0'),
);
$tree = callPrivate($mm, 'nestFlatList', array($flat, 'billing', 'fsb-billing'));

// Browser is on the customer invoice list.
$mm->currentleftmenu = '';
$_SERVER['PHP_SELF'] = '/dolibarr/compta/facture/list.php';
$_GET = array();

$r = new ReflectionMethod('MenuManager', 'markPaths');
$r->setAccessible(true);
$args = array(&$tree);
$r->invokeArgs($mm, $args);

$open = array();
$walk = function ($nodes) use (&$walk, &$open) {
	foreach ($nodes as $n) {
		if (!empty($n['onpath'])) {
			$open[] = $n['titre'];
		}
		if (!empty($n['children'])) {
			$walk($n['children']);
		}
	}
};
$walk($tree);
check('only the active path is expanded', 'Factures clients, Liste', implode(', ', $open));

// 11. The supplier "Brouillon" url differs only by a query parameter: it must NOT match.
$actives = array();
$walk2 = function ($nodes) use (&$walk2, &$actives) {
	foreach ($nodes as $n) {
		if (!empty($n['active'])) {
			$actives[] = $n['titre'];
		}
		if (!empty($n['children'])) {
			$walk2($n['children']);
		}
	}
};
$walk2($tree);
check('exactly one active entry', 'Liste', implode(', ', $actives));

// 12. On a card page no url matches, but the session leftmenu still opens the group.
$flat = array(
	u('Factures clients', 0, '/compta/facture/index.php', 'customers_bills'),
	u('Liste', 1, '/compta/facture/list.php', 'customers_bills'),
	u('Factures fournisseur', 0, '/fourn/facture/index.php', 'suppliers_bills'),
);
$tree = callPrivate($mm, 'nestFlatList', array($flat, 'billing', 'fsb-billing'));
// Since 1.4.0 the leftmenu fallback also requires the entry to belong to the module
// being browsed (a leftmenu code such as 'setup' exists in several modules).
$mm->currentmainmenu = 'billing';
$mm->currentleftmenu = 'customers_bills';
$_SERVER['PHP_SELF'] = '/dolibarr/compta/facture/card.php';
$open = array();
$args = array(&$tree);
$r->invokeArgs($mm, $args);
$walk($tree);
// Both entries of the group carry leftmenu=customers_bills in the real eldy data
// (see the hrefs of /compta/facture/list.php), so the whole group opens - and only it.
check('card page falls back on session leftmenu', 'Factures clients, Liste', implode(', ', $open));

// 12b. Same leftmenu code seen from another module must not open the group.
$mm->currentmainmenu = 'commercial';
$open = array();
$args = array(&$tree);
$r->invokeArgs($mm, $args);
$walk($tree);
check('leftmenu fallback is scoped to the current module', '', implode(', ', $open));
$mm->currentmainmenu = '';

// 13. Same url twice under one parent: keys must still differ (and stay deterministic).
$mm2 = new MenuManager(null, 0);
$flat = array(u('Group', 0, '/x/index.php'), u('A', 1, '/x/list.php'), u('B', 1, '/x/list.php'));
$tree = callPrivate($mm2, 'nestFlatList', array($flat, 'x', 'fsb-x'));
$ka = $tree[0]['children'][0]['key'];
$kb = $tree[0]['children'][1]['key'];
check('duplicate urls get distinct keys', 'distinct', ($ka !== $kb ? 'distinct' : 'same: '.$ka));
check('storage key is url based, not label based', 'fsb-x/x/index.php/x/list.php', $ka);

// 14. An array request parameter never matches, and never raises a notice.
$mm3 = new MenuManager(null, 0);
$n = callPrivate($mm3, 'buildNode', array(u('L', 1, '/x/list.php?type=c'), 1, 'x', 'fsb-x'));
$_SERVER['PHP_SELF'] = '/dolibarr/x/list.php';
$_GET = array('type' => array('c'));
$r2 = new ReflectionMethod('MenuManager', 'isActiveEntry');
$r2->setAccessible(true);
check('array GET parameter is not a match', 'false', $r2->invoke($mm3, $n) ? 'true' : 'false');
$_GET = array('type' => 'c');
check('scalar GET parameter matches', 'true', $r2->invoke($mm3, $n) ? 'true' : 'false');
$_GET = array();

// 15. Compact markup: short keys unique and stable, every expander labelled by an
//     existing label, no dot span printed (drawn by CSS).
$mm4 = new MenuManager(null, 0);
$flat = array(u('G', 0, '/x/index.php'), u('A', 1, '/x/list.php'), u('A2', 2, '/x/list.php?s=1'), u('B', 1, '/x/list.php'));
$tree = callPrivate($mm4, 'nestFlatList', array($flat, 'x', 'fsb-x'));
ob_start();
callPrivate($mm4, 'printNodes', array($tree, 0, 0));
$html = ob_get_clean();
preg_match_all('/data-fsb-key="([^"]+)"/', $html, $mk);
check('short keys are unique', count($mk[1]), count(array_unique($mk[1])));
$mm5 = new MenuManager(null, 0);
$tree5 = callPrivate($mm5, 'nestFlatList', array($flat, 'x', 'fsb-x'));
ob_start();
callPrivate($mm5, 'printNodes', array($tree5, 0, 0));
preg_match_all('/data-fsb-key="([^"]+)"/', ob_get_clean(), $mk5);
check('short keys are stable across page loads', implode(',', $mk[1]), implode(',', $mk5[1]));
preg_match_all('/aria-labelledby="([^"]+)"/', $html, $ml);
$missing = array();
foreach ($ml[1] as $id) {
	if (strpos($html, ' id="'.$id.'"') === false) {
		$missing[] = $id;
	}
}
check('every expander points to an existing label', '', implode(',', $missing));
check('no dot span printed', 0, substr_count($html, 'fsb-dot'));

echo $fails ? "\n$fails test(s) failed\n" : "\nAll tests passed\n";
exit($fails ? 1 : 0);
