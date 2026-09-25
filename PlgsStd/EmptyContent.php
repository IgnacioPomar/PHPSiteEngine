<?php

namespace PHPSiteEngine\PlgsStd;

use PHPSiteEngine\Plugin;

/*
 * Empty-content placeholder plugin, used as a system fallback when
 * a menu option has no real plugin registered yet. Not tied to any
 * specific route, it just renders nothing.
 */
class EmptyContent extends Plugin
{


	public function main ()
	{
		return '';
	}


	public static function getPlgInfo (): array
	{
		$plgInfo = array ();
		$plgInfo ['plgDescription'] = "Empty placeholder content";
		$plgInfo ['isMenu'] = 0;
		$plgInfo ['perms'] = '[]';
		$plgInfo ['params'] = '[]';

		return $plgInfo;
	}
}
