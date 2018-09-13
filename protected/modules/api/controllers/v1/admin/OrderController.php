<?php
namespace app\modules\api\controllers\v1\admin;

use app\common\models\model\LogOperation;
use app\common\models\model\Order;
use app\common\models\model\UserBlacklist;
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
        $idList = ControllerParameterValidator::getRequestParam($this->allParams, 'idList', null, Macro::CONST_PARAM_TYPE_ARRAY, '订单ID错误');
        $filter = $this->baseFilter;
        $filter['status'] = [Order::STATUS_SETTLEMENT];
        $filter['id'] = $idList;

        $query = (new \yii\db\Query())
            ->select(['id','order_no','status'])
            ->from(Order::tableName())
            ->where($filter);
        $rawOrders = $query->limit(1000)
            ->all();

        if(!$rawOrders || count($rawOrders)!=count($idList)){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        $orderOpList = [];
        foreach ($rawOrders as $order){
            //接口日志埋点
            Yii::$app->params['operationLogFields'] = [
                'table'=>'p_orders',
                'pk'=>$order['id'],
                'order_no'=>$order['order_no'],
            ];
            LogOperation::inLog('ok');

            $orderOpList[] = ['order_no'=>$order['order_no']];
        }

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
        $filter['status'] = [Order::STATUS_SETTLEMENT,Order::STATUS_PAID];

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
                Util::throwException(Macro::FAIL,'只有成功订单才能结算:'.$order['order_no']);
            }


            $data['idList'][] = $order['id'];
        }


        $ret = RpcPaymentGateway::call('/order/settlement', $data);

        return ResponseHelper::formatOutput(Macro::SUCCESS,'结算成功');
    }

    /**
     * 充值订单加入黑名单
     * @admin
     */
    public function actionAddBlacklist()
    {
        $idList = ControllerParameterValidator::getRequestParam($this->allParams, 'idList', null, Macro::CONST_PARAM_TYPE_ARRAY, '订单ID错误');
        $filter = $this->baseFilter;
        $filter['id'] = $idList;

        $query = (new \yii\db\Query())
            ->select(['id','order_no','client_ip','client_id','merchant_id','merchant_account'])
            ->from(Order::tableName())
            ->where($filter);
        $rawOrders = $query->limit(1000)
            ->all();

        if(!$rawOrders || count($rawOrders)!=count($idList)){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        $user = Yii::$app->user->identity;
        $orderOpList = [];
        foreach ($rawOrders as $order){
            //接口日志埋点
            Yii::$app->params['operationLogFields'] = [
                'table'=>'p_orders',
                'pk'=>$order['id'],
                'order_no'=>$order['order_no'],
            ];
            LogOperation::inLog('ok');

            $blaclist = UserBlacklist::findOne(['type'=>1,'val'=>$order['client_ip']]);
            if(!$blaclist){
                $data = [
                    'type'=>1,
                    'val'=>$order['client_ip'],
                    'order_no'=>$order['order_no'],
                    'order_type'=>1,
                    'merchant_id'=>$order['merchant_id'],
                    'merchant_name'=>$order['merchant_account'],
                    'op_uid'=>$user->id,
                    'op_username'=>$user->username,
                ];
                $blaclist = new UserBlacklist();
                $blaclist->setAttributes($data,false);
                $blaclist->save(false);
            }

            if($order['client_id']){
                $blaclist = UserBlacklist::findOne(['type'=>2,'val'=>$order['client_id']]);
                if(!$blaclist){
                    $data = [
                        'type'=>2,
                        'val'=>$order['client_id'],
                        'order_no'=>$order['order_no'],
                        'order_type'=>1,
                        'merchant_id'=>$order['merchant_id'],
                        'merchant_name'=>$order['merchant_account'],
                        'op_uid'=>$user->id,
                        'op_username'=>$user->username,
                    ];
                    $blaclist = new UserBlacklist();
                    $blaclist->setAttributes($data,false);
                    $blaclist->save(false);
                }

            }

        }


        return ResponseHelper::formatOutput(Macro::SUCCESS, 'IP及设备ID成功加入黑名单');
    }

    /**
     * 充值金额统计分析
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionEcharts()
    {
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
        $startTime = empty($dateStart) ? strtotime('-4 days',strtotime(date("Y-m-d"))) : strtotime(date("Y-m-d",strtotime($dateStart)));
        $endTime = empty($dateEnd) ? strtotime(date("Y-m-d")) : strtotime(date("Y-m-d",strtotime($dateEnd)));
        $days = ($endTime - $startTime) / (24*3600) ;
        if($days > 4){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '时间筛选跨度不能超过4天');
        }
        $timeArray = [
            0=>['date_start'=>'00:00:00','date_end'=>'00:59:59'],
            1=>['date_start'=>'01:00:00','date_end'=>'01:59:59'],
            2=>['date_start'=>'02:00:00','date_end'=>'02:59:59'],
            3=>['date_start'=>'03:00:00','date_end'=>'03:59:59'],
            4=>['date_start'=>'04:00:00','date_end'=>'04:59:59'],
            5=>['date_start'=>'05:00:00','date_end'=>'05:59:59'],
            6=>['date_start'=>'06:00:00','date_end'=>'06:59:59'],
            7=>['date_start'=>'07:00:00','date_end'=>'07:59:59'],
            8=>['date_start'=>'08:00:00','date_end'=>'08:59:59'],
            9=>['date_start'=>'09:00:00','date_end'=>'09:59:59'],
            10=>['date_start'=>'10:00:00','date_end'=>'10:59:59'],
            11=>['date_start'=>'11:00:00','date_end'=>'11:59:59'],
            12=>['date_start'=>'12:00:00','date_end'=>'12:59:59'],
            13=>['date_start'=>'13:00:00','date_end'=>'13:59:59'],
            14=>['date_start'=>'14:00:00','date_end'=>'14:59:59'],
            15=>['date_start'=>'15:00:00','date_end'=>'15:59:59'],
            16=>['date_start'=>'16:00:00','date_end'=>'16:59:59'],
            17=>['date_start'=>'17:00:00','date_end'=>'17:59:59'],
            18=>['date_start'=>'18:00:00','date_end'=>'18:59:59'],
            19=>['date_start'=>'19:00:00','date_end'=>'19:59:59'],
            20=>['date_start'=>'20:00:00','date_end'=>'20:59:59'],
            21=>['date_start'=>'21:00:00','date_end'=>'21:59:59'],
            22=>['date_start'=>'22:00:00','date_end'=>'22:59:59'],
            23=>['date_start'=>'23:00:00','date_end'=>'23:59:59']
        ];
        $data = [];
        for($i = 0 ;$i < $days ;$i++){
            $dayTime = date("Y-m-d",strtotime("-{$i} days"));
            foreach ($timeArray as $key => $val){
                $where['start_time'] = $dayTime.' '.$val['date_start'];
                $where['end_time'] = $dayTime.' '.$val['date_end'];
                $data[$dayTime][$key] = Order::totalChargeAmount($where);
            }
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }

}
