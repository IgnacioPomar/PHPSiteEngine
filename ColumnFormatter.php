<?php

namespace PHPSiteEngine;

/**
 * This class helps column order and format for reports
 */
class ColumnFormatter
{
	/*
	 * Must have the following format:
	 * colsDef = array (
	 * 'aaa' => array ('bbb', 'ccc', 'ddd'),
	 * );
	 *
	 * WHERE:
	 * 'aaa': index of the column array
	 * 'bbb': CSS class for the field
	 * 'ccc': Header name of the column
	 * 'ddd': Alt tag for the header
	 */
	private $colsDef;
	public $stylers;

	// ASCII varse
	private $out;
	private $browserFileName;
	const FIELD_DELIMITER = ';';
	const FIELD_ENCLOSURE = '"';


	/**
	 * Constructor
	 *
	 * @param array $colsDef
	 *        	Column definition
	 */
	public function __construct (array $colsDef)
	{
		$this->colsDef = $colsDef;
		$this->stylers = array ();
	}


	public function openAscii ($browserFileName)
	{
		$this->out = fopen ('php://memory', 'rw+');
		$this->browserFileName = $browserFileName;

		fwrite ($this->out, chr (239) . chr (187) . chr (191));
	}


	public function asciiSend ()
	{
		header ('Pragma: public');
		header ('Expires: 0');
		header ('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header ('Cache-Control: private', false); // required for certain browsers.
		header ('Content-Type: text/csv');

		header ('Content-Disposition: attachment; filename="' . $this->browserFileName . '";');
		header ('Content-Transfer-Encoding: binary');

		fseek ($this->out, 0);
		$out = stream_get_contents ($this->out);
		// $out = str_replace ( '.', ',', $out ); Se hace cuando se generan los resultados

		header ('Content-Length: ' . strlen ($out));
		print ($out);

		exit ();
	}


	public function asciiAddHeader ()
	{
		$cabecera = array ();
		foreach ($this->colsDef as $col)
		{
			$cabecera [] = html_entity_decode ($col [1]);
		}

		fputcsv ($this->out, $cabecera, self::FIELD_DELIMITER, self::FIELD_ENCLOSURE);
	}


	public function getHeaderCols ()
	{
		$retVal = '';
		foreach ($this->colsDef as $col)
		{
			$retVal .= '<span class="' . $col [0] . '" title="' . $col [2] . '">' . $col [1] . '</span>';
		}
		return $retVal;
	}


	public function getHeaderColsWithOrdLink ($prelink)
	{
		$retVal = '';
		foreach ($this->colsDef as $idCol => $col)
		{
			$retVal .= '<a href="' . $prelink . $idCol . '"><span class="' . $col [0] . '" title="' . $col [2] . '">' . $col [1] . '</span></a>';
		}
		return $retVal;
	}


	public function getHeaderColsWithAutoOrder ($prelink, $orderTag = 'order', $dirTag = 'dir')
	{
		$currOrder = $_GET [$orderTag] ?? '';
		$currDir = $_GET [$dirTag] ?? 'ASC';
		$retVal = '';
		foreach ($this->colsDef as $idCol => $col)
		{
			$link = $prelink . $orderTag . '=' . $idCol;
			$class = '';
			if ($currOrder == $idCol)
			{
				$class = ' orderBy';
				$link .= '&' . $dirTag;
				if ($currDir == 'ASC')
				{
					$class .= ' ordAsc';
					$link .= '=DESC';
				}
				else
				{
					$class .= ' ordDesc';
					$link .= '=ASC';
				}
			}
			$retVal .= '<a href="' . $link . '"><span class="' . $col [0] . $class . '" title="' . $col [2] . '">' . $col [1] . '</span></a>';
		}
		return $retVal;
	}


	public function asciiAddLine (array $row)
	{
		$line = array ();
		foreach (array_keys ($this->colsDef) as $fld)
		{
			$val = $row [$fld] ?? '';

			$line [] = html_entity_decode ($val);
		}

		fputcsv ($this->out, $line, self::FIELD_DELIMITER, self::FIELD_ENCLOSURE);
	}


	public function getBodyCols (array $row)
	{
		$retVal = '';
		foreach ($this->colsDef as $fld => $col)
		{
			$val = '';
			if (isset ($row [$fld]))
			{
				$val = $row [$fld];
			}

			$retVal .= '<span class="' . $col [0] . '">' . Html::e ($val) . '</span>';
		}
		return $retVal;
	}


	public function getStyledBodyCols (array $row)
	{
		$retVal = '';
		foreach ($this->colsDef as $fld => $col)
		{
			$val = '';
			if (isset ($row [$fld]))
			{
				$val = $row [$fld];
			}

			if (isset ($this->stylers [$fld]))
			{
				$sty = &$this->stylers [$fld];
				$retVal .= $sty->getSpan ($val, $col [0]);
			}
			else
			{
				$retVal .= '<span class="' . $col [0] . '">' . Html::e ($val) . '</span>';
			}
		}
		return $retVal;
	}
}
