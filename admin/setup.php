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
 * \file        custom/fullsidebar/admin/setup.php
 * \ingroup     fullsidebar
 * \brief       Setup page of the FullSidebar module
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array("admin", "fullsidebar@fullsidebar"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

// The CSRF block of main.inc.php only runs when MAIN_SECURITY_CSRF_WITH_TOKEN is on
// (off by default), so the token is compared here as well.
if ($action === 'update') {
	$posttoken = GETPOST('token', 'alphanohtml');
	$validtokens = array_filter(array(currentToken(), newToken()));
	if (GETPOST('errorcode', 'alpha') === 'InvalidToken'
		|| empty($posttoken)
		|| !in_array($posttoken, $validtokens, true)) {
		accessforbidden($langs->trans("ErrorBadValueForToken"), 0, 0);
	}
}


/*
 * Actions
 */

if ($action === 'update') {
	$error = 0;

	// Menu handler. MAIN_MENU_STANDARD is a core constant, never stored as '' -
	// it always holds the file name of a handler.
	$usefullsidebar = GETPOSTINT('FULLSIDEBAR_ENABLED');
	if ($usefullsidebar) {
		if (getDolGlobalString('MAIN_MENU_STANDARD') !== 'fullsidebar_menu.php') {
			dolibarr_set_const($db, 'FULLSIDEBAR_PREVIOUS_MENU', getDolGlobalString('MAIN_MENU_STANDARD', 'eldy_menu.php'), 'chaine', 0, '', $conf->entity);
		}
		$res = dolibarr_set_const($db, 'MAIN_MENU_STANDARD', 'fullsidebar_menu.php', 'chaine', 0, '', $conf->entity);
	} else {
		$previous = getDolGlobalString('FULLSIDEBAR_PREVIOUS_MENU', 'eldy_menu.php');
		if (empty($previous) || $previous === 'fullsidebar_menu.php') {
			$previous = 'eldy_menu.php';
		}
		$res = dolibarr_set_const($db, 'MAIN_MENU_STANDARD', $previous, 'chaine', 0, '', $conf->entity);
	}
	if ($res <= 0) {
		$error++;
	}

	// Own options. Always written as an explicit '0'/'1': dolibarr_set_const() DELETES
	// the row when given '', and insert_const() would then silently restore the
	// descriptor default at the next disable/enable cycle.
	$booleans = array(
		'FULLSIDEBAR_SHOW_UNAUTHORIZED',
		'FULLSIDEBAR_EXPAND_ALL',
		'FULLSIDEBAR_REMEMBER_OPENED',
		'FULLSIDEBAR_HIDE_TOPMENU',
	);
	foreach ($booleans as $constname) {
		$value = GETPOSTINT($constname) ? '1' : '0';
		if (dolibarr_set_const($db, $constname, $value, 'chaine', 0, '', $conf->entity) <= 0) {
			$error++;
		}
	}

	$clickmode = GETPOST('FULLSIDEBAR_CLICK_MODE', 'aZ09');
	if (!in_array($clickmode, array('arrow', 'title', 'dblclick'), true)) {
		$clickmode = 'arrow';
	}
	if (dolibarr_set_const($db, 'FULLSIDEBAR_CLICK_MODE', $clickmode, 'chaine', 0, '', $conf->entity) <= 0) {
		$error++;
	}

	// Core constants filling the top bar. They belong to Dolibarr (Home > Setup >
	// Display), NOT to this module: they are deliberately absent from $this->const, so
	// insert_const() can never resurrect a value of ours on a disable/enable cycle, and
	// remove() leaves them alone. They are only mirrored here to save a trip to another
	// setup page once the top menu entries are hidden.
	$coretools = array(
		'MAIN_USE_TOP_MENU_SEARCH_DROPDOWN',
		'MAIN_USE_TOP_MENU_QUICKADD_DROPDOWN',
		'MAIN_USE_TOP_MENU_IMPORT_FILE',
	);
	foreach ($coretools as $constname) {
		$current = getDolGlobalString($constname);
		if (GETPOSTINT($constname)) {
			// MAIN_USE_TOP_MENU_IMPORT_FILE may hold a custom upload url instead of 1
			// (main.inc.php tests is_numeric on it). Never overwrite such a value.
			$value = ($current !== '' && !is_numeric($current)) ? $current : '1';
		} else {
			$value = '0';
		}
		if (dolibarr_set_const($db, $constname, $value, 'chaine', 0, '', $conf->entity) <= 0) {
			$error++;
		}
	}

	if ($error) {
		setEventMessages($langs->trans("Error"), null, 'errors');
	} else {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}

	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = $langs->trans("FullSidebarSetup");

llxHeader('', $title, $help_url);

print load_fiche_titre($title, '', 'title_setup');

$currenthandler = getDolGlobalString('MAIN_MENU_STANDARD', 'eldy_menu.php');
$enabled = ($currenthandler === 'fullsidebar_menu.php') ? 1 : 0;

// The handler is loaded through $conf->modules_parts['menus'], which is built from
// llx_const at module activation time: warn when the module is on but the part is not
// registered, because the sidebar would silently fall back to eldy.
$menuparts = empty($conf->modules_parts['menus']) ? array() : (array) $conf->modules_parts['menus'];
if (!isset($menuparts['fullsidebar'])) {
	print info_admin($langs->trans("FullSidebarWarnNotRegistered"), 0, 0, 'warning', '', '', 'warning');
}

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

// --- Sidebar -----------------------------------------------------------------

print load_fiche_titre($langs->trans("FullSidebarSidebarTitle"), '', '');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td class="center" width="120">'.$langs->trans("Value").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("FullSidebarEnabled");
print '<br><span class="opacitymedium small">'.$langs->trans("FullSidebarEnabledHelp", $currenthandler).'</span>';
print '</td>';
print '<td class="center">'.$form->selectyesno('FULLSIDEBAR_ENABLED', $enabled, 1).'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("FullSidebarHideTopMenu");
print '<br><span class="opacitymedium small">'.$langs->trans("FullSidebarHideTopMenuHelp").'</span>';
print '</td>';
print '<td class="center">'.$form->selectyesno('FULLSIDEBAR_HIDE_TOPMENU', getDolGlobalInt('FULLSIDEBAR_HIDE_TOPMENU'), 1).'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("FullSidebarClickMode");
print '<br><span class="opacitymedium small">'.$langs->trans("FullSidebarClickModeHelp").'</span>';
print '</td>';
print '<td class="center">'.$form->selectarray('FULLSIDEBAR_CLICK_MODE', array(
	'arrow' => $langs->trans("FullSidebarClickArrow"),
	'title' => $langs->trans("FullSidebarClickTitle"),
	'dblclick' => $langs->trans("FullSidebarClickDbl"),
), getDolGlobalString('FULLSIDEBAR_CLICK_MODE', 'arrow'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("FullSidebarExpandAll");
print '<br><span class="opacitymedium small">'.$langs->trans("FullSidebarExpandAllHelp").'</span>';
print '</td>';
print '<td class="center">'.$form->selectyesno('FULLSIDEBAR_EXPAND_ALL', getDolGlobalInt('FULLSIDEBAR_EXPAND_ALL'), 1).'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("FullSidebarRememberOpened");
print '<br><span class="opacitymedium small">'.$langs->trans("FullSidebarRememberOpenedHelp").'</span>';
print '</td>';
print '<td class="center">'.$form->selectyesno('FULLSIDEBAR_REMEMBER_OPENED', getDolGlobalInt('FULLSIDEBAR_REMEMBER_OPENED'), 1).'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("FullSidebarShowUnauthorized");
print '<br><span class="opacitymedium small">'.$langs->trans("FullSidebarShowUnauthorizedHelp").'</span>';
print '</td>';
print '<td class="center">'.$form->selectyesno('FULLSIDEBAR_SHOW_UNAUTHORIZED', getDolGlobalInt('FULLSIDEBAR_SHOW_UNAUTHORIZED'), 1).'</td>';
print '</tr>';

print '</table>';
print '</div>';

// --- Top bar -----------------------------------------------------------------
// Core options, shown here because hiding the top menu entries empties that bar.

print '<br>';
print load_fiche_titre($langs->trans("FullSidebarTopBarTitle"), '', '');
print '<span class="opacitymedium small">'.$langs->trans("FullSidebarTopBarIntro").'</span><br><br>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td class="center" width="120">'.$langs->trans("Value").'</td>';
print '</tr>';

$importfile = getDolGlobalString('MAIN_USE_TOP_MENU_IMPORT_FILE');

$coretools = array(
	'MAIN_USE_TOP_MENU_SEARCH_DROPDOWN' => 'FullSidebarTopSearch',
	'MAIN_USE_TOP_MENU_QUICKADD_DROPDOWN' => 'FullSidebarTopQuickAdd',
	'MAIN_USE_TOP_MENU_IMPORT_FILE' => 'FullSidebarTopImportFile',
);
foreach ($coretools as $constname => $labelkey) {
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($labelkey);
	print '<br><span class="opacitymedium small">'.$langs->trans($labelkey.'Help').'</span>';
	if ($constname === 'MAIN_USE_TOP_MENU_IMPORT_FILE' && $importfile !== '' && !is_numeric($importfile)) {
		print '<br><span class="opacitymedium small">'.$langs->trans("FullSidebarTopImportFileCustom", $importfile).'</span>';
	}
	print '</td>';
	// getDolGlobalString is used rather than getDolGlobalInt: MAIN_USE_TOP_MENU_IMPORT_FILE
	// may legitimately hold a url, which casts to 0 as an int and would read as "off".
	$on = (getDolGlobalString($constname) !== '' && getDolGlobalString($constname) !== '0') ? 1 : 0;
	print '<td class="center">'.$form->selectyesno($constname, $on, 1).'</td>';
	print '</tr>';
}

print '</table>';
print '</div>';

print '<div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans("Save")).'"></div>';

print '</form>';

print '<br>';
print info_admin($langs->transnoentities("FullSidebarSetupNote"), 0, 0, '1', '');

llxFooter();
$db->close();
