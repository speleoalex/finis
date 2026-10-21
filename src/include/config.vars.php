<?php

global $_FN;
//specific options for the mysql driver:
$_FN['xmetadb_mysqlhost'] = "localhost";
$_FN['xmetadb_mysqldatabase'] = "fndatabase";
$_FN['xmetadb_mysqlusername'] = "root";
$_FN['xmetadb_mysqlpassword'] = "";
//display errors ("on" only while developing, in config.vars.local.php)
$_FN['display_errors'] = "off";
//max upload file size
$_FN['upload_max_filesize'] = "20M";
//writable folder that contains data
$_FN['datadir'] = "misc";
//authentication method
$_FN['default_auth_method'] = "local";
//groups allowed to use an API key (MCP, API clients), comma separated; empty = every user, administrators always
$_FN['apikey_groups'] = "";
//reverse proxies trusted for the client address in X-Forwarded-For, comma separated IP addresses; empty = the client is REMOTE_ADDR
$_FN['trusted_proxies'] = "";
//default driver: xmlphp,sqlite3,sqlite,csv,serialize,mysql,mssql
$_FN['default_database_driver'] = "xmlphp";
//home section that overrides the control center setting (empty = use the setting)
$_FN['home_section_force'] = "";
