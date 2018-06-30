<?php
$config = \yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/../base.php',
    [
        'bootstrap' => [],
        'modules' => [],
        'components' => [
            'db' => [
                'class' => 'yii\db\Connection',
                'dsn' => 'mysql:host=127.0.0.1;dbname=xh_payment_com',
                'username' => 'xh_payment_com',
                'password' => 'xf8LxyLRZmNM62Jd',
                'charset' => 'utf8',
                'tablePrefix' => 'p_',
                'enableLogging'=>true,
            ],
            'redis' => [
                'class' => 'yii\redis\Connection',
                'hostname' => '127.0.0.1',
                'port' => 63780,
                'database' => 0,
            ],
        ],
        'params' => [
        ],
    ]
);

return $config;
