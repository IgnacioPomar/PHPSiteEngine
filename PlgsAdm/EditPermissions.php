<?php

namespace PHPSiteEngine\PlgsAdm;

use PHPSiteEngine\Context;
use PHPSiteEngine\Plugin;
use PHPSiteEngine\Html;

class EditPermissions extends Plugin
{
	const PERM_COLORS = array (- 1 => '#ffa7a7', 0 => '#c1c1c1', 1 => '#a5ff8b');
	const PERM_VALUES = array (- 1 => 'DENIED', 0 => 'NOT SET', 1 => 'ALLOWED');
	private array $plgssWithPerms;
	private array $currentPerms;
	private array $efectivePerms;
	private bool $showEfectivePerms;


	private static function addPermCombo ($id, $defVal)
	{
		$retval = '';
		$selColor = self::PERM_COLORS [0];
		foreach (self::PERM_VALUES as $idc => $val)
		{
			$sel = '';
			$color = self::PERM_COLORS [$idc];

			if ($idc == $defVal)
			{
				$selColor = $color;
				$sel = 'selected=""';
			}

			$retval .= "<option value=\"$idc\" style=\"background-color: $color;\" $sel>$val</option>";
		}
		$retval .= "</select></span>";

		return '<span class="permCombo"><select name="' . $id . '" style="background-color: ' . $selColor . ';">' . $retval;
	}


	private static function inputIdEncode ($node, $plg, $perm)
	{
		$id = rtrim (strtr (base64_encode ($node), '+/', '-_'), '=');
		$id .= '@' . rtrim (strtr (base64_encode ($plg), '+/', '-_'), '=');
		$id .= '@' . rtrim (strtr (base64_encode ($perm), '+/', '-_'), '=');

		return 'b64@' . $id;
	}


	private static function inputIdDecode ($id)
	{
		$parts = explode ("@", $id);
		$retVal = array ();
		$retVal ['node'] = base64_decode (strtr ($parts [1], '-_', '+/'));
		$retVal ['plg'] = base64_decode (strtr ($parts [2], '-_', '+/'));
		$retVal ['perm'] = base64_decode (strtr ($parts [3], '-_', '+/'));

		return $retVal;
	}


	private function savePermsCommon ($sqlStart, $idElem, $idElemType)
	{
		$idElem = ($idElemType === 'i') ? (int) $idElem : (string) $idElem;

		$placeholders = array ();
		$types = '';
		$params = array ();

		foreach ($_POST as $key => $val)
		{
			if (substr ($key, 0, 4) === 'b64@')
			{
				$id = self::inputIdDecode ($key);

				$placeholders [] = '(?, ?, ?, ?, ?)';
				$types .= 'ss' . $idElemType . 'si';
				$params [] = $id ['node'];
				$params [] = $id ['plg'];
				$params [] = $idElem;
				$params [] = $id ['perm'];
				$params [] = (int) $val;
			}
		}

		if (count ($placeholders) > 0)
		{
			$sql = $sqlStart . PHP_EOL . implode (',' . PHP_EOL, $placeholders);
			$sql .= 'ON DUPLICATE KEY UPDATE permValue=VALUES(permValue)';

			$stmt = $this->context->mysqli->prepare ($sql);
			$stmt->bind_param ($types, ...$params);

			$retVal = "<h1>Saving Permissions</h1>";
			if (! $stmt->execute ())
			{
				$retVal .= '<p class="error">Failed saving permissions.</p>';
			}
			else
			{
				$retVal .= '<p class="success">Permissions saved.</p>';
			}

			return $retVal . $this->showSelector ();
		}

		return '<p class="warning">There was no permmisions to save.</p>';
	}


	private function saveGrpPerms ()
	{
		$sql = 'INSERT INTO wePermissionsGroup (mnuNode,plgName,idGrp,permName, permValue) VALUES';
		return $this->savePermsCommon ($sql, $_POST ['idGrp'], 'i');
	}


	private function saveUsrPerms ()
	{
		$sql = 'INSERT INTO wePermissionsUsers (mnuNode,plgName,idUser,permName, permValue) VALUES';
		return $this->savePermsCommon ($sql, $_POST ['idUsr'], 's');
	}


	private function getStoredValue ($node, $plugin, $perm)
	{
		/*
		 * if (! isset ($this->currentPerms [$node])) return 0;
		 * $nodeGrp = &$this->currentPerms [$node];
		 *
		 * if (! isset ($nodeGrp [$plugin])) return 0;
		 * $plgGrp = &$nodeGrp [$plugin];
		 *
		 * if (! isset ($plgGrp [$perm]))
		 * return 0;
		 * else
		 * return $plgGrp [$perm];
		 */
		return $this->currentPerms [$node] [$plugin] [$perm] ?? 0;
	}


	private function showEfectivePerm ($node, $plugin, $perm)
	{
		$efective = $this->efectivePerms [$node] [$plugin] [$perm] ?? 0;
		return '<span class="efective" style="background-color:' . self::PERM_COLORS [$efective] . ';">' . self::PERM_VALUES [$efective] . '</span>';
	}


	/**
	 * Show the menu level, with Parameters if the plugin has them
	 *
	 * @param int $parentID
	 * @param array $mnu
	 * @return string
	 */
	private function getMenuLevel (int $level, array &$mnu)
	{
		$ident = str_repeat ('<span class="w-25"></span>', $level);
		$retVal = '';

		foreach ($mnu as $opc)
		{
			$isEnabled = (isset ($opc ['isEnable'])) ? (1 == $opc ['isEnable']) : true;
			$extraClass = '';
			$txtNodeId = $opc ['opc'] ?? '';

			if (! $isEnabled)
			{
				$extraClass = 'disabled';
				$txtNodeId .= ' (disabled)';
			}

			$retVal .= '<div class="menuNode ' . $extraClass . '"><span class="nodeId">' . Html::e ($txtNodeId) . '</span>';
			$retVal .= '<span class="nodeName">' . $ident . Html::e ($opc ['name']) . '</span>';

			if ($this->isEditable)
			{
				if (isset ($opc ['opc']))
				{
					$defVal = $this->getStoredValue ($opc ['opc'], $opc ['plg'], '');
					$id = self::inputIdEncode ($opc ['opc'], $opc ['plg'], '');
					$retVal .= self::addPermCombo ($id, $defVal);

					if ($this->showEfectivePerms)
					{
						$retVal .= $this->showEfectivePerm ($opc ['opc'], $opc ['plg'], '');
					}
				}
			}
			else
			{
				$retVal .= '<span class="NoEditable">No Editable</span>';
			}

			// Aqui el combo selector
			$retVal .= '</div>';

			if (isset ($opc ['opc']) && isset ($this->plgssWithPerms [$opc ['plg']]))
			{
				$extraPerms = json_decode ($this->plgssWithPerms [$opc ['plg']], true);
				foreach ($extraPerms as $perm)
				{
					$retVal .= '<div class="pluginNode"><span class="nodeId"></span><span class="nodeName">' . $ident . '<span class="pluginPerm">' . Html::e ($perm) . '</span></span>';

					$defVal = $this->getStoredValue ($opc ['opc'], $opc ['plg'], $perm);
					$id = self::inputIdEncode ($opc ['opc'], $opc ['plg'], $perm);
					$retVal .= self::addPermCombo ($id, $defVal);
					if ($this->showEfectivePerms)
					{
						$retVal .= $this->showEfectivePerm ($opc ['opc'], $opc ['plg'], $perm);
					}
					$retVal .= '</div>';
				}
			}

			if (isset ($opc ['subOpcs']))
			{
				$retVal .= $this->getMenuLevel ($level + 1, $opc ['subOpcs']);
			}
		}

		return $retVal;
	}


	/**
	 * Show the full Menu
	 *
	 * @return string
	 */
	private function getMainMenu ($desc, $hiddenInput)
	{
		// Load the menu params
		$this->isEditable = $this->context->mnu->isEditable;

		// Finally, show the menu
		$retVal = "<h1>Permissions Management for $desc</h1>";
		if (! $this->isEditable)
		{
			$retVal .= '<p class="info">This is a fixed menu, however it is possible to adjust the plugin permissions.</p>';
		}

		$retVal .= '<form action="' . $this->uriPrefix . '" method="post" autocomplete="off">' . PHP_EOL;
		$retVal .= $hiddenInput . PHP_EOL;

		$retVal .= '<div id="mnuEditor">';

		// Menu Editor HEader
		$retVal .= '<div class="header"><span class="nodeId">Node Id</span><span class="nodeName">Node name</span><span class="NoEditable">Permmision</span>';
		if ($this->showEfectivePerms)
		{
			$retVal .= '<span class="efective">Inherited</span>';
		}
		$retVal .= '</div>';

		$retVal .= $this->getMenuLevel (0, $this->context->mnu->arrOpcs);
		$retVal .= '</div>';

		$retVal .= '<button class="btn" type="submit" value="Save">Save</button>';
		$retVal .= '</form>';

		return $retVal;
	}


	/**
	 * Loads the Parameters in the database
	 */
	private function loadPlgsWithPerms ()
	{
		$this->plgssWithPerms = array ();
		$sql = 'SELECT plgName, plgPerms FROM wePlugins WHERE LENGTH(plgPerms) >2;';
		if ($resultado = $this->context->mysqli->query ($sql))
		{
			while ($row = $resultado->fetch_assoc ())
			{
				$this->plgssWithPerms [$row ['plgName']] = $row ['plgPerms'];
			}
		}
	}


	/**
	 * Loads the Parameters in the database
	 */
	private function loadCurrentGroupPerms ($idGrp)
	{
		$this->currentPerms = array ();
		$idGrp = (int) $idGrp;
		$sql = 'SELECT * FROM wePermissionsGroup WHERE idGrp=?';
		$stmt = $this->context->mysqli->prepare ($sql);
		$stmt->bind_param ('i', $idGrp);
		$stmt->execute ();
		if ($resultado = $stmt->get_result ())
		{
			while ($row = $resultado->fetch_assoc ())
			{
				$this->currentPerms [$row ['mnuNode']] [$row ['plgName']] [$row ['permName']] = $row ['permValue'];
			}
		}
	}


	private function loadCurrentUserPerms ($idUsr)
	{
		$this->currentPerms = array ();
		$sql = 'SELECT * FROM wePermissionsUsers WHERE idUser=?';
		$stmt = $this->context->mysqli->prepare ($sql);
		$stmt->bind_param ('s', $idUsr);
		$stmt->execute ();
		if ($resultado = $stmt->get_result ())
		{
			while ($row = $resultado->fetch_assoc ())
			{
				$this->currentPerms [$row ['mnuNode']] [$row ['plgName']] [$row ['permName']] = $row ['permValue'];
			}
		}
	}


	private function loadEfectivePerms ($idUsr)
	{
		$this->efectivePerms = array ();
		$sql = 'SELECT MIN(permValue) as permValue,mnuNode,plgName,permName ';
		$sql .= 'FROM wePermissionsGroup WHERE permValue<>0 AND idGrp IN ';
		$sql .= '(SELECT idGrp FROM weUsersGroups WHERE idUser = ?) GROUP BY mnuNode,plgName,permName';
		$stmt = $this->context->mysqli->prepare ($sql);
		$stmt->bind_param ('s', $idUsr);
		$stmt->execute ();
		if ($resultado = $stmt->get_result ())
		{
			while ($row = $resultado->fetch_assoc ())
			{
				$this->efectivePerms [$row ['mnuNode']] [$row ['plgName']] [$row ['permName']] = $row ['permValue'];
			}
		}
	}


	private function showGrpForm ()
	{
		// Load group name
		$grpName = '';
		$idGrp = (int) $_GET ['idGrp'];
		$sql = 'SELECT grpName FROM weGroups WHERE idGrp=?';
		$stmt = $this->context->mysqli->prepare ($sql);
		$stmt->bind_param ('i', $idGrp);
		$stmt->execute ();
		if ($resultado = $stmt->get_result ())
		{
			if ($row = $resultado->fetch_assoc ())
			{
				$grpName = $row ['grpName'];
			}
		}

		$hiddenInput = '<input type="hidden" name="idGrp" value="' . Html::e ($idGrp) . '" />';

		return $this->getMainMenu ('group <hlight>' . Html::e ($grpName) . '</hlight>', $hiddenInput);
	}


	private function showUsrForm ()
	{
		// Load group name
		$usrName = '';
		$idUsr = $_GET ['idUsr'];
		$sql = 'SELECT u.name,g.groups FROM weUsers u ';
		$sql .= ' LEFT JOIN  (SELECT GROUP_CONCAT(grpName  SEPARATOR ", ") AS groups, rel.idUser FROM weGroups g INNER JOIN weUsersGroups rel ON g.idGrp = rel.idGrp GROUP BY rel.idUser) g';
		$sql .= ' ON u.idUser=g.idUser WHERE u.idUser=?';
		$stmt = $this->context->mysqli->prepare ($sql);
		$stmt->bind_param ('s', $idUsr);
		$stmt->execute ();
		if ($resultado = $stmt->get_result ())
		{
			if ($row = $resultado->fetch_assoc ())
			{
				$usrName = $row ['name'];
				$usrGroups = $row ['groups'];
			}
		}

		$hiddenInput = '<input type="hidden" name="idUsr" value="' . Html::e ($idUsr) . '" />';

		return $this->getMainMenu ('user <hlight>' . Html::e ($usrName) . '</hlight> (<dlight>' . Html::e ($usrGroups) . '</dlight>)', $hiddenInput);
	}


	/**
	 * Show all the groups/users to allow edit permissions
	 *
	 * @return string
	 */
	private function showSelector ()
	{
		$retVal = '<h1>Permissions Management</h1><div class="adminFrame">';

		$retVal .= '<span class="permissionGroup"><h2>Groups</h2>';
		$sql = 'SELECT idGrp, grpName FROM weGroups ORDER BY grpName;';
		if ($resultado = $this->context->mysqli->query ($sql))
		{
			while ($row = $resultado->fetch_assoc ())
			{
				$retVal .= '<a href="' . $this->uriPrefix . 'idGrp=' . Html::e ($row ['idGrp']) . '"><div>' . Html::e ($row ['grpName']) . '</div></a>' . PHP_EOL;
			}
		}
		$retVal .= '</span>';

		$retVal .= '<span class="permissionGroup"><h2>Users</h2>';
		$sql = 'SELECT u.idUser,u.name,g.groups  FROM weUsers u ';
		$sql .= ' LEFT JOIN  (SELECT GROUP_CONCAT(grpName  SEPARATOR ", ") AS groups, rel.idUser FROM weGroups g INNER JOIN weUsersGroups rel ON g.idGrp = rel.idGrp GROUP BY rel.idUser) g';
		$sql .= ' ON u.idUser=g.idUser';

		if ($resultado = $this->context->mysqli->query ($sql))
		{
			while ($row = $resultado->fetch_assoc ())
			{
				$retVal .= '<a href="' . $this->uriPrefix . 'idUsr=' . Html::e ($row ['idUser']) . '"><div>' . Html::e ($row ['name']) . ' <span class="groups">(' . Html::e ($row ['groups']) . ')</span></div></a>' . PHP_EOL;
			}
		}
		$retVal .= '</span>';

		return $retVal . '</div>';
	}


	public function getExternalJs ()
	{
		$js = array ();
		$js [] = 'jquery-2.2.4.min.js';
		$js [] = 'admin.js';

		return $js;
	}


	/**
	 */
	public function __construct (Context $context)
	{
		parent::__construct ($context);
	}


	public static function getPlgInfo (): array
	{
	}


	/**
	 *
	 * @return string
	 */
	public function main ()
	{
		if (isset ($_GET ['idGrp']))
		{
			$this->showEfectivePerms = false;
			$this->loadPlgsWithPerms ();
			$this->loadCurrentGroupPerms ($_GET ['idGrp']);

			return $this->showGrpForm ();
		}
		else if (isset ($_GET ['idUsr']))
		{
			$this->showEfectivePerms = true;
			$this->loadPlgsWithPerms ();
			$this->loadCurrentUserPerms ($_GET ['idUsr']);
			$this->loadEfectivePerms ($_GET ['idUsr']);

			return $this->showUsrForm ();
		}
		else if (isset ($_POST ['idGrp']))
		{
			return $this->saveGrpPerms ();
		}
		else if (isset ($_POST ['idUsr']))
		{
			return $this->saveUsrPerms ();
		}
		else
		{
			return $this->showSelector ();
		}
		return "";
	}
}
