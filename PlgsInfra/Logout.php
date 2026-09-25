<?php

namespace PHPSiteEngine\PlgsInfra;

use PHPSiteEngine\Plugin;

class Logout extends Plugin
{


	public function main ()
	{
		if (isset ($_COOKIE ['SecurityCookie']))
		{

			$cookie = explode ("@", $_COOKIE ['SecurityCookie']);
			if (isset ($cookie [1]))
			{
				// DElete from Database
				$consulta = 'DELETE FROM weSessCookie WHERE cookieId = ? AND cookiePass = ?';
				$stmt = $this->context->mysqli->prepare ($consulta);
				$stmt->bind_param ('ss', $cookie [0], $cookie [1]);
				$stmt->execute ();
			}

			// Delete in the browser
			unset ($_COOKIE ['SecurityCookie']);
			setcookie ('SecurityCookie', '', - 1, '/');
		}

		if (! isset ($_SESSION)) session_start ();
		unset ($_SESSION);
		session_unset ();
		session_destroy ();
		$_SESSION ['userName'] = '';

		return '<h2 class="warning">Logout sucess</h2>';
	}


	public function getExternalCss ()
	{
		$css = array ();
		$css [] = 'gioMain.css';
		$css [] = 'groupList.css';
		return $css;
	}


	public static function getPlgInfo (): array
	{
		$plgInfo = array ();
		$plgInfo ['plgDescription'] = "Logs the current user out";
		$plgInfo ['isMenu'] = 1;
		$plgInfo ['perms'] = '[]';
		$plgInfo ['params'] = '[]';

		return $plgInfo;
	}
}

