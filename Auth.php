<?php

namespace PHPSiteEngine;

class Auth
{
	private $userId;
	private $mysqli;
	private $errorInfo = '';
	private $errorCode;

	// Configurated behaviours
	private bool $keepLogged;
	private bool $allowRecover;
	private bool $allowAppLogins;


	/**
	 * Constructor: init the configurated behaviours
	 */
	public function __construct ()
	{
		$this->keepLogged = $GLOBALS ['authKeepLogged'] ?? true;
		$this->allowRecover = $GLOBALS ['authAllowRecover'] ?? false;
		$this->allowAppLogins = $GLOBALS ['authAllowAppLogins'] ?? false;
	}


	/**
	 * A login for the admin space.
	 * It wont use the skin at all
	 */
	public static function setupLogin ($mysqli): ?string
	{
		if (isset ($_SESSION ['userId']))
		{
			return $_SESSION ['userId'];
		}

		$auth = new Auth ();
		$auth->userId = NULL;
		$auth->mysqli = $mysqli;

		if ($auth->checkLocallogin ()) return $auth->userId;

		$auth->showSetupLoginForm ();

		return $auth->userId;
	}


	/**
	 * Check if the user is logged Or is logging
	 *
	 * @param integer $userId
	 * @return boolean
	 */
	public static function login ($mysqli): ?string
	{
		// 0.- WE are already in session
		if (isset ($_SESSION ['userId']))
		{
			return $_SESSION ['userId'];
		}

		$auth = new Auth ();
		$auth->userId = NULL;
		$auth->mysqli = $mysqli;

		if ($auth->allowRecover)
		{
			$auth->disassembleRecoveryLink ();
			if (isset ($_POST ['recoverPass']))
			{
				return $auth->recoverPass ();
			}
		}

		// 1.- Check if there is a cookie with a valid session
		if ($auth->checkCookieslogin ()) return $auth->userId;

		// 2.- TODO: check if there is a valid google or facebook or Office365 login
		// 3.- Check if the form has been submitted
		if ($auth->checkLocallogin ()) return $auth->userId;

		if ($auth->allowAppLogins)
		{
			// 4.- Check if it is an internal call from inside the site
			if ($auth->checkInternalCall ()) return $auth->userId;

			// 5.- Check if it is a direct app call
			if ($auth->checkAppCall ()) return $auth->userId;
		}

		// TODO: Check if there is a valid certificate in the machine
		// https://webauthn.guide/#webauthn-api

		// ---------- We dont have a valid user ----------
		// Show the login form
		$auth->showLoginForm ();
		return NULL;
	}


	/**
	 * Check if the user is an admin
	 *
	 * @param integer $userId
	 * @return boolean
	 */
	public static function isAdmin ($mysqli, $userId): bool
	{
		$retVal = false;
		$consulta = 'SELECT isAdmin FROM weUsers WHERE idUser = ?';

		$stmt = $mysqli->prepare ($consulta);
		$stmt->bind_param ('s', $userId);
		$stmt->execute ();
		if ($res = $stmt->get_result ())
		{
			if ($row = $res->fetch_assoc ())
			{
				$retVal = ($row ['isAdmin'] == 1);
			}
			$res->free_result ();
		}
		return $retVal;
	}


	/**
	 * Check if the user is still active
	 *
	 * @param integer $userId
	 * @return boolean
	 */
	private function checkIfUserIsActive ($userId): bool
	{
		$retVal = FALSE;
		$consulta = 'SELECT isActive, name FROM weUsers WHERE idUser = ?';

		$stmt = $this->mysqli->prepare ($consulta);
		$stmt->bind_param ('s', $userId);
		$stmt->execute ();
		if ($resultado = $stmt->get_result ())
		{
			if ($resultado->num_rows > 0)
			{
				$tupla = $resultado->fetch_object ();

				if ($tupla->isActive == 1)
				{
					$retVal = TRUE;

					$this->userId = $_SESSION ['userId'] = $userId;
					$_SESSION ['userName'] = $tupla->name;
				}
			}

			$resultado->free_result ();
		}

		return $retVal;
	}


	/**
	 * Extend the coookie another 30 days
	 *
	 *
	 * @param integer $userId
	 * @return boolean
	 */
	private function extendCoockieLife ($cookieId)
	{
		$sql = 'UPDATE weSessCookie SET expires= NOW() + INTERVAL 30 DAY WHERE cookieId = ?';
		$stmt = $this->mysqli->prepare ($sql);
		$stmt->bind_param ('s', $cookieId);
		$stmt->execute ();
	}


	/**
	 * DElete old coockies
	 */
	private function deleteOldCookies ()
	{
		$sql = 'DELETE FROM weSessCookie WHERE expires < NOW();';
		$this->mysqli->query ($sql);
	}


	/**
	 * Nos aseguramos de que la base de datos admita un mayor tiempo de vida a esta identificación
	 *
	 *
	 * @param integer $userId
	 * @return boolean
	 */
	private function checkStaticPass ($staticPass, $extendLifeTime, $useCookie)
	{
		list ($cookieId, $cookiePass) = explode ('@', $staticPass);

		$consulta = 'SELECT cookiePass, realUserId FROM weSessCookie WHERE cookieId = ?';

		// TODO: Considerar usar la información de browser INFo para comprobar si es una sesión válida
		$retVal = false;
		$stmt = $this->mysqli->prepare ($consulta);
		$stmt->bind_param ('s', $cookieId);
		$stmt->execute ();
		if ($resultado = $stmt->get_result ())
		{
			if ($resultado->num_rows > 0)
			{
				$tupla = $resultado->fetch_object ();

				if (($tupla->cookiePass === $cookiePass) && ($this->checkIfUserIsActive ($tupla->realUserId)))
				{

					$retVal = true;

					if ($extendLifeTime)
					{
						$this->extendCoockieLife ($cookieId);
						if ($useCookie)
						{
							setcookie ("SecurityCookie", $cookieId . '@' . $cookiePass, strtotime ('+30 days'));
						}
					}
				}
			}

			$resultado->free ();
		}
		return $retVal;
	}


	/**
	 * Obtenemos un parametro get para usar autentificación internamente.
	 * IMPORTANTE: No mostrar jamas esto de cara al usuario, nos basamos en secureCookie
	 *
	 * @return string parametro a añadir a la secuencia GET
	 */
	public static function getInternalCallParam ()
	{
		return 'localhostId=' . $_COOKIE ['SecurityCookie'];
	}


	/**
	 * Intentamos hacer la autentificacion mediante cookie
	 *
	 * @return boolean TRUE en caso de que la autentificaci´n sea correcta
	 */
	private function checkCookieslogin ()
	{
		$retVal = FALSE;

		if ($this->keepLogged && isset ($_COOKIE ['SecurityCookie']))
		{
			// Comprobamos si la cookie tiene el formato deseado
			if (strpos ($_COOKIE ['SecurityCookie'], '@') !== false)
			{
				// Primero eliminamos las cookies caducadas
				$this->deleteOldCookies ();

				$retVal = $this->checkStaticPass ($_COOKIE ['SecurityCookie'], true, true);
			}
		}

		return $retVal;
	}


	/**
	 * Comprobamos si es una llamada interna desde el propio motor
	 * parametro de salida: Identificador del usuario que hace login
	 *
	 * @return boolean TRUE en caso de que la autentificación sea correcta
	 */
	private function checkInternalCall ()
	{
		$retVal = FALSE;

		// TODO: comprobar si conviene codificar en base 64
		// TODO: Comprobar además que sólo se ejecuta desde localhost

		/*
		 * $whitelist = array (
		 * '127.0.0.1',
		 * '::1'
		 * );
		 * if (! in_array ( $_SERVER ['REMOTE_ADDR'], $whitelist ))
		 * {
		 * // not valid
		 * }
		 */

		if (isset ($_GET ['localhostId']))
		{
			$secureCookie = $_GET ['localhostId'];

			if (strpos ($secureCookie, '@') !== false)
			{
				$retVal = $this->checkStaticPass ($secureCookie, false, false);
			}
		}
		return $retVal;
	}


	/**
	 * Sistema de auth pensado para apps externas (excel, metatrader, etc...)
	 * Consideramos que estas aplicaciones carecen de cookies, y por ende, de sesión
	 *
	 * @return boolean TRUE en caso de que la autentificación sea correcta
	 */
	private function checkAppCall ()
	{
		if (isset ($_POST ['AppIdAction']))
		{
			// Nos aseguramos de que se trate como AJAX, siempre
			$_GET ['ajax'] = 1;

			if ($_POST ['AppIdAction'] == 'login')
			{
				$loginRetval = 'KO'; // . json_encode ($_POST);
				if ((isset ($_POST ['appIdUser'])) && (isset ($_POST ['appIdPass'])))
				{

					$sql = 'SELECT idUser, password FROM weUsers WHERE isActive=1 AND email=?';
					$stmt = $this->mysqli->prepare ($sql);
					$stmt->bind_param ('s', $_POST ['appIdUser']);
					$stmt->execute ();
					if ($res = $stmt->get_result ())
					{
						if ($row = $res->fetch_assoc ())
						{
							if (password_verify ($_POST ['appIdPass'], $row ['password']))
							{
								$userId = $row ['idUser'];
								$loginRetval = 'OK:';
								$loginRetval .= $this->saveCookieSession ($this->mysqli, $userId);
							}
						}
					}
				}
				exit ($loginRetval);
			}
			else
			{
				$retval = false;
				$keyToken = $_POST ['AppIdToken'];
				if (strpos ($keyToken, '@') !== false)
				{
					if ($this->checkStaticPass ($keyToken, true, false))
					{
						$retval = true;
					}
				}

				if ($_POST ['AppIdAction'] == 'check')
				{
					// Estamos comprobando la auth (en cuyo caso extendemos la vida)
					// Rompe el flujo y responde directamente
					if ($retval)
					{
						exit ('OK');
					}
					else
					{
						exit ('KO');
					}
				}
				else if ($_POST ['AppIdAction'] == 'std')
				{
					// Uso normal: comprobamos el id de usuario y seguimos
					// Esta debe seguir el flujo en caso de que haya ido ok
					if ($retval)
					{
						return $retval;
					}
					else
					{
						exit ('KO: auth fail');
					}
				}

				// TODO: Esto es delicado, por loq ue habría que comprobar que un usuario no cede sus credenciales a un tercero
				/*
				 * Por ello, lo que haremos será crear una aplicación que se ejecutará y registrará en el dominio, y esta, a su vez,
				 * generará un token de seguridad exclusivo que se almacenará en una ubicación accesible por excel (en el registro).
				 *
				 * Pasaríamos este token como una cabecera más de HTTP (lo leeríamos con la función de PHP get_headers)
				 */
			}
		}
	}


	/**
	 * Guardamos en la base de datos un identificador de la sesión, y mandamos una cookie al usuario
	 *
	 * @param integer $userId
	 *        	Identificador real del usuario
	 */
	private function saveCookieSession ($setCookie, $userId = NULL)
	{
		// Skip if we are not allowed to keep the user logged
		if (! $this->keepLogged) return '';

		$userId = $userId ?? $this->userId;

		// Primero obtenemos los valores unicos para esta sesion
		$cookieId = uniqid ('', true); // menos de 30 caracteres
		$cookiePass = mt_rand (100000000, 999999999);
		$browserId = $_SERVER ['HTTP_USER_AGENT'];

		$consulta = 'INSERT INTO weSessCookie (cookieId,cookiePass,realUserId,firstAccess,expires,browserInfo)
		VALUES (?,?,?,NOW(),NOW() + INTERVAL 30 DAY,?);
		';

		$stmt = $this->mysqli->prepare ($consulta);
		$cookiePassStr = (string) $cookiePass;
		$stmt->bind_param ('ssss', $cookieId, $cookiePassStr, $userId, $browserId);

		if ($stmt->execute ())
		{
			if ($setCookie)
			{
				setcookie ("SecurityCookie", $cookieId . '@' . $cookiePass, strtotime ('+30 days'));
			}

			return $cookieId . '@' . $cookiePass;
		}
		return '';
	}


	/**
	 *
	 * @return boolean
	 */
	public function checkLocallogin ()
	{
		$retVal = FALSE;
		if (isset ($_POST ['user']))
		{
			$consulta = 'SELECT idUser, name, password, isActive FROM weUsers WHERE email=?';

			$stmt = $this->mysqli->prepare ($consulta);
			$stmt->bind_param ('s', $_POST ['user']);
			$stmt->execute ();
			if ($resultado = $stmt->get_result ())
			{
				if ($resultado->num_rows > 0)
				{
					$tupla = $resultado->fetch_object ();

					if (password_verify ($_POST ['password'], $tupla->password))
					{
						if ($tupla->isActive == 1)
						{
							$this->userId = $_SESSION ['userId'] = $tupla->idUser;
							$_SESSION ['userName'] = $tupla->name;

							$this->saveCookieSession (true);
							$retVal = TRUE;
						}
						else
						{
							$this->errorInfo = 'Cuenta caducada';
						}
					}
					else
					{
						$this->errorInfo = 'Usuario o contraseña incorrectos';
					}
				}
				else
				{
					$this->errorInfo = 'Usuario o contraseña incorrectos';
				}
				$resultado->free ();
			}
		}
		return $retVal;
	}


	/**
	 * Mostramos el formulario para poder hacer login
	 *
	 * @param string $lang
	 */
	private function showFinalLoginForm ($file)
	{
		$loginForm = file_get_contents ($file);
		$loginForm = str_replace ('@@uriPath@@', Site::$uriPath, $loginForm);
		$loginForm = str_replace ('@@skinPath@@', Site::$uriSkinPath, $loginForm);
		$loginForm = str_replace ('@@rscUriPath@@', Site::$rscUriPath, $loginForm);
		$loginForm = str_replace ('@@errorInfo@@', $this->errorInfo, $loginForm);

		header ('Content-Type: text/html; charset=utf-8');
		print ($loginForm);
	}


	/**
	 * Mostramos el formulario para poder hacer login
	 *
	 * @param string $lang
	 */
	public function showLoginForm ()
	{
		if (file_exists (Site::$skinPath . 'tmplt/loginForm.htm'))
		{
			$this->showFinalLoginForm (Site::$skinPath . 'tmplt/loginForm.htm');
		}
		else
		{
			$this->showFinalLoginForm (Site::$rscPath . 'skinTmplt/loginForm.htm');
		}
	}


	public function showSetupLoginForm ()
	{
		$this->showFinalLoginForm (Site::$rscPath . 'html/setupLoginForm.htm', '');
	}


	/**
	 * Drop Coockies and unset session, so we can logout
	 *
	 * @param string $lang
	 */
	public function logout ()
	{
		if (isset ($_COOKIE ['SecurityCookie']))
		{

			$cookie = explode ("@", $_COOKIE ['SecurityCookie']);
			if (isset ($cookie [1]))
			{
				// Primero eliminamos nuestra cookie actual
				$consulta = 'DELETE FROM weSessCookie WHERE cookieId = ? AND cookiePass = ?';
				$stmt = $this->mysqli->prepare ($consulta);
				$stmt->bind_param ('ss', $cookie [0], $cookie [1]);
				$stmt->execute ();
			}
		}

		if (! isset ($_SESSION)) session_start ();
		unset ($_SESSION);
		session_unset ();
		session_destroy ();

		header ("location:index.php");

		exit ();
	}


	// ----------------- PAssword Recovery Funcions: start -----------------

	/**
	 * The email recover link comes obscured to make it easier...
	 * so, for easy development, we kei it look like a std call
	 *
	 * @param integer $userId
	 * @return boolean
	 */
	private function disassembleRecoveryLink ()
	{
		if (isset ($_GET ['recover']))
		{
			$prms = array ();
			parse_str (base64_decode (strtr ($_GET ['recover'], '-_', '+/')), $prms);
			if (isset ($prms ['rP']) && isset ($prms ['rE']) && isset ($prms ['CR']))
			{
				$_POST ['recoverPass'] = $prms ['rP'];
				$_POST ['recoverEmail'] = $prms ['rE'];
				$_GET ['codRec'] = strtr ($prms ['CR'], '+/', '-_');
			}
		}
	}


	/**
	 * Password recovery flow
	 *
	 *
	 * @return NULL
	 */
	private function recoverPass ()
	{
		if (isset ($_POST ['recoverPass']) && isset ($_POST ['recoverEmail']))
		{

			if (isset ($_POST ['codRec']))
			{
				// The form has been posted with the new password
				// TODO: check if the password has the requiired strength
				return $this->checkAndStoreNewPass ();
			}
			else
			{
				$recoverFormPassFile = Site::$skinPath . 'tmplt/recoverFormPass.htm';
				if (! file_exists ($recoverFormPassFile))
				{
					$recoverFormPassFile = Site::$rscPath . 'html/recoverFormPass.htm';
				}

				$loginEmailForm = file_get_contents ($recoverFormPassFile);
				$loginEmailForm = str_replace ('@@email@@', $_POST ['recoverEmail'], $loginEmailForm);

				if (isset ($_GET ['codRec']))
				{
					// Comming from the email recover link. Show the form to reset the code
					$loginEmailForm = str_replace ('@@codRec@@', $_GET ['codRec'], $loginEmailForm);
				}
				else
				{
					// WE have the email... set a new recovery code and send by email
					$this->launchRecoverEmail ($_POST ['recoverEmail']);
					$loginEmailForm = str_replace ('@@codRec@@', '', $loginEmailForm);
				}

				header ('Content-Type: text/html; charset=utf-8');
				print ($loginEmailForm);
			}
		}
		else
		{
			// Comming from a link in the the login form: ask for the email
			$recoverFormEmailFile = Site::$skinPath . 'tmplt/recoverFormEmail.htm';
			if (! file_exists ($recoverFormEmailFile))
			{
				$recoverFormEmailFile = Site::$rscPath . 'html/recoverFormEmail.htm';
			}
			$loginEmailForm = file_get_contents ($recoverFormEmailFile);

			header ('Content-Type: text/html; charset=utf-8');
			print ($loginEmailForm);
		}

		return NULL;
	}


	/**
	 * Check the recovery password and if correct, store the new password
	 *
	 * @return NULL
	 */
	private function checkAndStoreNewPass ()
	{
		// Check the used code is valid
		$email = $_POST ['recoverEmail'];
		$sql = 'SELECT idUser,recoveryPass FROM weUsers WHERE email=? AND recoveryDate> NOW()';

		$idUsr = NULL;
		$isValid = false;
		$stmt = $this->mysqli->prepare ($sql);
		$stmt->bind_param ('s', $email);
		$stmt->execute ();
		if ($resultado = $stmt->get_result ())
		{
			if ($resultado->num_rows > 0)
			{
				$row = $resultado->fetch_object ();

				if ($row->recoveryPass == $_POST ['codRec'])
				{
					$isValid = true;
					$idUsr = $row->idUser;
					$_SESSION ['userId'] = $idUsr;
				}
			}
		}

		if (! $isValid)
		{
			// TODO: Show a error message
			return NULL;
		}
		else
		{
			$dbPass = password_hash ($_POST ['newPass'], 1);

			$sql = 'UPDATE weUsers SET password=?, recoveryPass="", isActive=true WHERE email = ?';
			$stmt = $this->mysqli->prepare ($sql);
			$stmt->bind_param ('ss', $dbPass, $email);
			$stmt->execute ();
			return $idUsr;
		}
	}


	private function launchRecoverEmail ($email): string
	{
		// check email. Drop process if incorrect
		if (! filter_var ($email, FILTER_VALIDATE_EMAIL))
		{
			// YAGNI: Check if we need FILTER_FLAG_EMAIL_UNICODE
			// TODO: Show a better message error
			return "Incorrect email address.";
		}

		// Check the user and recover passwodr status
		$currRecoverPass = '';
		$mustSendEmail = true;
		$mustGenerateNewRecoverPass = true;
		$sqlEml = $email;
		$sql = 'SELECT recoveryPass, recoveryDate > NOW() AS withRecoverPassword, (recoveryDate - INTERVAL 30 MINUTE)> NOW()  AS isNewRecoverPassword';
		$sql .= ' FROM weUsers WHERE email=?';

		$stmt = $this->mysqli->prepare ($sql);
		$stmt->bind_param ('s', $sqlEml);
		$stmt->execute ();
		if ($res = $stmt->get_result ())
		{
			if ($res->num_rows > 0)
			{
				$row = $res->fetch_assoc ();
				if ($row ['withRecoverPassword'] == 0)
				{
					$mustSendEmail = true;
					$mustGenerateNewRecoverPass = true;
				}
				else if ($row ['isNewRecoverPassword'] == 0)
				{
					$mustGenerateNewRecoverPass = false;
					$mustSendEmail = true;
					$currRecoverPass = $row ['recoveryPass'];
				}
			}
			else
			{
				// The account does not exists: return as if the process were OK
				return '';
			}
		}

		// Generate a new recover password
		if ($mustGenerateNewRecoverPass)
		{
			$currRecoverPass = rtrim (base64_encode (random_bytes (14)), '=');
			$sql = 'UPDATE weUsers SET recoveryPass=?, recoveryDate= (NOW()+INTERVAL 30 MINUTE) WHERE email = ?';
			$stmt = $this->mysqli->prepare ($sql);
			$stmt->bind_param ('ss', $currRecoverPass, $sqlEml);
			$stmt->execute ();
		}

		// Send the recover password email
		if ($mustSendEmail)
		{
			require_once (Site::$cfgPath . 'mailer_cfg.php');
			require_once (Site::$rscPath . 'authMailer.php');
			sendRecoverEmail ($email, $currRecoverPass);
		}

		// No errors
		return '';
	}

	// ----------------- PAssword Recovery Funcions: end -----------------
}
