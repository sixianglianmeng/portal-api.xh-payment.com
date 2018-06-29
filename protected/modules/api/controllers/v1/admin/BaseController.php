<?php
namespace app\modules\api\controllers\v1\admin;

use Yii;
use app\components\Macro;

class BaseController extends \app\components\WebAppController
{

    /**
     * 前置action
     *
     * @author bootmall@gmail.com
     */
    public function beforeAction($action){
        $this->user = Yii::$app->user->identity;
        $parentBeforeAction =  parent::beforeAction($action);

        return $parentBeforeAction;
    }
}