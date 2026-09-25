<?php

namespace PHPSiteEngine;

class WebEngine
{
	private Context $context;

	// @formatter:off
	const HARDCODED_PLUGINS = array(
			// 'name' => 'path',
			'/Logout'		=> ['file' =>'PlgsStd/Logout.php', 'class' => 'Logout'],
			'/'				=> ['file' =>'PlgsStd/EmptyContent.php', 'class' => 'EmptyContent'],
	);
	
	// @formatter:on

	/**
	 *
	 * @param Context $context
	 * @param Menu $mnu
	 * @param bool $isAjax
	 */
	public static function launch (&$context)
	{
		$we = new WebEngine ();
		$we->context = &$context;

		$we->compose ();
	}


	/**
	 * Show the main body of the plugin and nothing else
	 */
	private function showAjaxBody ()
	{
		$plg = $this->loadPlugin ();
		print ($plg->main ());
	}


	/**
	 * Compose the page from its diufferent fragments
	 */
	private function compose ()
	{
		$plg = $this->loadPlugin ();
		$plgBody = $plg->main ();

		if ($this->context->isAjax)
		{
			print ($plgBody);
		}
		else
		{
			$layout = file_get_contents (Site::$templatePath . $this->context->mnu->getBaseTemplate ());

			// ---- Session data ----
			$layout = str_replace ('@@userName@@', htmlspecialchars_decode ($_SESSION ['userName']), $layout);

			// ---- Plugin data ----
			$this->setJsCall ($plg->getJsCall (), $layout);
			$this->setJsHeader ($plg->getExternalJs (), $layout);
			$this->setCssHeader ($plg->getExternalCss (), $layout);

			// ---- Web Engine base components ----
			$layout = str_replace ('@@Menu@@', $this->context->mnu->getMenu (), $layout);
			$layout = str_replace ('@@pageTitle@@', $this->context->mnu->getTitle (), $layout);
			$layout = str_replace ('@@skinPath@@', Site::$uriSkinPath, $layout);
			$layout = str_replace ('@@uriPath@@', Site::$uriPath, $layout);

			// ---- Finally, the body ----
			$layout = str_replace ('@@content@@', $plgBody, $layout);

			print ($layout);
		}
	}


	/**
	 * Returns the Plugin of the body.
	 * If there is no plugin, ends the execution
	 */
	private function loadPlugin (): Plugin
	{
		$mnu = &$this->context->mnu;

		if ($mnu->hasOpcSelected ())
		{
			$class = $mnu->getPlugin ();
			if ($resultado = $this->context->mysqli->query ("SELECT plgFile, plgParams, plgPerms FROM wePlugins WHERE plgName='$class';"))
			{
				if ($row = $resultado->fetch_assoc ())
				{
					require_once (Site::$plgsPath . $row ['plgFile']);
					$plg = new $class ($this->context);

					if (strlen ($row ['plgParams']) > 2)
					{
						$plg->checkParams ();
					}

					if (strlen ($row ['plgPerms']) > 2)
					{
						$plg->checkPerms ();
					}

					return $plg;
				}
			}
		}

		// No menu-configured plugin matched (either the URL has no menu option,
		// or it does but no plugin is registered for it yet): try a system plugin.
		if (isset (self::HARDCODED_PLUGINS [$mnu->subPath]))
		{
			$plgInfo = self::HARDCODED_PLUGINS [$mnu->subPath];

			require_once (Site::$nsPath . $plgInfo ['file']);
			$plgName = 'PHPSiteEngine\\PlgsStd\\' . $plgInfo ['class'];
			$plg = new $plgName ($this->context);
			// Harcoded plugins dont have nor params nor permmisions

			$mnu->stCurrOpc ([ 'opc' => $mnu->subPath, 'tmplt' => 'skel.htm', 'name' => $plgInfo ['class']]);

			return $plg;
		}

		// If we arrive here, means we have no plugin installed
		require_once (Site::$nsPath . 'Fake404.php');
		Fake404::main ();
	}


	/**
	 * Emplaces the content of the javascript call at the end of the page
	 *
	 * @param string $jsCall
	 * @param string $output
	 */
	private static function setJsCall ($jsCall, &$output)
	{
		$isAnyJsCall = FALSE;
		$jsCallContents = '';
		if ($jsCall != '') // avoid null files
		{
			$jsCallFile = Site::$skinPath . 'js/' . $jsCall;
			if (file_exists ($jsCallFile))
			{
				$jsCallContents = '<script type="text/javascript">';
				$jsCallContents .= file_get_contents ($jsCallFile);
				$jsCallContents .= '</script>';
				$isAnyJsCall = TRUE;
			}
		}

		if ($isAnyJsCall)
		{
			$output = str_replace ('</body>', $jsCallContents . '</body>', $output);
		}
	}


	/**
	 *
	 * @param array $jsFiles
	 * @param string $output
	 */
	private static function setJsHeader ($jsFiles, &$output)
	{
		$jsHeader = '';
		foreach ($jsFiles as $jsFile)
		{
			if (strpos ($jsFile, '://') !== false)
			{
				$jsPath = $jsFile;
			}
			else
			{
				$jsPath = Site::$uriSkinPath . "js/$jsFile";
			}

			$extension = substr (strrchr ($jsPath, '.'), 1);
			if ($extension == 'mjs')
			{
				$jsHeader .= '<script type="module" src="' . $jsPath . '"></script>' . PHP_EOL;
			}
			else
			{
				$jsHeader .= '<script type="text/javascript" src="' . $jsPath . '"></script>' . PHP_EOL;
			}
		}

		$output = str_replace ('</head>', $jsHeader . '</head>', $output);
	}


	/**
	 *
	 * @param array $cssFiles
	 * @param string $output
	 */
	private static function setCssHeader ($cssFiles, &$output)
	{
		$cssHeader = '';
		foreach ($cssFiles as $cssFile)
		{
			if (strpos ($cssFile, '://') !== false)
			{
				$cssPath = $cssFile;
				$cssType = '';
			}
			else
			{
				$cssPath = Site::$uriSkinPath . "css/$cssFile";
				$cssType = 'type="text/css"';
			}

			// $cssPath = "./skins/$skin/css/$cssFile";
			$cssHeader .= '<link rel="stylesheet" ' . $cssType . ' href="' . $cssPath . '">' . PHP_EOL;
		}
		$output = str_replace ('</head>', $cssHeader . '</head>', $output);
	}
}