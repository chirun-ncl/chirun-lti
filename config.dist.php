<?php

// Application settings
define('APP_NAME', 'Coursebuilder LTI Tool');
define('SESSION_NAME', 'php-coursebuilder');
define('VERSION', '0.2.0');

// Setup paths
define('WEBDIR', '/lti');
define('WEBCONTENTDIR', WEBDIR.'/content');
define('INSTALLDIR', '/var/www/webroot/lti');
define('CONTENTDIR', INSTALLDIR.'/content');
define('UPLOADDIR', INSTALLDIR.'/upload');
define('PROCESSDIR', INSTALLDIR.'/process');
define('PROCESSUSER', "programs");

// Database connection settings
define('DB_NAME', 'mysql:dbname=#######;host=#######');
define('DB_USERNAME', '#######');
define('DB_PASSWORD', '#######');
define('DB_TABLENAME_PREFIX', '');

?>
