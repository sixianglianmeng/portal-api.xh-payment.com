<?php
namespace app\modules\api\controllers\v1;

use app\common\models\model\Channel;
use app\common\models\model\Notice;
use app\common\models\model\Remit;
use app\common\models\model\UserPaymentInfo;
use app\modules\gateway\models\logic\LogicOrder;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use app\lib\helpers\ResponseHelper;
use app\lib\helpers\ControllerParameterValidator;
use app\common\models\model\User;
use app\common\models\model\Order;
use app\components\Macro;
use app\common\models\model\LogOperation;
use app\modules\api\controllers\BaseController;

class DashboardController extends BaseController
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
        $ret =  parent::beforeAction($action);

        return $ret;
    }

    /**
     * 用户首页
     */
    public function actionIndex()
    {

        $user = Yii::$app->user->identity;
        //获取最新的一条公告
        $notice = Notice::find()->orderBy('created_at desc')->asArray()->all();

        $data['notice'] = [];
        foreach ($notice as $key => $val){
            $data['notice'][$key] = $val;
        }
        $logOperation = LogOperation::find()
            ->where(['user_id' => $user->id,'type'=>'v1_user_login','op_status'=>0])
            ->andFilterCompare('created_at', '>='.(86400*20))
            ->orderBy('created_at desc')
            ->limit(2)
            ->asArray()
            ->all();
        if(empty($logOperation)){
            $data['user']['last_login_time'] = date("Y-m-d H:i:s",$user->last_login_time);
            $data['user']['last_login_ip'] = $user->last_login_ip;
        }else{
            if(count($logOperation) == 2){
                $data['user']['last_login_time'] = date("Y-m-d H:i:s",$logOperation[1]['created_at']);
                $data['user']['last_login_ip'] = $logOperation[1]['ip'];
            }else{
                $data['user']['last_login_time'] = date("Y-m-d H:i:s",$logOperation[0]['created_at']);
                $data['user']['last_login_ip'] = $logOperation[0]['ip'];
            }
        }
        $data['isMainAccount'] =  $user->isMainAccount();
        //主账号才显示统计数据
        if(!$data['isMainAccount']){
            return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
        }

        $data['user']['group_id'] = $user->group_id;
        $data['user']['order_today_amount'] = 0;
        $data['user']['order_today_total'] = 0;
        $data['user']['order_today_fee_amount'] = 0;
        $data['user']['order_yesterday_amount'] = 0;
        $data['user']['order_yesterday_total'] = 0;
        $data['user']['order_yesterday_fee_amount'] = 0;
        $data['user']['remit_today_amount'] = 0;
        $data['user']['remit_today_total_success'] = 0;
        $data['user']['remit_today_total_fail'] = 0;
        $data['user']['remit_today_amount_fail'] = 0;
        $data['user']['remit_yesterday_amount'] = 0;
        $data['user']['remit_yesterday_total_success'] = 0;
        $data['user']['remit_yesterday_total_fail'] = 0;
        $data['user']['remit_yesterday_amount_fail'] = 0;
        //已支付
        $orderToday = Order::getYesterdayTodayOrder($user->group_id,$user->id,'today', [Order::STATUS_SETTLEMENT,Order::STATUS_PAID]);
//        Yii::info('orderToday----',json_encode($orderToday));
        //$orderToday = $orderTodayQuery->asArray()->all();
        if(!empty($orderToday)){
            $data['user']['order_today_amount'] = $orderToday['amount'] ?? 0 ;
            $data['user']['order_today_total'] = $orderToday['total'] ?? 0 ;
            $data['user']['order_today_fee_amount'] = $orderToday['fee_amount'] ?? 0 ;
        }
//        Yii::info('order_today_amount----',$data['user']['order_today_amount']);
        //待结算
        $orderPaidToday = Order::getYesterdayTodayOrder($user->group_id,$user->id,'today', Order::STATUS_PAID);
        //$orderToday = $orderTodayQuery->asArray()->all();
        if(!empty($orderPaidToday)){
            $data['user']['order_today_paid_amount'] = $orderPaidToday['amount'] ?? 0 ;
            $data['user']['order_today_paid_total'] = $orderPaidToday['total'] ?? 0 ;
            $data['user']['order_today_paid_fee_amount'] = $orderPaidToday['fee_amount'] ?? 0 ;
        }

        $orderYesterday = Order::getYesterdayTodayOrder($user->group_id,$user->id,'yesterday');
        if(!empty($orderYesterday)){
            $data['user']['order_yesterday_amount'] = $orderYesterday['amount'] ?? 0 ;
            $data['user']['order_yesterday_total'] = $orderYesterday['total'] ?? 0 ;
            $data['user']['order_yesterday_fee_amount'] = $orderYesterday['fee_amount'] ?? 0 ;
        }
        //待结算
        $orderPaidYesterday = Order::getYesterdayTodayOrder($user->group_id,$user->id,'yesterday', Order::STATUS_PAID);
        //$orderToday = $orderTodayQuery->asArray()->all();
        if(!empty($orderPaidYesterday)){
            $data['user']['order_yesterday_paid_amount'] = $orderPaidYesterday['amount'] ?? 0 ;
            $data['user']['order_yesterday_paid_total'] = $orderPaidYesterday['total'] ?? 0 ;
            $data['user']['order_yesterday_paid_fee_amount'] = $orderPaidYesterday['fee_amount'] ?? 0 ;
        }
        $remitTodaySuccess = Remit::getYesterdayTodayRemit($user->group_id,$user->id,'today',1);
        if(!empty($remitTodaySuccess)){
            $data['user']['remit_today_amount'] = $remitTodaySuccess['amount'] ?? 0;
            $data['user']['remit_today_total_success'] = $remitTodaySuccess['total'] ?? 0;
        }
        $remitTodayFail = Remit::getYesterdayTodayRemit($user->group_id,$user->id,'today',0);
        if(!empty($remitTodayFail)){
            $data['user']['remit_today_total_fail'] = $remitTodayFail['total'] ?? 0;
            $data['user']['remit_today_amount_fail'] = $remitTodayFail['amount'] ?? 0;
        }
        $remitYesterdaySuccess = Remit::getYesterdayTodayRemit($user->group_id,$user->id,'yesterday',1);
        if(!empty($remitYesterdaySuccess)){
            $data['user']['remit_yesterday_amount'] = $remitYesterdaySuccess['amount'] ?? 0;
            $data['user']['remit_yesterday_total_success'] = $remitYesterdaySuccess['total'] ?? 0;
        }
        $remitYesterdaySuccess = Remit::getYesterdayTodayRemit($user->group_id,$user->id,'yesterday',0);
        if(!empty($remitYesterdaySuccess)){
            $data['user']['remit_yesterday_total_fail'] = $remitYesterdaySuccess['total'] ?? 0;
            $data['user']['remit_yesterday_amount_fail'] = $remitYesterdaySuccess['amount'] ?? 0;
        }
//        Yii::info('user----',$data['user']);
        $data['rate'] = UserPaymentInfo::getPayMethodsArrByAppId($user->id);
        $data['remit_fee'] = $user->paymentInfo->remit_fee;
        $data['payMethodOptions'] = Channel::ARR_METHOD;
        $data['needPayAccountOpenFee'] = $user->needPayAccountOpenFee();
        $data['needPayAccountOpenAmount'] = 0;
        if($data['needPayAccountOpenFee']){
            $data['needPayAccountOpenAmount'] = $user->accountOpenFeeInfo->fee;
        }
        //格式化返回json结构
//        $data = [];
//        foreach ($user as $key=>$val){
//            $data[] = [$key,$val];
//        }
        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }
}
