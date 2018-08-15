<?php
namespace app\common\models\model;

use yii\db\ActiveRecord;

/*
 * 订单model
 *
 * @property int $id 自增ID
 * @property int $op_uid 用户UID
 * @property string $op_username 用户名
 * @property string $order_no 平台支付流水号
 * @property string $merchant_order_no 商户支付流水号
 * @property string $channel_order_no 上级支付渠道流水号
 * @property int $merchant_id 商户ID
 * @property string $merchant_user_id 商户用户UID
 * @property int $app_id 商户应用ID
 * @property int $app_name 商户应用名
 * @property string $merchant_account 商户账户
 * @property string $amount 订单金额
 * @property string $paid_amount 实际支付的金额
 * @property string $channel_id 支付通道ID
 * @property string $channel_merchant_id 支付通道商户号
 * @property string $channel_app_id 支付通道app id
 * @property string $pay_method_code 支付方式代码
 * @property string $sub_pay_method_code 子支付方式代码
 * @property string $bank_code 银行直连支付时的银行代码
 * @property string $title 订单描述
 * @property string $description 订单详情
 * @property int $notify_status 订单后台通知状态 0未通知 10已通知
 * @property string $notify_url 订单后台通知URL
 * @property string $reutrn_url 订单前台回跳URL
 * @property string $client_ip 订单终端用户IP
 * @property string $client_id 客户端ID
 * @property int $created_at 记录生成时间
 * @property int $paid_at 支付时间
 * @property int $updated_at 记录更新时间
 * @property string $bak 订单备注
 * @property int $notify_at 上次通知时间
 * @property int $notify_times 已通知次数
 * @property int $next_notify_time 下次通知时间
 * @property int $status -10交易失败, 0未付款，10付款中，20已支付
 * @property string $return_params 订单完成后回传给商户的参数
 * @property int $financial_status 订单账务处理状态 0未处理10已处理
 * @property string $fail_msg 订单失败描述
 * @property string $notify_ret 通知结果
 * @property int $merchant_order_time 商户订单时间
 * @property string $fee_amount 手续费
 * @property string $fee_rate 手续费比例
 * @property int $channel_account_id 支付通道账户表ID
 */
class Order extends BaseModel
{
    //充值订单状态
    const STATUS_NOTPAY= 10;
//    const STATUS_PAYING=10;
    const STATUS_PAID = 20;
    const STATUS_FREEZE = 30;
    const STATUS_FAIL = 40;
    const STATUS_REFUND = 50;
    const STATUS_SETTLEMENT = 60;

//    充值订单状态
    const ARR_STATUS = [
        self::STATUS_NOTPAY=>'待支付',
//        self::STATUS_PAYING=>'',
        self::STATUS_PAID=>'已支付',
        self::STATUS_FREEZE=>'冻结',
        self::STATUS_FAIL=>'支付失败',
        self::STATUS_REFUND=>'已退款',
        self::STATUS_SETTLEMENT=>'已结算',
    ];

//    订单通知状态
    const NOTICE_STATUS_NONE = 10;
    const NOTICE_STATUS_SUCCESS = 20;
    const NOTICE_STATUS_FAIL = 30;
//    订单状态 id=>描述
    const ARR_NOTICE_STATUS = [
        self::NOTICE_STATUS_NONE=>'未通知',
        self::NOTICE_STATUS_SUCCESS=>'通知成功',
        self::NOTICE_STATUS_FAIL=>'通知失败',
    ];

    const FINANCIAL_STATUS_NONE = 0;
    const FINANCIAL_STATUS_SUCCESS = 10;

    public static function getDb()
    {
        return \Yii::$app->db;
    }

    public static function tableName()
    {
        return '{{%orders}}';
    }

    public static function getOrderByOrderNo(string $orderNo){
        $order = Order::findOne(['order_no'=>$orderNo]);
        return $order;
    }

    public function getMerchant(){
        return $this->hasOne(User::className(), ['id'=>'merchant_id']);
    }

    public function getChannel(){
        return $this->hasOne(Channel::className(), ['id'=>'channel_id']);
    }

    public function getChannelAccount(){
        return $this->hasOne(ChannelAccount::className(), ['id'=>'channel_account_id']);
    }

    public function getUserPaymentInfo()
    {
        return $this->hasOne(UserPaymentInfo::className(), ['user_id'=>'merchant_id']);
    }
    /**
     * 获取支付方式配置信息
     *
     * @return ActiveRecord
     */
    public function getMethodConfig()
    {
        return $this->hasOne(MerchantRechargeMethod::className(), ['app_id' => 'app_id'])
            ->where(['method_id' => $this->pay_method_code])->one();
    }

    /**
     * 获取订单状态描述
     *
     * @return string
     * @author bootmall@gmail.com
     */
    public function getStatusStr()
    {
        return self::ARR_STATUS[$this->status]??'-';
    }

    /**
     * 获取通知状态描述
     *
     * @return string
     * @author bootmall@gmail.com
     */
    public function getNotifyStatusStr()
    {
        return self::ARR_NOTICE_STATUS[$this->notify_status]??'-';
    }

    /**
     * 获取所有上级代理账户此订单支付方式的配置
     *
     * @return array
     * @author bootmall@gmail.com
     */
    public function getAllParentRechargeConfig()
    {
        return $this->all_parent_recharge_config?json_decode($this->all_parent_recharge_config,true):[];
    }

    /**
     * 首页统计今天、昨天的充值成功笔数、手续费
     * @param $group_id 商户类型 10 - 管理员 20 - 代理 30 - 商户
     * @param $merchant_id 商户ID
     * @param $type today 今天 Yesterday 昨天
     * @param $status 订单状态 默认20已结算
     */
    public static function getYesterdayTodayOrder($group_id,$merchant_id,$type,$status=self::STATUS_SETTLEMENT)
    {
        $order = [];
        $orderQuery = self::find();
        if($type == 'today'){
            $orderQuery->andFilterCompare('created_at', '>='.strtotime(date("Y-m-d")));
        }else{
            $orderQuery->andFilterCompare('created_at', '>='.strtotime('-1 day',strtotime(date("Y-m-d"))));
            $orderQuery->andFilterCompare('created_at', '<'.strtotime(date("Y-m-d")));
        }
        //$orderTodayQuery->andFilterCompare('created_at', '<'.strtotime($dateEnd));
        if($group_id == 20){
            $orderQuery->andWhere(['merchant_id'=>$merchant_id]);
            $agentWhere = [
                'or',
                ['like','all_parent_agent_id',','.$merchant_id.','],
                ['like','all_parent_agent_id','['.$merchant_id.']'],
                ['like','all_parent_agent_id','['.$merchant_id.','],
                ['like','all_parent_agent_id',','.$merchant_id.']']
            ];
            $orderQuery->andWhere($agentWhere);
        }
        if($group_id == 30){
            $orderQuery->andWhere(['merchant_id'=>$merchant_id]);
        }
        $orderQuery->andWhere(['status'=>$status]);
        $orderQuery->select('sum(amount) as amount,count(id) as total,sum(fee_amount) as fee_amount');
        $order = $orderQuery->asArray()->all();

        return $order[0];
    }
}