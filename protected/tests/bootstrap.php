<?php
!defined('TEST') && define('TEST', 'TEST');

require(__DIR__ . '/../../protected/config/mode.php');
require(__DIR__ . '/../../vendor/autoload.php');

// $testEnv = getenv('APPLICATION_ENV');
$moduleName = getenv('MODULE_NAME');
// $idcNum = @intval(getenv('IDC_NUM'));
// $idcId = @intval(getenv('IDC_ID'));
// if ($idcNum <= 0) {
//     throw new \Exception("IDC_NUM is invalid! please export it in shell");
// }
// !defined('MODULE_NAME') && define('MODULE_NAME', $moduleName);
// !defined('APPLICATION_ENV') && define('APPLICATION_ENV', $testEnv);
// !defined('IDC_NUM') && define('IDC_NUM', $idcNum); // 机房数
// !defined('IDC_ID') && define('IDC_ID', $idcId); // idc数字编号，编号从0开始

\power\yii2\log\LogHelper::init();

$config = require sprintf('%s/../../protected/config/%s/test.php', __DIR__ , strtolower(APPLICATION_ENV));
new power\yii2\console\Application($config);

if (!empty($moduleName)) {
    // module custom config
    $moduleId = strtolower(preg_replace('#(?<!/)([A-Z])#', '-$1' , $moduleName));
    \Yii::$app->getModule($moduleId);
}
