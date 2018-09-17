<?php
namespace app\modules\api\controllers\v1\admin;

use app\common\models\model\ChannelAccount;
use app\common\models\model\LogOperation;
use app\common\models\model\Remit;
use app\common\models\model\UserBlacklist;
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

        if(!in_array($order->status,[Remit::STATUS_BANK_PROCESSING, Remit::STATUS_DEDUCT, Remit::STATUS_CHECKED, Remit::STATUS_NOT_REFUND])){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单状态必须是已扣款|已审核|处理中|失败未退款');
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

        $ret = RpcPaymentGateway::syncRemitStatus(0,[$order->order_no]);

        return ResponseHelper::formatOutput(Macro::SUCCESS, "订单处理成功");
    }

    /**
     * 管理员实时查看订单上游状态
     * @role admin
     */
    public function actionSyncStatusRealtime()
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

        if(!in_array($order->status,[Remit::STATUS_BANK_PROCESSING, Remit::STATUS_CHECKED,  Remit::STATUS_DEDUCT, Remit::STATUS_NOT_REFUND, Remit::STATUS_BANK_NET_FAIL, Remit::STATUS_BANK_PROCESS_FAIL, Remit::STATUS_SUCCESS])){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '订单状态必须是已审核|已扣款|处理中|失败未退款|成功:'.$order->status);
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
     * 重新提交订单到上游
     */
    public function actionReSubmitToBank()
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

        $ret = RpcPaymentGateway::call('/remit/re-submit-to-bank',['orderNoList'=>$orders]);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '订单已重新提交');
    }

    /**
     * 出款待审核提醒
     * @roles admin,admin_operator,admin_service_lv1,admin_service_lv2
     */
    public function actionRemind()
    {
        $user = Yii::$app->user->identity;
        $data = ['count'=>0];
        if($user->isSuperAdmin()){
            $query = Remit::find()->where(['status'=>[Remit::STATUS_DEDUCT]]);
            $merchantCheckStatusCanBeShow = [Remit::MERCHANT_CHECK_STATUS_CHECKED,Remit::MERCHANT_CHECK_STATUS_DENIED];
            $query->andFilterWhere([
                    'or',
                    ['need_merchant_check'=> 0],
                    [
                        'and',
                        'need_merchant_check=1',
                        'merchant_check_status IN('.implode(',',$merchantCheckStatusCanBeShow).')'
                    ]
                ]
            );
            $data['count'] = $query->count();
        }
        //刷新登录token
//        $data['__token__'] = Yii::$app->user->identity->refreshAccessToken();
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }

    /**
     * 银卡当天出款统计
     * @roles admin,admin_operator,admin_service_lv1,admin_service_lv2
     */
    public function actionBankCardTodayStatistic()
    {
        $cardNo = ControllerParameterValidator::getRequestParam($this->allParams, 'cardNo', null,Macro::CONST_PARAM_TYPE_NUMERIC_STRING,'银行卡格式错误');

        $user = Yii::$app->user->identity;
        $data  = (new \yii\db\Query())
            ->select([
                "status",
                "count(id) as nums",
                "sum(amount) as amount"
            ])
            ->from(Remit::tableName())
            ->where(['bank_no'=>$cardNo])
            ->andFilterCompare('created_at', '>='.strtotime(date("Y-m-d 0:0")))
            ->groupBy('status')
            ->all();
        foreach ($data as $k=>$d){
            $data[$k]['status_str'] = Remit::ARR_STATUS[$d['status']]??'-';
        }

        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
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

        $user = Yii::$app->user->identity;
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

            //暂时都可以强制切通道,人工控制状态问题
//            if(!in_array($remit->status,[Remit::STATUS_NONE,Remit::STATUS_DEDUCT,Remit::STATUS_NOT_REFUND])){
////                Util::throwException(Macro::FAIL,'只有未审核|失败未退款订单才能切换通道');
//                $failed[] = $remit->order_no;
//                continue;
//            }
            $oldChannelAccount = $remit->channelAccount;
            $remit->channel_id = $channelAccount->channel_id;
            $remit->channel_account_id = $channelAccount->id;
            $remit->channel_merchant_id = $channelAccount->merchant_id;
            $remit->bank_ret = $remit->bank_ret.date('Ymd H:i:s')." {$user->username}切换通道{$oldChannelAccount->channel_name}->{$channelAccount->channel_name}\n";
            $remit->save();
        }
        if($failed){
            $msg = "部分订单状态错误，通道切换失败:".implode(",",$failed);
            $ret = Macro::FAIL;
        }
        return ResponseHelper::formatOutput($ret,$msg,['failed'=>$failed]);
    }


    /**
     * 出款订单加入黑名单
     * @admin
     */
    public function actionAddBlacklist()
    {
        $idList = ControllerParameterValidator::getRequestParam($this->allParams, 'idList', null, Macro::CONST_PARAM_TYPE_ARRAY, '订单ID错误');
        $filter = $this->baseFilter;
        $filter['id'] = $idList;

        $query = (new \yii\db\Query())
            ->select(['id','order_no','bank_no','merchant_id','merchant_account'])
            ->from(Remit::tableName())
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

            $blaclist = UserBlacklist::findOne(['type'=>2,'val'=>$order['bank_no']]);
            if(!$blaclist){
                $data = [
                    'type'=>3,
                    'val'=>$order['bank_no'],
                    'order_no'=>$order['order_no'],
                    'order_type'=>2,
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


        return ResponseHelper::formatOutput(Macro::SUCCESS, "银行卡号{$order['bank_no']}成功加入黑名单");
    }
}
