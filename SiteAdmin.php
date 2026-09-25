<?php

namespace PHPSiteEngine;

require_once ('Site.php');
require_once ('Context.php');
require_once ('Plugin.php');
require_once ('Installer.php');
require_once ('MenuLoaderJson.php');
require_once ('MenuLoaderDB.php'); // if ($GLOBALS ['menuType'] != 0)
require_once ('Auth.php');

// Include for the admin plugins
require_once ('Html.php');
require_once ('ColumnFormatter.php');
require_once ('AutoForm.php');
require_once ('PlgsAdm/FormatterColumnToCheckbox.php');

class SiteAdmin
{

	// @formatter:off
    const ADMIN_PLUGINS = array(
        // 'name' => 'path',
        'ReinstallCore'		=> 'PlgsAdm/ReinstallCore.php',
		'ReinstallPlugins'	=> 'PlgsAdm/ReinstallPlugins.php',
    		
		'MaintenanceUsers' 	=> 'PlgsAdm/MaintenanceUsers.php',
		'MaintenanceGroups'	=> 'PlgsAdm/MaintenanceGroups.php',
		'EditPermissions'	=> 'PlgsAdm/EditPermissions.php',
		'EditMenu'			=> 'PlgsAdm/EditMenu.php'
    );

    // @formatter:on
	private Menu $mnuAdmin;
	private Context $context;
	private ?string $userId;


	public function __construct ()
	{
		$this->context = new Context ();
		// we set the userId to get all menu from the db
		$this->context->userId = NULL;

		// Load the admin Menu
		$this->mnuAdmin = new Menu ();
		MenuLoaderJson::loadFromFile (Site::$rscPath . 'adminMenu.json', $this->mnuAdmin);
	}


	/**
	 * Establish connection to database
	 *
	 * @return boolean false if database connection failed
	 */
	function connectDb ()
	{
		$this->context->mysqli = new \mysqli ($GLOBALS ['dbserver'], $GLOBALS ['dbuser'], $GLOBALS ['dbpass'], $GLOBALS ['dbname'], $GLOBALS ['dbport']);
		if ($this->context->mysqli->connect_errno)
		{

			$outputMessage = '<div class="fail">';
			$outputMessage .= '<b>Error</b>: Database connection Failed.<br />';
			$outputMessage .= 'Error number: ' . $this->context->mysqli->connect_errno . '.<br />';
			$outputMessage .= 'Error Description: ' . $this->context->mysqli->connect_error . '.<br />';
			$outputMessage .= '</div>';

			$outputMessage .= '<div class="fail">You must <b>FIX the site_cfg</b> file.</div>';

			echo $outputMessage;
			return false;
		}

		// $mysqli->query ("SET NAMES 'UTF8'");
		$this->context->mysqli->set_charset ("utf8");

		return true;
	}


	/**
	 * Check if the installation is working fine
	 *
	 * @return boolean false if we called the installer
	 */
	private function checkInstallation ()
	{
		return $this->connectDb () && $this->checkBaseTables ();
	}


	/**
	 * Check if this is a new installation with a manual site_cfg file
	 *
	 * @return boolean false if we called the installer
	 */
	private function checkBaseTables ()
	{
		$sql = 'SELECT 1 FROM weUsers LIMIT 1;';
		try
		{
			$resultado = $this->context->mysqli->query ($sql);
		}
		catch (\mysqli_sql_exception $e)
		{
			$resultado = false;
		}

		if (! $resultado)
		{
			$installer = new Installer ($this->context->mysqli);
			$installer->installWithConfigFile ();
			return false;
		}
		else
		{
			$resultado->close ();
			return true;
		}
	}


	/**
	 * Check if this is a fresh installation
	 *
	 * @return boolean false if we called the installer
	 */
	private static function checkFileCfg ()
	{
		if (! file_exists (Site::$cfgFile))
		{
			Installer::installFromScratch ();

			return false;
		}

		// WE have the config File
		include_once (Site::$cfgFile);
		Site::initCfg ();

		return true;
	}


	/**
	 * Show the view of the selected option in the admin menu
	 */
	private function showAdminView ()
	{
		// If the installation is done in a subdirectory, it would not load the styles correctly
		$head = '<head>';
		$head .= "<link rel='stylesheet' type='text/css' href='" . Site::$rscUriPath . "css/admin.css'>";

		$body = '</head><body>';
		$body .= "<div id='toolbar'>{$this->mnuAdmin->getMenu ($this->context->mnu)}</div>";
		$body .= '<div id="mainContainer">';

		$plgName = $this->mnuAdmin->getPlugin ();

		if (! empty ($plgName) && isset (self::ADMIN_PLUGINS [$plgName]))
		{
			require_once (Site::$nsPath . self::ADMIN_PLUGINS [$plgName]);
			$plgName = 'PHPSiteEngine\\PlgsAdm\\' . $plgName;
			$plg = new $plgName ($this->context);

			// Add admin module javascript
			foreach ($plg->getExternalJs () as $jsFile)
			{
				$head .= "<script type='text/javascript' src='" . Site::$rscUriPath . "js/$jsFile' ></script>";
			}

			// Show module
			$body .= $plg->main ();
		}
		else
		{
			$body .= '<h1>Admin area</h1><p>You can use the menu.</p>';
		}

		$body .= '</div>';
		return $head . $body;
	}


	/**
	 * Check user login
	 *
	 * @return boolean false if no user or user without admin role
	 */
	private function checkAdminAuth ()
	{
		session_start ();
		$this->userId = Auth::setupLogin ($this->context->mysqli);

		if ($this->userId !== NULL)
		{
			$this->context->userId = $this->userId;

			// Check if we currently have an admin rights
			if (Auth::isAdmin ($this->context->mysqli, $this->userId))
			{
				return TRUE;
			}
			else
			{
				echo 'You need ADMIN privileges to enter here';

				unset ($_SESSION);
				session_unset ();
				session_destroy ();
				$_SESSION ['userName'] = '';

				return FALSE;
			}
		}
		else
		{
			return FALSE;
		}
	}


	private function loadWebMenu ()
	{
		$this->context->mnu = new Menu ();
		if ($GLOBALS ['menuType'] == 0)
		{
			MenuLoaderJson::load ($this->context, $this->context->mnu);
		}
		else
		{
			MenuLoaderDB::load ($this->context, $this->context->mnu);
		}
	}


	/**
	 * Entry Point
	 */
	public static function main ($rootPath, $cfgFile)
	{
		Site::init ($rootPath, $cfgFile);
		if (self::checkFileCfg ())
		{
			$adminModule = new SiteAdmin ();

			if ($adminModule->checkInstallation () && $adminModule->checkAdminAuth ())
			{
				if ($GLOBALS ['Version'] != Site::VERSION)
				{
					if (isset ($_POST ['installNewVersion']) && $_POST ['installNewVersion'] == 'true')
					{
						include_once (Site::$nsPath . 'Migrator.php');
						Migrator::migrate ($adminModule->context);
					}
					else
					{
						echo file_get_contents (Site::$rscPath . 'html/migrationForm.html');
					}
				}
				else
				{
					$adminModule->loadWebMenu ();
					echo $adminModule->showAdminView ();
				}
			}
		}
	}
}




