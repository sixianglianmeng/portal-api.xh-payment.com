<?php
require(__DIR__ . '/../protected/config/mode.php');
require(__DIR__ . '/../vendor/autoload.php');

\power\yii2\log\LogHelper::init();
$config = require(__DIR__ . '/../protected/config/' . strtolower(APPLICATION_ENV) . '/main.php');
(new \power\yii2\web\Application($config))->run();
