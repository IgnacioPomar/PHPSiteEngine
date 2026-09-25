<?php

namespace PHPSiteEngine\PlgsAdm;

use PHPSiteEngine\Site;
use PHPSiteEngine\Plugin;
use PHPSiteEngine\Context;
use PHPSiteEngine\ColumnFormatter;
use PHPSiteEngine\Autoform;
use PHPSiteEngine\Html;

// YAGNI: Include it in the ColumnFormatter class
class MaintenanceGroups extends Plugin
{
	private $jsonFile;


	public function __construct (Context $context)
	{
		parent::__construct ($context);

		$this->jsonFile = Site::$nsPath . 'tables/groups.jsonTable';
	}

	// @formatter:off
	const COLS_TABLE_GROUPS = array (
			'grpName' => array ('w-400', 'Group name', 'Visible name on the platform.'),
	);
	// @formatter:on

	/**
	 *
	 * @return string
	 */
	private function showListGroups ()
	{
		$resultGroups = $this->runQueryGroups ();

		$formatter = new ColumnFormatter (self::COLS_TABLE_GROUPS);

		$retVal = "<a href='{$this->uriPrefix}newGroup' class='btn mb-3'>Add new group</a>";

		$retVal .= "<div class='head'>{$formatter->getHeaderCols ()}</div>";

		while ($group = $resultGroups->fetch_assoc ())
		{
			$retVal .= '<div class="line">';

			$retVal .= $formatter->getStyledBodyCols ($group);

			$retVal .= '<div class="w-100"></div>';

			$link = "{$this->uriPrefix}idGrp=" . Html::e ($group ['idGrp']);
			$retVal .= "<a href='$link'><span class='w-50'><svg xmlns='http: // www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-pencil-fill' viewBox='0 0 16 16'><path d='M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708l-3-3zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.499.499 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11l.178-.178z'/></svg></span></a>";

			$retVal .= "</div>";
		}

		return $retVal;
	}


	/**
	 *
	 * @param number $idGroup
	 * @return \mysqli_result|false
	 */
	private function runQueryGroups ($idGroup = 0)
	{
		// YAGNI: Receive an array with the necessary columns
		$idGroup = (int) $idGroup;
		if ($idGroup != 0)
		{
			$stmt = $this->context->mysqli->prepare ('SELECT * FROM weGroups WHERE idGrp = ?');
			$stmt->bind_param ('i', $idGroup);
			$stmt->execute ();
			return $stmt->get_result ();
		}

		return $this->context->mysqli->query ('SELECT * FROM weGroups');
	}


	/**
	 *
	 * @return string
	 */
	private function showGroupForm ()
	{
		$autoForm = new AutoForm ($this->jsonFile);
		$fieldSet = array ('grpName');
		// It is necessary to generate the form
		$autoForm->set = $fieldSet;

		$retVal = '';

		if (! empty ($_POST))
		{
			if (! empty ($_POST ['idGrp']))
			{
				$retVal .= $this->updateGroup ($autoForm);
			}
			else if (! empty ($_POST ['grpName']))
			{
				return $this->insertNewGroup ($autoForm);
			}
		}

		if (! empty ($_GET ['idGrp']))
		{
			if ($resultGroup = $this->runQueryGroups ($_GET ['idGrp']))
			{
				if ($group = $resultGroup->fetch_assoc ())
				{
					$autoForm->setHidden ('idGrp', $group ['idGrp']);

					if (empty ($retVal))
					{
						$retVal .= '<div class="container"><h1>Edit group</h1>';
					}
					$retVal .= $autoForm->generateForm ($group, ! empty ($_POST ['idGrp']));
					$retVal .= '</div>';

					return $retVal;
				}
			}
		}

		$retVal = '<div class="container"><h1>Add new group</h1>';
		$retVal .= $autoForm->generateForm ($_POST);
		$retVal .= '</div>';

		return $retVal;
	}


	/**
	 *
	 * @param object $autoForm
	 * @return string
	 */
	private function insertNewGroup ($autoForm)
	{
		$autoForm->mysqli = $this->context->mysqli;
		$query = $autoForm->getInsertSql ($_POST);
		$this->context->mysqli->query ($query);

		$retVal = '<p>Registro Insertado Correctamente</p>';
		$icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-counterclockwise" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466z"/></svg>';
		$retVal .= '<div class="container"><a class="btn rigth" href="' . strtok ($this->uriPrefix, '?') . '">' . $icon . 'Volver</a></div>';
		return $retVal;
	}


	/**
	 *
	 * @param object $autoForm
	 * @return string
	 */
	private function updateGroup ($autoForm)
	{
		$autoForm->mysqli = $this->context->mysqli;
		$sql = $autoForm->getUpdateSql ($_POST, [ 'idGrp']);

		$this->context->mysqli->query ($sql);

		$retVal = "<p>Registro Actualizado Correctamente</p>";
		$icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-counterclockwise" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466z"/></svg>';
		$retVal .= '<div class="container"><a class="btn rigth" href="' . strtok ($this->uriPrefix, '?') . '">' . $icon . 'Volver</a></div>';

		return $retVal;
	}


	public static function getPlgInfo (): array
	{
		$plgInfo = array ();
		$plgInfo ['plgDescription'] = "Permite crear, editar y listar los grupos de usuarios.";
		$plgInfo ['isMenu'] = 1;
		$plgInfo ['perms'] = '[]';
		$plgInfo ['params'] = '[]';

		return $plgInfo;
	}


	/**
	 *
	 * @return string
	 */
	public function main ()
	{
		if (isset ($_GET ['newGroup']) || ! empty ($_GET ['idGrp']))
		{
			$retVal = $this->showGroupForm ();
		}
		else
		{
			$retVal = $this->showListGroups ();
		}

		return $retVal;
	}
}