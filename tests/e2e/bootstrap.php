<?php
global $CFG_GLPI;

define('GLPI_ROOT', dirname(__DIR__, 2));

define('TU_USER', '_test_user');
define('TU_PASS', 'PhpUnit_4');

if (file_exists("vendor/autoload.php")) {
    require_once "vendor/autoload.php";
}
include GLPI_ROOT . "/inc/includes.php";
