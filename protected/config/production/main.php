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
            'domain.cdn' => 'gateway.gd95516.com',
            'domain.gateway.rpc' => 'gateway.gd95516.com',
            'corsOriginDomain' => ['*','gateway.gd95516.com'],
        ]
    ]
);

$config['components']['log']['targets'][] = [
    'class' => 'yii\log\EmailTarget',
    'mailer' => 'mailer',
    'levels' => ['error', 'warning'],
    'message' => [
        'from' => ['webmaster@payment.com'],
        'to' => ['master@payment.com'],
        'subject' => '系统异常',
    ],
];

return $config;