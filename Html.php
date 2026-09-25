<?php

namespace PHPSiteEngine;

class Html
{
	public static function e ($value): string
	{
		return htmlspecialchars ((string) $value, ENT_QUOTES, 'UTF-8');
	}
}
