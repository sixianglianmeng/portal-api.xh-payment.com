<?php
$config = \yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/main.php',
    [
        'enableCoreCommands' => true,
        'controllerNamespace' => 'app\commands',
        'components' => [
            'log' => [
                'flushInterval' => 1,
                'targets' => [
                    'file' => [
                        'exportInterval' => 1,
                    ],
                    'notice' => [
                        'exportInterval' => 1,
                    ],
                ],
            ],
        ],
    ]
);
unset($config['components']['response']['format']);
if (isset($config['components']['request'])) {
    unset($config['components']['request']);
}
return $config;
