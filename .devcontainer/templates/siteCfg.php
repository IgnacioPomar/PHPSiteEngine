<?php
/**
 * Configuración inicial para el servicio MariaDB definido en docker-compose.
 */

$GLOBALS ['Version'] = '0.3';

$GLOBALS ['authAllowRecover'] = TRUE;
$GLOBALS ['authAllowAppLogins'] = FALSE;
$GLOBALS ['authKeepLogged'] = TRUE;
$GLOBALS ['menuType'] = '1';

$GLOBALS ['dbserver'] = 'db';
$GLOBALS ['dbport'] = '3306';
$GLOBALS ['dbuser'] = 'app';
$GLOBALS ['dbpass'] = 'app';
$GLOBALS ['dbname'] = 'app';

$GLOBALS ['plgs'] = 'plgs';
$GLOBALS ['skin'] = 'skins';
