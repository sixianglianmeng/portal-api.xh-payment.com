<?php
namespace app\components;

class ConsoleCommand extends \yii\console\Controller
{
    public function init()
    {
        parent::init();
        
        ini_set("display_errors", 1);
        ini_set('memory_limit', '2048M');
    }
}
