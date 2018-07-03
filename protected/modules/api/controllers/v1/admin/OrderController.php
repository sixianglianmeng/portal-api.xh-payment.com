<?php
namespace app\modules\api\controllers\v1\admin;

use app\common\models\model\Order;
use app\components\Macro;
use app\components\RpcPaymentGateway;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use app\modules\gateway\models\logic\LogicOrder;
use Yii;

class OrderController extends BaseController
{
    //基础查询参数，例如查询订单只能查询自己的
    protected $baseFilter = [];

    public function behaviors()
    {
        $parentBehaviors = parent::behaviors();
        //验证码不需要token验证
        $behaviors = [];
        $behaviors = \yii\helpers\ArrayHelper::merge($parentBehaviors, $behaviors);

        return $behaviors;
    }

    /**
     * 前置action
     *
     * @author bootmall@gmail.com
     */
    public function beforeAction($action){
        $parentBeforeAction =  parent::beforeAction($action);

        return $parentBeforeAction;
    }

    /**
     * 设置订单为成功状态
     */
    public function actionSetSuccess()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $id;
//        $filter['status'] = Order::STATUS_PAID;
//        $order = Order::findOne($filter);
        $order = Order::find()->where($filter)->limit(1)->one();
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_orders',
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        $orderOpList = [];
        $orderOpList[] = ['order_no'=>$order->order_no];
        RpcPaymentGateway::setOrderSuccess($orderOpList);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '订单已更新为成功状态');
    }

    /**
     * 冻结订单
     */
    public function actionFrozen()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['status'] = Order::STATUS_PAID;
        $filter['id'] = $id;
//        $order = Order::findOne($filter);
        $order = Order::find()->where($filter)->limit(1)->one();
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_orders',
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        $orderOpList = [];
        $orderOpList[] = ['order_no'=>$order->order_no];
        RpcPaymentGateway::setOrderFrozen($orderOpList);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '冻结成功');
    }

    /**
     * 解冻订单
     */
    public function actionUnFrozen()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $id;
        $order = Order::findOne($filter);
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_orders',
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        $orderOpList = [];
        $orderOpList[] = ['order_no'=>$order->order_no];
        RpcPaymentGateway::setOrderUnFrozen($orderOpList);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '解冻成功');
    }


    /**
     * 订单退款
     */
    public function actionRefund()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null,Macro::CONST_PARAM_TYPE_INT,'订单号格式错误');
        $bak = ControllerParameterValidator::getRequestParam($this->allParams, 'bak',null,Macro::CONST_PARAM_TYPE_STRING,'退款原因错误',[1]);

        $filter = $this->baseFilter;
        $filter['id'] = $id;
        $order = Order::findOne($filter);
        if(empty($order)){
            Util::throwException(Macro::FAIL,'订单不存在');
        }
        if($order->status!=Order::STATUS_PAID){
            Util::throwException(Macro::FAIL,'只有成功订单才能退款');
        }

        $data = [
            'order_no'=>$order->order_no,
            'bak'=>$bak,
        ];

        $ret = RpcPaymentGateway::call('/order/refund', $data);

        return ResponseHelper::formatOutput(Macro::SUCCESS,'退款成功');
    }

}
