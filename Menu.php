<?php

namespace PHPSiteEngine;

/*
 *
 * As all the Menu modules, it'll return:
 * - From the selected option:
 * · The template
 * · The plugin name
 * - A formatted menu for the user
 * - The personal Info Menu (if any)
 */
class Menu
{
	public array $arrOpcs;
	public bool $isEditable;
	private array $currOpc;
	public string $subPath;
	private bool $hasOpc;


	/**
	 * Sets the full menu info
	 */
	function __construct ()
	{
		$this->subPath = (isset ($_SERVER ['PATH_INFO'])) ? $_SERVER ['PATH_INFO'] : "/";

		$this->hasOpc = FALSE;
	}


	public function setMenuOpc ($arrOpcs)
	{
		$this->arrOpcs = empty ($arrOpcs) ? self::getEmptyMenu () : $arrOpcs;
		$this->setSelectedOpc ($this->arrOpcs);
	}


	/**
	 * Fallback menu used when no menu source provides any option
	 * (empty/missing json file, or an empty weMenu table), so the
	 * root path still resolves to a selectable option instead of a 404.
	 */
	private static function getEmptyMenu ()
	{
		return [
			[
				'opc' => '/',
				'name' => 'Home',
				'tmplt' => 'skel.htm',
				'plg' => '',
				'show' => true
			]
		];
	}


	public function getUriPrefix ()
	{
		return $_SERVER ['SCRIPT_NAME'] . $this->subPath . '?';
	}


	/**
	 * Main Menu.
	 *
	 * @return string
	 */
	public function getMenu ()
	{
		$retVal = '<span id="mainMenu">';
		$retVal .= $this->getMnuOpcs ($this->arrOpcs, 0);
		return $retVal . '</span>';
	}


	/**
	 * Main Menu.
	 *
	 * @return string
	 */
	public function getBaseTemplate ()
	{
		return $this->currOpc ['tmplt'];
	}


	public function getTitle ()
	{
		return $this->currOpc ['name'];
	}


	public function getPlugin ()
	{
		if (isset ($this->currOpc))
		{
			return $this->currOpc ['plg'];
		}
		else
		{
			return '';
		}
	}


	public function stCurrOpc (array $currOpc)
	{
		$this->currOpc = $currOpc;
	}


	public function hasOpcSelected ()
	{
		return $this->hasOpc;
	}


	private function getOpcFromMenuLevel ($node, &$mnu)
	{
		foreach ($mnu as $opc)
		{
			if (isset ($opc ['opc']) && $opc ['opc'] == $node)
			{
				return $opc;
			}
			else if (isset ($opc ['subOpcs']))
			{
				$retVal = $this->getOpcFromMenuLevel ($node, $opc ['subOpcs']);
				if (! is_null ($retVal))
				{
					return $retVal;
				}
			}
		}
		return null;
	}


	public function getOpcFromMenu ($node)
	{
		return $this->getOpcFromMenuLevel ($node, $this->arrOpcs);
	}


	private function getLinkOpcWithPlgFromMnuLvl (string $plgName, &$mnu)
	{
		foreach ($mnu as $opc)
		{
			if (isset ($opc ['plg']) && $opc ['plg'] == $plgName)
			{
				return $opc ['opc'];
			}
			else if (isset ($opc ['subOpcs']))
			{
				$retVal = $this->getLinkOpcWithPlgFromMnuLvl ($plgName, $opc ['subOpcs']);
				if (! is_null ($retVal))
				{
					return $retVal;
				}
			}
		}
		return null;
	}


	public function getLinkOpcWithPlg (string $plgName)
	{
		return $_SERVER ['SCRIPT_NAME'] . $this->getLinkOpcWithPlgFromMnuLvl ($plgName, $this->arrOpcs);
	}


	/**
	 * Searchs recursively for the selected option
	 *
	 * @return string
	 */
	private function setSelectedOpc (&$arrOpcs)
	{
		$retVal = false;
		foreach ($arrOpcs as &$opc)
		{
			if (isset ($opc ['opc']) && $opc ['opc'] == $this->subPath)
			{
				$opc ['isSelected'] = true;
				$this->currOpc = &$opc;
				$retVal = true;
				$this->hasOpc = TRUE;

				if (isset ($opc ['subOpcs']))
				{
					$this->setSelectedOpc ($opc ['subOpcs']);
				}
			}
			else if (isset ($opc ['subOpcs']))
			{
				$opc ['isSelected'] = $this->setSelectedOpc ($opc ['subOpcs']);
			}
			else
			{
				$opc ['isSelected'] = false;
			}
		}
		return $retVal;
	}


	/**
	 * Main Menu.
	 * Iterate recursively on directories
	 *
	 * @return string
	 */
	private function getMnuOpcs (&$arrOpcs, $lvl)
	{
		$retVal = '<ul class="mnuLvl' . $lvl . '">';
		foreach ($arrOpcs as $opc)
		{
			if ($opc ['show'])
			{
				$retVal .= '<li';
				if ($opc ['isSelected'])
				{
					$retVal .= ' class="selected"';
				}
				if (isset ($opc ['opc']))
				{
					$retVal .= '><a href="' . $_SERVER ['SCRIPT_NAME'] . $opc ['opc'] . '">' . $opc ['name'] . '</a>';
				}
				else
				{
					$retVal .= '>' . $opc ['name'];
				}

				if (isset ($opc ['subOpcs']))
				{
					$retVal .= $this->getMnuOpcs ($opc ['subOpcs'], $lvl + 1) . '<span class="caret"></span>';
				}
				$retVal .= '</li>' . PHP_EOL;
			}
		}
		return $retVal . '</ul>';
	}
}