<?php

namespace app\common\models\model;

/**
 * This is the model class for table "p_remit".
 *
 * @property int $id 自增ID
 * @property int $op_uid 操作者商户UID
 * @property string $op_username 操作者商户名
 * @property string $order_no 提款结算流水号
 * @property string $bat_order_no 批量提款时的批次号
 * @property string $merchant_order_no 商户提款结算流水号
 * @property string $channel_order_no 上级提款结算渠道流水号
 * @property int $merchant_id 商户ID
 * @property int $app_id 商户应用ID
 * @property int $app_name 商户应用名
 * @property string $merchant_account 商户账户
 * @property string $amount 订单金额
 * @property string $remit_fee 交易手续费
 * @property string $remited_amount 实际提款结算的金额
 * @property string $channel_id 提款结算通道ID
 * @property string $channel_merchant_id 提款结算通道商户号
 * @property string $bank_account 银行帐号
 * @property string $bank_name 银行名称
 * @property string $bank_no 银行卡号
 * @property string $bank_code 银行代码
 * @property string $client_ip 订单终端用户IP
 * @property int $created_at 申请时间
 * @property int $remit_at 提款结算时间
 * @property int $updated_at 记录更新时间
 * @property string $bak 订单备注
 * @property string $fail_msg 失败描述
 * @property string $bank_status 银行状态
 * @property int $status 0未处理 10 已审核 20账户已扣款 30已提交到银行 40 已出款 50处理失败已退款 60处理失败未退款 -10 提交银行失败 -20 银行处理失败
 */
class Remit extends BaseModel
{
    //默认单次最大提款金额
    const MAX_REMIT_PER_TIME = 49999;
    //0未处理 10 已审核 20账户已扣款 30银行处理中 40 成功已出款 50处理失败已退款 60处理失败未退款 -10 提交银行失败 -20 银行处理失败
    const STATUS_BANK_PROCESS_FAIL=-20;
    const STATUS_BANK_NET_FAIL=-10;
    const STATUS_NONE=0;
    const STATUS_CHECKED=10;
    const STATUS_DEDUCT=20;
    const STATUS_BANK_PROCESSING=30;
    const STATUS_SUCCESS=40;
    const STATUS_REFUND=50;
    const STATUS_NOT_REFUND=60;
    //银行处理状态 0 未处理 1 银行处理中 2 已打款 3失败
    const BANK_STATUS_NONE=0;
    const BANK_STATUS_PROCESSING=1;
    const BANK_STATUS_SUCCESS=2;
    const BANK_STATUS_FAIL=3;

    const FINANCIAL_STATUS_NONE = 0;
    const FINANCIAL_STATUS_SUCCESS = 10;

    const ARR_STATUS = [
        self::STATUS_NONE              => '未扣款',
        self::STATUS_DEDUCT            => '已扣款未审核',
        self::STATUS_CHECKED           => '已审核',
        self::STATUS_BANK_PROCESSING   => '银行处理中',
        self::STATUS_SUCCESS           => '成功已出款',
        self::STATUS_REFUND            => '失败已退款',
        self::STATUS_NOT_REFUND        => '提交失败',
        self::STATUS_BANK_NET_FAIL     => '提交银行失败',
        self::STATUS_BANK_PROCESS_FAIL => '银行处理失败',
    ];

    const ARR_BANK_STATUS = [
        self::BANK_STATUS_NONE => '已受理',
        self::BANK_STATUS_PROCESSING => '处理中',
        self::BANK_STATUS_SUCCESS => '出款成功',
        self::BANK_STATUS_FAIL => '出款失败',
    ];

    //订单通知状态
    const NOTICE_STATUS_NONE = 10;
    const NOTICE_STATUS_SUCCESS = 20;
    const NOTICE_STATUS_FAIL = 30;
//    订单状态 id=>描述
    const ARR_NOTICE_STATUS = [
        self::NOTICE_STATUS_NONE=>'未通知',
        self::NOTICE_STATUS_SUCCESS=>'通知成功',
        self::NOTICE_STATUS_FAIL=>'通知失败',
    ];

    const STATUS_LIST_PROCESSING = [self::STATUS_NOT_REFUND,self::STATUS_CHECKED,self::STATUS_DEDUCT,self::STATUS_BANK_PROCESSING];
    const STATUS_LIST_FAIL = [self::STATUS_BANK_NET_FAIL,self::STATUS_BANK_PROCESS_FAIL];

    const MERCHANT_CHECK_STATUS_NONE = 0;
    const MERCHANT_CHECK_STATUS_CHECKED = 1;
    const MERCHANT_CHECK_STATUS_DENIED = 2;
    const ARR_MERCHANT_CHECK_STATUS = [
        self::MERCHANT_CHECK_STATUS_NONE => '未审核',
        self::MERCHANT_CHECK_STATUS_CHECKED => '通过',
        self::MERCHANT_CHECK_STATUS_DENIED => '拒绝',
    ];

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%remit}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'op_uid' => 'Op Uid',
            'op_username' => 'Op Username',
            'order_no' => 'Order No',
            'bat_order_no' => 'Bat Order No',
            'merchant_order_no' => 'Merchant Order No',
            'channel_order_no' => 'Channel Order No',
            'merchant_id' => 'Merchant ID',
            'app_id' => 'App ID',
            'app_name' => 'App Name',
            'merchant_account' => 'Merchant Account',
            'money' => 'Money',
            'withdrawal_money' => 'Withdrawal Money',
            'channel_id' => 'Channel ID',
            'channel_merchant_id' => 'Channel Merchant ID',
            'bank_account' => 'Bank Account',
            'bank_name' => 'Bank Name',
            'bank_no' => 'Bank No',
            'bank_code' => 'Bank Code',
            'client_ip' => 'Client Ip',
            'created_at' => 'Created At',
            'withdrawal_at' => 'Withdrawal At',
            'updated_at' => 'Updated At',
            'bak' => 'Bak',
            'status' => 'Status',
        ];
    }

    public static function getByOrderNo(string $orderNo){
        $order = Remit::findOne(['order_no'=>$orderNo]);
        return $order;
    }

    public function getMerchant(){
        return $this->hasOne(User::className(), ['id'=>'merchant_id']);
    }

    public function getChannelAccount(){
        return $this->hasOne(ChannelAccount::className(), ['id'=>'channel_account_id']);
    }

    public function getUserPaymentInfo()
    {
        return $this->hasOne(UserPaymentInfo::className(), ['user_id'=>'merchant_id']);
    }

    /**
     * 获取订单状态描述
     *
     * @return string
     * @author bootmall@gmail.com
     */
    public function showStatusStr()
    {
        if(in_array($this->status,self::STATUS_LIST_PROCESSING)){
            return '处理中';
        }elseif (in_array($this->status,self::STATUS_LIST_FAIL)){
            return '出款失败';
        }elseif (in_array($this->status,[self::STATUS_REFUND])){
            return '失败已退款';
        }else{
            return self::ARR_STATUS[$this->status]??'-';
        }
    }

    /**
     * 获取所有上级代理账户此订单支付方式的配置
     *
     * @return array
     * @author bootmall@gmail.com
     */
    public function getAllParentRemitConfig()
    {
        return $this->all_parent_remit_config?json_decode($this->all_parent_remit_config,true):[];
    }

    /**
     * 首页统计今天、昨天的代付 成功笔数、失败笔数、失败金额
     * @param $group_id 商户类型 10 - 管理员 20 - 代理 30 - 商户
     * @param $merchant_id 商户ID
     * @param $type today 今天 Yesterday 昨天
     */
    public static function getYesterdayTodayRemit($group_id,$merchant_id,$type,$is_success)
    {
        $remit = [];
        $remitQuery = self::find();
        if($type == 'today'){
            $remitQuery->andFilterCompare('created_at', '>='.strtotime(date("Y-m-d")));
        }else{
            $remitQuery->andFilterCompare('created_at', '>='.strtotime('-1 day',strtotime(date("Y-m-d"))));
            $remitQuery->andFilterCompare('created_at', '<'.strtotime(date("Y-m-d")));
        }
        //$orderTodayQuery->andFilterCompare('created_at', '<'.strtotime($dateEnd));
        if($group_id == 20){
            $remitQuery->andWhere(['merchant_id'=>$merchant_id]);
            $agentWhere = [
                'or',
                ['like','all_parent_agent_id',','.$merchant_id.','],
                ['like','all_parent_agent_id','['.$merchant_id.']'],
                ['like','all_parent_agent_id','['.$merchant_id.','],
                ['like','all_parent_agent_id',','.$merchant_id.']']
            ];
            $remitQuery->andWhere($agentWhere);
        }
        if($group_id == 30){
            $remitQuery->andWhere(['merchant_id'=>$merchant_id]);
        }
        if($is_success == 1 ){
            $remitQuery->andWhere(['status'=>40]);
            $remitQuery->select('sum(amount) as amount,count(id) as total');
        }else{
            $remitQuery->andWhere(['in','status',array(50,60,-10,-20)]);
            $remitQuery->select('count(id) as total,sum(amount) as amount');
        }
        $order = $remitQuery->asArray()->all();
        return $order[0];
    }
}
