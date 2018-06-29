<?php
namespace app\commands;
use Yii;

class BaseConsoleCommand extends \yii\console\Controller
{
    public function init()
    {
        parent::init();
        
        ini_set("display_errors", 1);
        ini_set('memory_limit', '128M');
    }

    public function beforeAction($event)
    {
        Yii::info('console process: '.implode(' ',$_SERVER['argv']));
        return parent::beforeAction($event);
    }
}
