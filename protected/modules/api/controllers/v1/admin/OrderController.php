<?php
namespace app\modules\api\controllers\v1\admin;

use app\common\models\model\LogOperation;
use app\common\models\model\Order;
use app\components\Macro;
use app\components\RpcPaymentGateway;
use app\components\Util;
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
        $filter['status'] = Order::STATUS_SETTLEMENT;
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
     *
     * @role admin
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

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_orders',
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        if($order->status!=Order::STATUS_SETTLEMENT){
            Util::throwException(Macro::FAIL,'只有已结算订单才能退款');
        }

        $data = [
            'order_no'=>$order->order_no,
            'bak'=>$bak,
        ];

        $ret = RpcPaymentGateway::call('/order/refund', $data);

        return ResponseHelper::formatOutput(Macro::SUCCESS,'退款成功');
    }

    /**
     * 订单结算
     *
     * @role admin
     */
    public function actionSetSettlement()
    {
        $idList = ControllerParameterValidator::getRequestParam($this->allParams, 'idList', null, Macro::CONST_PARAM_TYPE_ARRAY, '订单ID错误');
        $bak = ControllerParameterValidator::getRequestParam($this->allParams, 'bak','',Macro::CONST_PARAM_TYPE_STRING,'结算原因错误',[1]);
        $merchantOrderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantOrderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户订单号错误',[0,32]);
        $merchantUsername = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantUserName', '',Macro::CONST_PARAM_TYPE_STRING,'用户名错误',[0,32]);
        $merchantNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户编号错误',[0,32]);

        $method = ControllerParameterValidator::getRequestParam($this->allParams, 'method','',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'支付类型错误',[0,100]);

        $channelAccount = ControllerParameterValidator::getRequestParam($this->allParams, 'channelAccount','',Macro::CONST_PARAM_TYPE_INT,'通道号错误',[0,100]);

        $notifyStatus = ControllerParameterValidator::getRequestParam($this->allParams, 'notifyStatus','',Macro::CONST_PARAM_TYPE_INT,'通知状态错误',[0,100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        $minMoney = ControllerParameterValidator::getRequestParam($this->allParams, 'minMoney', '',Macro::CONST_PARAM_TYPE_DECIMAL,'最小金额输入错误');

        $maxMoney = ControllerParameterValidator::getRequestParam($this->allParams, 'maxMoney', '',Macro::CONST_PARAM_TYPE_DECIMAL,'最大金额输入错误');

        //必须有筛选条件不为空
        $filterParams = ['idList','merchantOrderNo','merchantUserName','merchantNo','method','channelAccount',
            'notifyStatus','dateStart','dateEnd','minMoney','maxMoney'];
        $filterParamNull = true;
        foreach ($filterParams as $v){
            if(!empty($this->allParams[$v])) $filterParamNull=false;
        }
        if($filterParamNull){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, "筛选条件不能为空");
        }

        $filter = $this->baseFilter;
        $filter['status'] = Order::STATUS_PAID;

        $query = (new \yii\db\Query())
            ->select(['id','order_no','status'])
            ->from(Order::tableName())
            ->where($filter);
        $dateStart = strtotime($dateStart);
        $dateEnd = strtotime($dateEnd);
        if(($dateEnd-$dateStart)>86400*31){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过31天');
            $dateStart=$dateEnd-86400*31;
        }

        if($dateStart){
            $query->andFilterCompare('created_at', '>='.$dateStart);
        }
        if($dateEnd){
            $query->andFilterCompare('created_at', '<'.$dateEnd);
        }
        if($minMoney){
            $query->andFilterCompare('amount', '>='.$minMoney);
        }
        if($maxMoney){
            $query->andFilterCompare('amount', '=<'.$maxMoney);
        }
        if($idList){
            $query->andwhere(['id' => $idList]);
        }
        if($merchantOrderNo){
            $query->andwhere(['merchant_order_no' => $merchantOrderNo]);
        }
        if($merchantUsername){
            $query->andwhere(['merchant_account' => $merchantUsername]);
        }
        if($merchantNo){
            $query->andwhere(['merchant_id' => $merchantNo]);
        }

        if(!empty($channelAccount)){
            $query->andwhere(['channel_account_id' => $channelAccount]);
        }

        if(!empty($method)){
            $query->andwhere(['pay_method_code' => $method]);
        }

        if(!empty($notifyStatus)){
            $query->andwhere(['notify_status' => $notifyStatus]);
        }

        $rawOrders = $query->limit(1000)
            ->all();

        if(!$rawOrders){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }
        $data['bak']=$bak;

        foreach ($rawOrders as $order){
            //接口日志埋点
            Yii::$app->params['operationLogFields'] = [
                'table'=>'p_orders',
                'pk'=>$order['id'],
                'order_no'=>$order['order_no'],
            ];
            LogOperation::inLog('ok');

            if($order['status']!=Order::STATUS_PAID){
                Util::throwException(Macro::FAIL,'只有成功订单才能退款:'.$order['order_no']);
            }


            $data['idList'][] = $order['id'];
        }


        $ret = RpcPaymentGateway::call('/order/settlement', $data);

        return ResponseHelper::formatOutput(Macro::SUCCESS,'结算成功');
    }

}
