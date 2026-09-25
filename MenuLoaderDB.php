<?php

namespace PHPSiteEngine;

class MenuLoaderDB
{
	const ONLY_SITE_ADMIN = - 1;


	/**
	 *
	 * @param Context $context
	 * @return array
	 */
	public static function load (&$context, Menu &$menu)
	{
		$menu->setMenuOpc (MenuLoaderDB::getArrayMenu ($context->mysqli, $context->userId));
		$menu->isEditable = true;
	}


	/**
	 *
	 * @return array $orderMenu
	 */
	public static function getArrayMenu ($mysqli, $userId = self::ONLY_SITE_ADMIN)
	{
		if ($userId != self::ONLY_SITE_ADMIN)
		{
			$query = 'SELECT m.idNodo, m.idNodoParent, m.uri AS opc, m.plg, m.isVisible AS "show" , m.name, m.tmplt FROM weMenu m ';
			$query .= 'INNER JOIN (SELECT MIN(permValue) permVal,mnuNode,plgName FROM ';
			$query .= '(SELECT mnuNode,plgName,permValue,permName FROM wePermissionsGroup WHERE idGrp IN (SELECT idGrp FROM weUsersGroups WHERE idUser = ?) ';
			$query .= "UNION ALL SELECT mnuNode,plgName,permValue,permName FROM wePermissionsUsers WHERE idUser=?) permUnion WHERE permName='' AND permValue <> 0 ";
			$query .= 'GROUP BY mnuNode,plgName) up ON m.uri = up.mnuNode AND m.plg = up.plgName WHERE up.permVal=1 AND isEnable=1 ';
			$query .= 'ORDER BY idNodoParent,menuOrder;';

			$stmt = $mysqli->prepare ($query);
			$stmt->bind_param ('ss', $userId, $userId);
			$stmt->execute ();
			$menuDB = $stmt->get_result ()->fetch_all (MYSQLI_ASSOC);
		}
		else
		{
			$query = 'SELECT m.idNodo, m.idNodoParent, m.uri AS opc, m.plg, m.isVisible AS "show" , m.name, m.tmplt, m.isEnable FROM weMenu m ';
			$query .= 'ORDER BY idNodoParent,menuOrder;';

			// TODO: The following code is not clear: redo
			$menuDB = $mysqli->query ($query)->fetch_all (MYSQLI_ASSOC);
		}

		// Delete not real OPC
		foreach ($menuDB as &$parentItem)
		{
			if (substr ($parentItem ['opc'], 0, 1) == '@')
			{
				unset ($parentItem ['opc']);
			}
		}

		$orderMenuDb = array ();

		foreach ($menuDB as &$parentItem)
		{

			// If the menu contains a parent we stop the loop since it should be assigned.
			// if ($parentItem ['idNodoParent'] != NULL) break;

			$orderMenuDb [$parentItem ['idNodo']] = $parentItem;
			// We delete the first element, since it will be the one we just saved in our new array
			array_shift ($menuDB);

			$childs = self::addChildsMenuItems ($menuDB, $parentItem ['idNodo']);
			if (! empty ($childs [$parentItem ['idNodo']]))
			{
				$orderMenuDb [$parentItem ['idNodo']] ['subOpcs'] = $childs [$parentItem ['idNodo']];
			}
		}

		return $orderMenuDb;
	}


	/**
	 *
	 * @param array $menuDB
	 * @param int $parentId
	 * @return array $childsOfMenu
	 */
	private static function addChildsMenuItems (&$menuDB, $parentId)
	{
		$childsOfMenu = array ();
		foreach ($menuDB as $index => $childItem)
		{
			if ($childItem ['idNodoParent'] == $parentId)
			{
				$childsOfMenu [$parentId] [$childItem ['idNodo']] = $childItem;
				unset ($menuDB [$index]);

				$childs = self::addChildsMenuItems ($menuDB, $childItem ['idNodo']);
				if (! empty ($childs [$childItem ['idNodo']]))
				{
					$childsOfMenu [$parentId] [$childItem ['idNodo']] ['subOpcs'] = $childs [$childItem ['idNodo']];
				}
			}
		}
		if (! empty ($childsOfMenu)) return $childsOfMenu;
	}
}