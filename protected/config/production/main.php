<?php
$config = \yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/../base.php',
    [
        'bootstrap' => [],
        'modules' => [],
        'components' => [
            'db' => [
                'class' => 'yii\db\Connection',
                'dsn' => 'mysql:host=127.0.0.1;dbname=xh_payment',
                'username' => 'xh_payment',
                'password' => 'Hkz4wIxIJtJusidaf234iK',
                'charset' => 'utf8',
                'tablePrefix' => 'p_',
                'enableSchemaCache' => true,
                // Name of the cache component used to store schema information
                'schemaCache' => 'cache',
                // Duration of schema cache.
                'schemaCacheDuration' => 86400, // 24H it is in seconds
            ],
            'redis' => [
                'class' => 'yii\redis\Connection',
                'hostname' => '127.0.0.1',
                'port' => 63780,
                'database' => 0,
            ],
        ],
        'params'    => [
        ]
    ]
);

return $config;