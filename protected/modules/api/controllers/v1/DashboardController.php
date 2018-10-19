<?php
namespace app\modules\api\controllers\v1;

use app\common\models\model\Channel;
use app\common\models\model\ChannelAccount;
use app\common\models\model\Notice;
use app\common\models\model\Remit;
use app\common\models\model\UserPaymentInfo;
use app\modules\gateway\models\logic\LogicOrder;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
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
        $notice = Notice::find()->where(['status'=>Notice::STATUS_PAID])->orderBy('created_at desc')->asArray()->all();

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
        $data['rate'] = UserPaymentInfo::getPayMethodsArrByAppId($user->id);
        $data['remit_fee'] = $user->paymentInfo->remit_fee;
        $data['payMethodOptions'] = Channel::ARR_METHOD;
        $data['needPayAccountOpenFee'] = $user->needPayAccountOpenFee();
        $data['needPayAccountOpenAmount'] = 0;
        if($data['needPayAccountOpenFee']){
            $data['needPayAccountOpenAmount'] = $user->accountOpenFeeInfo->fee;
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 统计昨天、今天的充值订单
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionRechargeTotal()
    {
        $user = Yii::$app->user->identity;
        //today-今天 yesterday-昨天
        $times = ControllerParameterValidator::getRequestParam($this->allParams,'times',null,Macro::CONST_PARAM_TYPE_STRING,'时间错误');
        $data = [
            'amount' => 0,
            'total' => 0,
            'fee_amount' => 0,
        ];
        //已支付
        $orderToday = Order::getYesterdayTodayOrder($user->group_id,$user->id,$times,Order::STATUS_SETTLEMENT);
        if(!empty($orderToday)){
            $data['amount'] = $orderToday['amount'] ?? 0 ;
            $data['total'] = $orderToday['total'] ?? 0 ;
            $data['fee_amount'] = $orderToday['fee_amount'] ?? 0 ;
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }

    /**
     * 统计昨天、今天的代付订单
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionRemitTotal()
    {
        $user = Yii::$app->user->identity;
        //today-今天 yesterday-昨天
        $times = ControllerParameterValidator::getRequestParam($this->allParams,'times',null,Macro::CONST_PARAM_TYPE_STRING,'时间错误');
        $data = [
            'amount_success' => 0,
            'total_success' => 0,
            'amount_fail' => 0,
            'total_fail' => 0,
        ];
        $remitTodaySuccess = Remit::getYesterdayTodayRemit($user->group_id,$user->id,$times,1);
        if(!empty($remitTodaySuccess)){
            $data['amount_success'] = $remitTodaySuccess['amount'] ?? 0;
            $data['total_success'] = $remitTodaySuccess['total'] ?? 0;
        }
        $remitTodayFail = Remit::getYesterdayTodayRemit($user->group_id,$user->id,$times,0);
        if(!empty($remitTodayFail)){
            $data['amount_fail'] = $remitTodayFail['amount'] ?? 0;
            $data['total_fail'] = $remitTodayFail['total'] ?? 0;
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }
}
