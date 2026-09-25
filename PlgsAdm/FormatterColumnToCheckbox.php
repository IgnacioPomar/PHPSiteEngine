<?php

namespace PHPSiteEngine\PlgsAdm;

class FormatterColumnToCheckbox
{


	/**
	 *
	 * @param mixed $val
	 * @param string $class
	 * @return string
	 */
	public function getSpan ($val, $class)
	{
		$retVal = "<div class='$class'>";

		$attributes = [ 'disabled'];
		if ($val)
		{
			$attributes [] = 'checked';
		}

		$retVal .= '<input type="checkbox" ' . join (' ', $attributes) . '>';
		$retVal .= '</div>';
		return $retVal;
	}
}
