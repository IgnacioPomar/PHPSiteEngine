<?php

namespace PHPSiteEngine\PlgsAdm;

use PHPSiteEngine\Plugin;
use PHPSiteEngine\Installer;

class ReinstallCore extends Plugin
{


	public static function getPlgInfo (): array
	{
		$plgInfo = array ();
		$plgInfo ['plgDescription'] = "Reinstala las tablas principales del núcleo del sistema.";
		$plgInfo ['isMenu'] = 1;
		$plgInfo ['perms'] = '[]';
		$plgInfo ['params'] = '[]';

		return $plgInfo;
	}


	public function main ()
	{
		$installer = new Installer ($this->context->mysqli);

		$retVal = '<h1>Reinstalling Core Tables</h1>';
		$retVal .= $installer->createCoreTables ();
		return $retVal;
	}
}