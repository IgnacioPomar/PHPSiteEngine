<?php

namespace PHPSiteEngine;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'libphp-phpmailer/autoload.php';


/**
 * URL-safe base64 encoding (no padding, '+'/'/' replaced by '-'/'_')
 */
function base64url_encode ($data)
{
	return rtrim (strtr (base64_encode ($data), '+/', '-_'), '=');
}


function smtpSend ($email, $subject, $body, $bodyTxt)
{
	$mail = new PHPMailer (true);

	try
	{
		// Server settings
		$mail->SMTPDebug = 0;
		$mail->isSMTP ();
		$mail->Host = $GLOBALS ['smtpHost'];
		$mail->SMTPAuth = true;
		$mail->Username = $GLOBALS ['smtpUsername'];
		$mail->Password = $GLOBALS ['smtpPass'];
		$mail->SMTPSecure = 'tls';
		$mail->Port = 587;

		$mail->setFrom ($GLOBALS ['smtpUsername'], 'Zona alumnos - GolfInone');

		// //YAGNI: recinbir el nombre de la cuneta
		$mail->addAddress ($email, $email);

		// Attachments
		// $mail->addAttachment ($realFile, $fileName);

		// Content

		$mail->isHTML (true);
		$mail->Body = $body;
		$mail->AltBody = $bodyTxt;
		$mail->Subject = $subject;

		$mail->send ();
	}
	catch (Exception $e)
	{
		// openlog ("recoveryMailer", LOG_PID | LOG_PERROR, LOG_SYSLOG);
		syslog (LOG_ERR, "Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
		// closelog ();
	}
}


/**
 * Función ecargada de mandar por SMTRP un email
 *
 * @param string $email
 * @param string $pass
 */
function sendRecoverEmail ($email, $pass)
{
	$plainLink = 'rP&rE=' . $email . '&CR=' . strtr ($pass, '-_', '+/');
	$server = $_SERVER ['REQUEST_SCHEME'] . '://' . $_SERVER ['SERVER_NAME'];
	$link = $server . $GLOBALS ['uriPath'] . '?recover=' . base64url_encode ($plainLink);

	$emlRecoverFile = Site::$skinPath . 'tmplt/emlPasswordRecover.htm';
	if (! file_exists ($emlRecoverFile))
	{
		$emlRecoverFile = Site::$rscPath . 'html/emlPasswordRecover.htm';
	}
	$emlTxtRecoverFile = Site::$skinPath . 'tmplt/emlTxtPasswordRecover.txt';
	if (! file_exists ($emlTxtRecoverFile))
	{
		$emlTxtRecoverFile = Site::$rscPath . 'html/emlTxtPasswordRecover.txt';
	}

	$subject = $GLOBALS ['recoverySubject'];

	$msg = file_get_contents ($emlRecoverFile);
	$msg = str_replace ('@@recoverCode@@', $pass, $msg);
	$msg = str_replace ('@@recoverLink@@', $link, $msg);

	$msgtxt = file_get_contents ($emlTxtRecoverFile);
	$msgtxt = str_replace ('@@recoverCode@@', $pass, $msgtxt);
	$msgtxt = str_replace ('@@recoverLink@@', $link, $msgtxt);

	smtpSend ($email, $subject, $msg, $msgtxt);
}

