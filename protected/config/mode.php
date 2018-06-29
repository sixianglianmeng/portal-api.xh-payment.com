<?php
mb_internal_encoding("UTF-8");
$hostname = gethostname();
$sysEnv   = getenv('ENV');
$phpEnv   = getenv('PHP_ENV');

if (
    strpos($hostname, 'prod') !== false
    || strpos($hostname, 'production') !== false
    || strpos($sysEnv, 'prod') !== false
    || strpos($sysEnv, 'production') !== false
    || strpos($phpEnv, 'prod') !== false
    || strpos($phpEnv, 'production') !== false

) {
    define('APPLICATION_ENV', 'production');
    define('YII_ENV', 'production');
} elseif (
    strpos($hostname, 'test') !== false
    || strpos($hostname, 'testing') !== false
    || strpos($sysEnv, 'test') !== false
    || strpos($sysEnv, 'testing') !== false
    || strpos($phpEnv, 'test') !== false
    || strpos($phpEnv, 'testing') !== false
) {
    define('APPLICATION_ENV', 'test');
    define('YII_ENV', 'test');
} else {
    define('APPLICATION_ENV', 'dev');
    define('YII_ENV', 'dev');

    define('YII_DEBUG', true);
}