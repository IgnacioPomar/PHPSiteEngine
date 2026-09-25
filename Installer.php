<?php

namespace PHPSiteEngine;

include_once ('DbSchema.php');
include_once ('UUIDv7.php');

// De momento no se hace por falta de tiempo
class Installer
{
	public $mysqli;


	public static function installFromScratch ()
	{
		if (isset ($_POST ['dbname']))
		{
			readfile (Site::$rscPath . 'html/installResult.htm');
			$installer = new Installer ();
			print ($installer->installProcessFromScratch ());
		}
		else
		{
			Installer::showInstallSetup ();
		}
	}


	public function installWithConfigFile ()
	{
		if (isset ($_POST ['adminlogin']))
		{
			readfile (Site::$rscPath . 'html/installResult.htm');
			print ($this->installProcessCommon ());
		}
		else
		{
			readfile (Site::$rscPath . 'html/installFormNoCfg.htm');
		}
	}


	private static function showInstallSetup ()
	{
		$layout = file_get_contents (Site::$rscPath . 'html/installForm.htm');

		header ('Content-Type: text/html; charset=utf-8');
		print ($layout);
	}


	public function __construct ($mysqli = NULL)
	{
		if ($mysqli == NULL)
		{
			$this->mysqli = @new \mysqli ($_POST ['dbserver'], $_POST ['dbuser'], $_POST ['dbpass'], $_POST ['dbname'], $_POST ['dbport']);
		}
		else
		{
			$this->mysqli = $mysqli;
		}
	}


	/**
	 * Steps for a full fresh installs
	 *
	 * @return string
	 */
	private function installProcessFromScratch ()
	{
		$outputMessage = '';

		// 0.- Check Params
		if (! $this->checkDbAccess ($outputMessage))
		{
			return $outputMessage;
		}

		// 1.- Guardamos el nuevo fichero de configuración
		if (! $this->saveNewCfgFile ($outputMessage))
		{
			return $outputMessage;
		}

		include_once (Site::$cfgFile);
		Site::initCfg ();

		return $this->installProcessCommon ();
	}


	/**
	 * Steps starting with the database inicialization
	 *
	 * @return string
	 */
	private function installProcessCommon ()
	{
		// Out Msg
		$outputMessage = '';

		// 2.- Creamos la estructura de la base de datos y metemos datos iniciales
		$outputMessage .= $this->createCoreTables ();

		if (! $this->addInitialData ($outputMessage))
		{
			return $outputMessage;
		}

		$outputMessage .= $this->registerPlugins ($outputMessage);
		return $outputMessage;
	}


	/**
	 * Creamos el esquema de la base de datos
	 *
	 * @param string $outputMessage
	 */
	public function createCoreTables ()
	{
		$retVal = '';

		$dir = new \FilesystemIterator (Site::$nsPath . 'tables/');
		foreach ($dir as $fileinfo)
		{
			if ($fileinfo->getExtension () == 'jsonTable')
			{
				$upd = DbSchema::createOrUpdateTable ($this->mysqli, $fileinfo->getPathname ());

				$retVal .= $this->formatDBAction ($upd);
			}
		}

		return $retVal;
	}


	private function createOrUpdateTables ($basePath)
	{
		$retVal = '';

		$dir = new \RecursiveIteratorIterator (new \RecursiveDirectoryIterator ($basePath));
		foreach ($dir as $file)
		{
			if ($file->isDir ())
				continue;
			else if ($file->getExtension () == 'jsonTable')
			{
				$upd = DbSchema::createOrUpdateTable ($this->mysqli, $file->getPathname ());

				$retVal .= $this->formatDBAction ($upd);
			}
		}

		return $retVal;
	}


	private function formatDBAction ($upd)
	{
		if ($upd [1] == - 1)
		{
			return '<div class="fail"><b>Error</b>: ' . $upd [0] . ' <br /> ' . $this->mysqli->connect_error . '</div>';
		}
		else if ($upd [1] == 1)
		{
			return '<div class="OK"><b>' . $upd [0] . ' </b>: Ok</div>';
		}
		else
		{
			return '<div class="none">' . $upd [0] . ' [No changes]</div>';
		}
	}


	public function registerPlugins ()
	{
		$out = '';
		// Get list of currently installed Plugins
		$installedPLgs = array ();
		if ($resultado = $this->mysqli->query ('SELECT plgName FROM wePlugins;'))
		{
			while ($row = $resultado->fetch_assoc ())
			{
				$installedPLgs [] = $row ['plgName'];
			}
		}

		// Get current plgs info
		$currentPlgs = array ();
		foreach (glob (Site::$plgsPath . "*.plg/*.php") as $filename)
		{
			$clases = self::getPhpClasses ($filename);
			if (sizeof ($clases) > 0)
			{
				include_once $filename;

				foreach ($clases as $className)
				{
					if (is_subclass_of ($className, 'PHPSiteEngine\\Plugin'))
					{
						$currentPlgs [$className] = call_user_func ($className . '::getPlgInfo');
						$currentPlgs [$className] ['path'] = str_replace (Site::$plgsPath, '', $filename);
					}
				}
			}
		}

		// Update existing plugins
		foreach ($installedPLgs as $plgName)
		{
			if (isset ($currentPlgs [$plgName]))
			{
				$plgInfo = &$currentPlgs [$plgName];
				foreach ($plgInfo as &$val)
				{
					$val = $this->mysqli->real_escape_string ($val);
				}

				$sql = 'UPDATE wePlugins SET plgDescrip = "' . $plgInfo ['plgDescription'] . '", ';
				$sql .= 'plgFile = "' . $plgInfo ['path'] . '", plgParams= "' . $plgInfo ['params'] . '", ';
				$sql .= 'plgPerms = "' . $plgInfo ['perms'] . '", isMenu = ' . $plgInfo ['isMenu'] . ' ';
				$sql .= "WHERE plgName = \"$plgName\";";

				if ($this->mysqli->query ($sql) === TRUE)
				{
					$out .= '<div class="ok">Plugin updated: <b>' . $plgName . '</b></div>';
				}
				else
				{
					$out .= '<div class="fail"><b>Error</b>: Unable to update plugin: <b>' . $plgName . '</b</div>';
				}
			}
		}

		// Create new Plugins
		foreach ($currentPlgs as $plgName => $plgInfo)
		{
			if (! in_array ($plgName, $installedPLgs))
			{
				foreach ($plgInfo as &$val)
				{
					$val = $this->mysqli->real_escape_string ($val);
				}

				$sql = 'INSERT INTO wePlugins (plgName,plgDescrip,plgFile,plgParams,plgPerms,isMenu) VALUES (';
				$sql .= '"' . $plgName . '","' . $plgInfo ['plgDescription'] . '","' . $plgInfo ['path'] . '",';
				$sql .= '"' . $plgInfo ['params'] . '","' . $plgInfo ['perms'] . '",' . $plgInfo ['isMenu'] . ');';
				if ($this->mysqli->query ($sql) === TRUE)
				{
					$out .= '<div class="ok">New plugin registered: <b>' . $plgName . '</b></div>';
				}
				else
				{
					$out .= '<div class="fail"><b>Error</b>: Unablle to register plugin: <b>' . $plgName . '</b></div>';
				}
			}
		}

		// Delete NOT existing plugins
		$diff = array_diff ($installedPLgs, array_keys ($currentPlgs));
		if (count ($diff) > 0)
		{
			$sql = 'DELETE FROM wePlugins WHERE plgName IN (';
			$sep = '';
			foreach ($diff as $plgName)
			{
				$sql .= $sep . '"' . $plgName . '"';
				$sep = ',';
			}
			$sql .= ');';

			if ($this->mysqli->query ($sql) === TRUE)
			{
				$out .= '<div class="ok">Removed inexistent PLugins</div>';
			}
			else
			{
				$out .= '<div class="fail"><b>Error</b>: Unable to remove old plugins<br />: ' . $this->mysqli->error . '</div>';
			}
		}
		else
		{
			$out .= '<div class="none">No Plugin was nedded to remove</div>';
		}

		// Create or Update PLugins Tables
		return $out . '<h3>Updating plugin tables</h3>' . $this->createOrUpdateTables (Site::$plgsPath);
	}


	/**
	 * Get the classes defined in a PHP file
	 *
	 * @param string $filename
	 * @see https://stackoverflow.com/a/2051010/74785
	 * @return mixed[] Devuelve una lista de las clases que contiene el fichero
	 */
	private static function getPhpClasses ($filename)
	{
		$classes = array ();
		$php_code = file_get_contents ($filename);
		$tokens = token_get_all ($php_code);
		$count = count ($tokens);
		for($i = 2; $i < $count; $i ++)
		{
			if ($tokens [$i - 2] [0] == T_CLASS && $tokens [$i - 1] [0] == T_WHITESPACE && $tokens [$i] [0] == T_STRING)
			{

				$class_name = $tokens [$i] [1];
				$classes [] = $class_name;
			}
		}
		return $classes;
	}


	/**
	 * Add the just created admin account
	 *
	 * @param boolean $outputMessage
	 */
	public function addInitialData (&$out)
	{
		$sqlCmd = 'INSERT INTO weUsers (idUser, name, email, password, isActive, isAdmin) VALUES (?, ?, ?, ?, 1, 1)';

		$idUser = UUIDv7::generateBase64 ();
		$passwordHash = password_hash ($_POST ['adminpass1'], PASSWORD_DEFAULT);

		$stmt = $this->mysqli->prepare ($sqlCmd);
		$stmt->bind_param ('ssss', $idUser, $_POST ['adminname'], $_POST ['adminlogin'], $passwordHash);

		// Finalmente, insertamos el nuevo registro
		if ($stmt->execute ())
		{
			$out .= '<div class="ok">Creating admin credentials:  <b>OK</b></div>';
			return TRUE;
		}
		else
		{
			$out .= '<div class="fail"><b>Error</b>: Unablle to create admin user.<br />: ' . $this->mysqli->error . '</div>';
			return FALSE;
		}
	}


	/**
	 * Check if the database info is correct
	 *
	 * @param string $outputMessage
	 *        	the output message in we will append the new report
	 */
	public function checkDbAccess (&$outputMessage)
	{
		if ($this->mysqli->connect_error)
		{
			$outputMessage .= '<div class="fail">';
			$outputMessage .= '<b>Error</b>: MySQL connection Failed.<br />';
			$outputMessage .= 'Codigo de error: ' . $this->mysqli->connect_errno . '.<br />';
			$outputMessage .= 'Descripci&oacute;n: ' . $this->mysqli->connect_error . '.<br />';
			$outputMessage .= '</div>';
			return FALSE;
		}
		else
		{
			$outputMessage .= '<div class="ok">Database Conection: <b>OK</b></div>';
			return TRUE;
		}
	}


	/**
	 *
	 * @param boolean $outputMessage
	 */
	public function saveNewCfgFile (&$outputMessage)
	{
		// TODO: Detect and apply the modules (auth....)
		$outputSkin = Site::$rscPath . 'default/site_cfg.template';
		$cfgFile = file_get_contents ($outputSkin);
		// Each value is embedded via var_export() so it becomes a safe, self-quoted PHP literal,
		// no matter what characters (quotes, backslashes, ';') it contains.
		$cfgFile = str_replace ('@@dbserver@@', var_export ($_POST ['dbserver'], true), $cfgFile);
		$cfgFile = str_replace ('@@dbport@@', var_export ((int) $_POST ['dbport'], true), $cfgFile);
		$cfgFile = str_replace ('@@dbuser@@', var_export ($_POST ['dbuser'], true), $cfgFile);
		$cfgFile = str_replace ('@@dbpass@@', var_export ($_POST ['dbpass'], true), $cfgFile);
		$cfgFile = str_replace ('@@dbname@@', var_export ($_POST ['dbname'], true), $cfgFile);
		$cfgFile = str_replace ('@@plgs@@', var_export ($_POST ['plgs'], true), $cfgFile);
		$cfgFile = str_replace ('@@skins@@', var_export ($_POST ['skins'], true), $cfgFile);

		$cfgFile = str_replace ('@@menuType@@', var_export ($_POST ['mnu'], true), $cfgFile);
		$cfgFile = str_replace ('@@authLog@@', isset ($_POST ['authLog']) ? 'TRUE' : 'FALSE', $cfgFile);
		$cfgFile = str_replace ('@@authRecover@@', isset ($_POST ['authRecover']) ? 'TRUE' : 'FALSE', $cfgFile);

		if (! file_put_contents (Site::$cfgFile, $cfgFile))
		{
			$outputMessage .= '<div class="fail">';
			$outputMessage .= '<b>Error</b>: Unable to save the config file<br />';
			$outputMessage .= 'Workaround: save the file ' . Site::$cfgFile . ' with this contents:<br /><pre>';
			$outputMessage .= $cfgFile;
			$outputMessage .= '</pre></div>';
			return FALSE;
		}
		else
		{
			$outputMessage .= '<div class="ok">Config file: <b>OK</b></div>';
		}

		return TRUE;
	}
}