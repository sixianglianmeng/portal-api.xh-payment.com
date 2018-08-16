<?php
namespace app\modules\api\controllers\v1\admin;

use app\common\models\model\ChannelAccount;
use app\common\models\model\LogOperation;
use app\common\models\model\Remit;
use app\components\Macro;
use app\components\RpcPaymentGateway;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use app\modules\gateway\models\logic\LogicRemit;
use Yii;

class RemitController extends BaseController
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
     * 设置提款为成功
     */
    public function actionSetSuccess()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $id;
        $order = Remit::find()->where($filter)->limit(1)->one();
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_remit',
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        if(!in_array($order->status,[Remit::STATUS_BANK_PROCESSING, Remit::STATUS_DEDUCT, Remit::STATUS_NOT_REFUND])){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单状态必须是已扣款|处理中|失败未退款');
        }

        $orderOpList = [];
        $orderOpList[] = ['order_no'=>$order->order_no];
        RpcPaymentGateway::setRemitSuccess($orderOpList);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '订单已更新为成功状态');
    }

    /**
     * 管理员同步提款状态
     * @role admin
     */
    public function actionSyncStatus()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $id;
        $order = Remit::find()->where($filter)->limit(1)->one();
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_remit',
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        $orderOpList = [];
        $orderOpList[] = ['order_no'=>$order->order_no];
        $ret = RpcPaymentGateway::syncRemitStatusRealtime($order->order_no);

        return ResponseHelper::formatOutput(Macro::SUCCESS, str_replace("\n","<br/>",$ret['message']));
    }

    /**
     * 设置提款为失败
     */
    public function actionSetFail()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $id;
//        $order = Remit::findOne($filter);
        $order = Remit::findOne($filter);
        if(!$order){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_remit',
            'pk'=>$order->id,
            'order_no'=>$order->order_no,
        ];

        if(!in_array($order->status,[Remit::STATUS_BANK_PROCESSING, Remit::STATUS_CHECKED,  Remit::STATUS_DEDUCT, Remit::STATUS_NOT_REFUND])){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单状态必须是已审核|已扣款|处理中|失败未退款:'.$order->status);
        }

        $orderOpList = [];
        $orderOpList[] = ['order_no'=>$order->order_no];
        RpcPaymentGateway::setRemitFail($orderOpList);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '订单已更新为失败状态');
    }

    /**
     * 设置出款为已审核
     */
    public function actionSetChecked()
    {
        $idList = ControllerParameterValidator::getRequestParam($this->allParams, 'idList', null, Macro::CONST_PARAM_TYPE_ARRAY, '订单ID错误');

        $filter = $this->baseFilter;
        $filter['id'] = $idList;
        $maxNum = 100;
        if(count($idList)>$maxNum){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, "单次最多设置{$maxNum}个订单");
        }
        $rawOrders = (new \yii\db\Query())
            ->select(['id','order_no'])
            ->from(Remit::tableName())
            ->where($filter)
            ->limit(100)
            ->all();

        if(!$rawOrders){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单不存在');
        }

        $orders = [];
        foreach ($rawOrders as $o){
            $orders[] = [
                'order_no'=>$o['order_no']
            ];

            //接口日志埋点
            Yii::$app->params['operationLogFields'] = [
                'table'=>'p_remit',
                'pk'=>$o['id'],
                'order_no'=>$o['order_no'],
            ];
            LogOperation::inLog('ok');
        }

        RpcPaymentGateway::setRemitChecked($orders);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '订单已更新为已审核');
    }
    /**
     * 出款待审核提醒
     * @roles admin,admin_operator,admin_service_lv1,admin_service_lv2
     */
    public function actionRemind()
    {
        $user = Yii::$app->user->identity;
        if($user->group_id == 10){
            $remit = Remit::find()->where(['status'=>Remit::STATUS_NONE])->count();
            if($remit > 0){
                return ResponseHelper::formatOutput(Macro::SUCCESS,'',[$remit]);
            }
        }
        return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN);
    }

    /**
     * 切换代付订单通道
     *
     * @role admin
     */
    public function actionSwitchChannelAccount()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', null,Macro::CONST_PARAM_TYPE_ARRAY,'订单号格式错误');
        $channelAccountId = ControllerParameterValidator::getRequestParam($this->allParams, 'channelAccountId', null,Macro::CONST_PARAM_TYPE_INT,'通道号格式错误');

        $filter = $this->baseFilter;
        $filter['id'] = $id;
        $rawOrders = Remit::findAll($filter);
        if(empty($rawOrders)){
            Util::throwException(Macro::FAIL,'订单不存在');
        }

        $channelAccount = ChannelAccount::findOne($channelAccountId);
        if(empty($channelAccount)){
            Util::throwException(Macro::FAIL,'通道不存在');
        }
        $failed=[];
        $msg = '通道切换成功';
        $ret = Macro::SUCCESS;
        foreach ($rawOrders as $remit){

            //接口日志埋点
            Yii::$app->params['operationLogFields'] = [
                'table'=>'p_remit',
                'pk'=>$remit->id,
                'order_no'=>$remit->order_no,
                'desc'=>'切换至：'.$channelAccount->channel_name
            ];
            LogOperation::inLog('ok');

            if(!in_array($remit->status,[Remit::STATUS_NONE,Remit::STATUS_DEDUCT,Remit::STATUS_NOT_REFUND])){
//                Util::throwException(Macro::FAIL,'只有未审核|失败未退款订单才能切换通道');
                $failed[] = $remit->order_no;
                continue;
            }

            $remit->channel_id = $channelAccount->channel_id;
            $remit->channel_account_id = $channelAccount->id;
            $remit->channel_merchant_id = $channelAccount->merchant_id;
            $remit->save();
        }
        if($failed){
            $msg = "部分订单状态错误，通道切换失败:".implode(",",$failed);
            $ret = Macro::FAIL;
        }
        return ResponseHelper::formatOutput($ret,$msg,['failed'=>$failed]);
    }
}
