<?php

namespace PHPSiteEngine;

require_once ('Site.php');
require_once ('Context.php');
require_once ('Plugin.php');
require_once ('WebEngine.php');
require_once ('Auth.php');

// Include for the Plugin
require_once ('Html.php');
require_once ('ColumnFormatter.php');
require_once ('AutoForm.php');

class SiteLauncher
{


	/**
	 * Establish connection to database
	 *
	 * @return boolean false if database connection failed
	 */
	private static function connectDb (&$context)
	{
		$context->mysqli = new \mysqli ($GLOBALS ['dbserver'], $GLOBALS ['dbuser'], $GLOBALS ['dbpass'], $GLOBALS ['dbname'], $GLOBALS ['dbport']);
		if ($context->mysqli->connect_errno)
		{
			print ('Database conectiopn failed. Wait a minute, or contact with a administrator.');
			print ('Errno: ' . $context->mysqli->connect_errno . '<br />');
			print ('Error: ' . $context->mysqli->connect_error . '<br />');

			return false;
		}

		$context->mysqli->set_charset ("utf8");
		// $mysqli->query ("SET NAMES 'UTF8'");

		return true;
	}


	/**
	 * Check if installation is OK
	 *
	 * @return boolean false if There is no config file, or if version does not match
	 */
	private static function checkInstallation ()
	{
		if (! file_exists (Site::$cfgFile))
		{
			echo 'Waiting installation';
			return false;
		}

		include_once (Site::$cfgFile);
		Site::initCfg ();

		if ($GLOBALS ['Version'] != Site::VERSION)
		{
			if (file_exists (Site::$templatePath . 'maintenance.html'))
			{
				echo file_get_contents (Site::$templatePath . 'maintenance.html');
			}
			else
			{
				echo file_get_contents (Site::$rscPath . 'skinTmplt/maintenance.html');
			}
			return false;
		}
		return true;
	}


	/**
	 * Check the user credentials
	 *
	 * @return boolean false If there is no valid user logged
	 */
	private static function checkAuth (&$context)
	{
		session_start ();

		// Check Auth
		$context->userId = Auth::login ($context->mysqli);

		return ($context->userId !== NULL);
	}


	/**
	 * Entry point
	 */
	public static function main ($rootPath, $cfgFile)
	{
		Site::init ($rootPath, $cfgFile);

		$context = new Context ();
		if (self::checkInstallation () && self::connectDb ($context) && self::checkAuth ($context))
		{

			// load the web Menu
			$context->mnu = new Menu ();
			if ($GLOBALS ['menuType'] == 0)
			{
				require_once ('MenuLoaderJson.php');
				MenuLoaderJson::load ($context, $context->mnu);
			}
			else
			{
				require_once ('MenuLoaderDB.php');
				MenuLoaderDB::load ($context, $context->mnu);
			}

			$context->isAjax = isset ($_GET ['ajax']);
			WebEngine::launch ($context);
		}
	}
}



