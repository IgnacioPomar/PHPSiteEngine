<?php
/**
 * Configuración inicial para el servicio MariaDB definido en docker-compose.
 */

$GLOBALS ['Version'] = '0.3';

$GLOBALS ['authRecover'] = TRUE;
$GLOBALS ['authLog'] = TRUE;
$GLOBALS ['menuType'] = '0';

$GLOBALS ['dbserver'] = 'db';
$GLOBALS ['dbport'] = '3306';
$GLOBALS ['dbuser'] = 'app';
$GLOBALS ['dbpass'] = 'app';
$GLOBALS ['dbname'] = 'app';

$GLOBALS ['plgs'] = 'plgs';
$GLOBALS ['skin'] = 'skins';
