<?php
namespace app\modules\api;

use Yii;
use yii\helpers\ArrayHelper;

class ApiModule extends \yii\base\Module
{
    public function __construct($id, $parent = null, $config = [])
    {
        $moduleName = basename(__DIR__);
        ! defined('MODULE_NAME') && define('MODULE_NAME', $moduleName);//SYSTEM_NAME . '.' .
        if (defined('TEST')) {
            // 单元测试模式
            $configPath = __DIR__ . '/config/' . strtolower(APPLICATION_ENV) . '/test.php';
        } elseif (Yii::$app instanceof \yii\console\Application) {
            // 控制台命令模式
            $this->controllerNamespace = 'app\\modules\\' . $moduleName . '\\commands';
            $configPath = __DIR__ . '/config/' . strtolower(APPLICATION_ENV) . '/console.php';
        } else {
            // web应用模式
            $configPath = __DIR__ . '/config/' . strtolower(APPLICATION_ENV) . '/main.php';
        }
        if (!is_readable($configPath)) {
            throw new \yii\web\HttpException(405, "$configPath is not readalbe");
        }

        $config = ArrayHelper::merge($config, require($configPath));
 
        parent::__construct($id, $parent, $config);
    }

    public function init()
    {
        parent::init();
    }
}
