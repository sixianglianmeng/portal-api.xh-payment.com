<?php
namespace app\common\models\model;

use yii\behaviors\TimestampBehavior;

/*
 * 帐变表
 */
class Financial extends BaseModel
{
    //状态未完成
    const STATUS_UNFINISHED=0;
    //状态已完成
    const STATUS_FINISHED=10;
    const ARR_STATUS = [
        self::STATUS_UNFINISHED => '未成功',
        self::STATUS_FINISHED => '成功',
    ];

    //账变类型 编号
    const EVENT_TYPE_RECHARGE = 10; # 充值
    const EVENT_TYPE_RECHARGE_FEE = 11; # 充值手续费
    const EVENT_TYPE_BONUS = 12; # 充值分润
    const EVENT_TYPE_REMIT = 20; # 代付
    const EVENT_TYPE_REMIT_FEE = 21; # 代付手续费
    const EVENT_TYPE_REMIT_BONUS = 22; # 代付分润
    const EVENT_TYPE_REFUND_REMIT = 23; # 代付失败退款
    const EVENT_TYPE_REFUND_REMIT_FEE = 24; # 代付失败手续费返还
    const EVENT_TYPE_REFUND_REMIT_BONUS = 25; # 代付失败分润
    const EVENT_TYPE_SYSTEM_PLUS = 30; # 系统加款
    const EVENT_TYPE_SYSTEM_MINUS = 31; # 系统扣款
    const EVENT_TYPE_RECHARGE_FROZEN = 32; # 冻结订单
    const EVENT_TYPE_RECHARGE_UNFROZEN = 33; # 解冻订单
    const EVENT_TYPE_TRANSFER_IN = 34; # 转账入款
    const EVENT_TYPE_TRANSFER_OUT = 35; # 转账出款
    const EVENT_TYPE_TRANSFER_FEE = 36; # 转账手续费

    //账变类型 编号=>描述
    const ARR_EVENT_TYPES = [
        self::EVENT_TYPE_RECHARGE=>'充值订单',
        self::EVENT_TYPE_RECHARGE_FEE=>'充值订单-手续费',
        self::EVENT_TYPE_BONUS=>'充值订单-分润',
        self::EVENT_TYPE_REMIT=>'代付订单',
        self::EVENT_TYPE_REMIT_FEE=>'代付订单-手续费',
        self::EVENT_TYPE_REMIT_BONUS=>'代付订单-分润',
        self::EVENT_TYPE_REFUND_REMIT=>'代付失败-退款',
        self::EVENT_TYPE_REFUND_REMIT_FEE=>'代付失败-手续费返还',
        self::EVENT_TYPE_REFUND_REMIT_BONUS=>'代付失败-分润返还',
        self::EVENT_TYPE_SYSTEM_PLUS=>'系统加款',
        self::EVENT_TYPE_SYSTEM_MINUS=>'系统扣款',
        self::EVENT_TYPE_RECHARGE_FROZEN=>'订单冻结',
        self::EVENT_TYPE_RECHARGE_UNFROZEN=>'订单解冻',
        self::EVENT_TYPE_TRANSFER_IN=>'转账入款',
        self::EVENT_TYPE_TRANSFER_OUT=>'转账出款',
        self::EVENT_TYPE_TRANSFER_FEE=>'转账手续费',
    ];
    //收款记录,结算记录,分润记录,系统加款,系统减款,账户间转账手续费,收款手续费,结算手续费,结算失败金额返还,结算失败手续费返还,账户间转出,账户间转入,结算分润,结算失败分润退还记录

    public static function tableName()
    {
        return '{{%financial}}';
    }

    public function behaviors() {
        return [TimestampBehavior::className(),];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [];
    }

    public function getMerchant(){
        return $this->hasOne(User::className(), ['id'=>'merchant_id']);
    }

    /**
     * 获取事件类型描述
     *
     * @return string
     * @author bootmall@gmail.com
     */
    public static function getEventTypeStr($eventType)
    {
        return self::ARR_EVENT_TYPES[$eventType]??'-';
    }

    /**
     * 获取状态描述
     *
     * @return string
     * @author bootmall@gmail.com
     */
    public static function getStatusStr($status)
    {
        return self::ARR_STATUS[$status]??'-';
    }
}