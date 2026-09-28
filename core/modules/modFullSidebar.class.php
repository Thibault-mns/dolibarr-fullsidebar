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
 * \file        core/modules/modFullSidebar.class.php
 * \ingroup     fullsidebar
 * \brief       Module descriptor for FullSidebar (complete left menu tree)
 */

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Class modFullSidebar
 *
 * Ships a menu handler (core/menus/standard/fullsidebar_menu.php) that renders the
 * left menu as a collapsible tree containing EVERY module and its submenus, instead
 * of the sub-entries of the current module only.
 *
 * The handler lives inside the module (module_parts['menus']), so no core file is
 * ever touched: main.inc.php looks the handler up in $conf->modules_parts['menus']
 * before falling back to /core/menus/standard/.
 */
class modFullSidebar extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

		// Range 185421-185849 reserved by TH Investissements / Matelas No Stress
		// on https://wiki.dolibarr.org/index.php/List_of_modules_id
		$this->numero = 185447;
		$this->rights_class = 'fullsidebar';
		$this->family = "interface";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		// Keys translated via langs/xx_XX/fullsidebar.lang
		$this->description = "FullSidebarDescription";
		$this->descriptionlong = "FullSidebarDescriptionLong";
		$this->editor_name = 'TH Investissements / Matelas No Stress';
		$this->editor_url = 'https://github.com/Thibault-mns';
		$this->version = '1.5.1';
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		$this->picto = 'fa-bars';

		// 'menus' => 1 adds /fullsidebar/core/menus/ to the directories main.inc.php
		// scans for the handler named by MAIN_MENU_STANDARD.
		// WARNING: module_parts is read from llx_const AT ACTIVATION TIME. Changing it
		// later requires a disable/enable cycle of the module.
		$this->module_parts = [
			'menus' => 1,
			'css'   => ['/fullsidebar/css/fullsidebar.css'],
			'js'    => ['/fullsidebar/js/fullsidebar.js'],
		];

		$this->dirs = [];
		$this->config_page_url = ["setup.php@fullsidebar"];

		$this->hidden = false;
		$this->depends = [];
		$this->requiredby = [];
		$this->conflictwith = [];
		$this->phpmin = [7, 3];
		// Only version actually tested: the handler reuses eldy.lib.php internals
		// (print_left_eldy_menu signature, 'enabled' = 2 semantics) and the markup of the
		// eldy theme. Lower this only after testing the older version for real.
		$this->need_dolibarr_version = [23, 0];
		$this->langfiles = ["fullsidebar@fullsidebar"];

		// Constants owned by the module.
		// Both defaults are the SAFE value ('0'), and setup.php always writes an
		// explicit '0'/'1' — never '' — otherwise dolibarr_set_const() would DELETE the
		// row and insert_const() would silently restore this default on the next
		// disable/enable cycle.
		$this->const = [
			// name, type, value, note, visible, entity, deleteonunactive
			[
				'FULLSIDEBAR_SHOW_UNAUTHORIZED', 'chaine', '0',
				'Show menu entries the user has no permission on, greyed out', 0, 'current', 0,
			],
			[
				'FULLSIDEBAR_EXPAND_ALL', 'chaine', '0',
				'Expand every branch by default instead of the current module only', 0, 'current', 0,
			],
			[
				'FULLSIDEBAR_REMEMBER_OPENED', 'chaine', '1',
				'Remember the branches the user opened (browser local storage)', 0, 'current', 0,
			],
			[
				'FULLSIDEBAR_HIDE_TOPMENU', 'chaine', '0',
				'Hide the entries of the horizontal menu (bar, logo and hamburger stay)', 0, 'current', 0,
			],
		];

		$this->tabs = [];
		$this->dictionaries = [];
		$this->boxes = [];
		$this->cronjobs = [];
		$this->menu = [];
		$this->rights = [];
	}

	/**
	 * Function called when module is enabled.
	 *
	 * Installs the menu handler as the active one (MAIN_MENU_STANDARD) and keeps the
	 * previous value aside so remove() can put it back.
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf;

		$current = getDolGlobalString('MAIN_MENU_STANDARD', 'eldy_menu.php');
		if ($current !== 'fullsidebar_menu.php') {
			// Only remember a foreign handler, so a disable/enable cycle does not
			// record our own file as "the previous one".
			dolibarr_set_const($this->db, 'FULLSIDEBAR_PREVIOUS_MENU', $current, 'chaine', 0, '', $conf->entity);
		}
		dolibarr_set_const($this->db, 'MAIN_MENU_STANDARD', 'fullsidebar_menu.php', 'chaine', 0, '', $conf->entity);

		return $this->_init([], $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * Restores the menu handler that was active before the module was enabled, so the
	 * interface never ends up pointing at a handler that is no longer loadable.
	 *
	 * @param string $options Options when disabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		global $conf;

		if (getDolGlobalString('MAIN_MENU_STANDARD') === 'fullsidebar_menu.php') {
			$previous = getDolGlobalString('FULLSIDEBAR_PREVIOUS_MENU', 'eldy_menu.php');
			if (empty($previous)) {
				$previous = 'eldy_menu.php';
			}
			dolibarr_set_const($this->db, 'MAIN_MENU_STANDARD', $previous, 'chaine', 0, '', $conf->entity);
		}

		return $this->_remove([], $options);
	}
}
