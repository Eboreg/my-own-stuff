<?php
define("MYSQL_SERVER", "localhost");
define("MYSQL_USERNAME", "<< removed >>");
define("MYSQL_PASSWORD", "<< removed >>");
define("MYSQL_SCHEMA", "mittcafe");
define('DEBUG', false);
define('DELIMITER', "\t");
define('ABSPATH', '/var/www/dopin-require/');
define('SOUTH', 0);
define('WEST', 1);
define('NORTH', 2);
define('EAST', 3);

ini_set('display_errors', 0);
if (DEBUG)
    error_reporting(E_ALL);
else
    error_reporting(E_ERROR | E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_PARSE);
?>