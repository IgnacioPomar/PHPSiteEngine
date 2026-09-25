<?php

namespace PHPSiteEngine;

/*
 * The Menu - JSon variant means all the users will see the same options
 * (it'll not hide options if we haver give permissions to it)
 *
 */
class MenuLoaderJson
{


	/**
	 *
	 * @param Context $context
	 * @return array
	 */
	public static function load (&$context, Menu &$menu)
	{
		$menuFileName = (isset ($GLOBALS ['jsonMenu'])) ? $GLOBALS ['jsonMenu'] : 'mainMenu.json';
		MenuLoaderJson::loadFromFile (Site::$cfgPath . $menuFileName, $menu);
	}


	public static function loadFromFile ($menuFile, Menu &$menu)
	{
		$opcs = file_exists ($menuFile) ? json_decode (file_get_contents ($menuFile), true) : null;
		$menu->setMenuOpc ($opcs);
		$menu->isEditable = false;
	}
}


