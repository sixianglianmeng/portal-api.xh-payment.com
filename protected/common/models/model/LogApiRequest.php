<?php

namespace app\common\models\model;

use Yii;

/**
 * This is the model class for table "p_log_api_request".
 *
 * @property int $id 自增ID
 * @property int $merchant_id 商户ID
 * @property string $merchant_name 商户登录名
 * @property int $channel_account_id 三方账户ID
 * @property string $channel_name 三方账户名称
 * @property string $event_type 操作类型
 * @property string $event_id 操作的对象ID，如订单ID，提款ID等
 * @property string $request_url 请求地址
 * @property int $request_method 请求类型：1get 2post
 * @property string $post_data post参数
 * @property string $response_data 请求对方时的响应内容，或者我方响应给对方的内容
 * @property string $remote_ip 请求我方接口的来源IP
 * @property string $referer 请求我方接口来源页面地址
 * @property string $useragent 请求我方接口客户端信息
 * @property string $device_id 请求我方接口客户端ID
 * @property int $created_at 记录生成时间
 * @property int $updated_at 记录更新时间
 * @property int $deleted_at 记录软删除时间
 * @property string $bak 备注
 */
class LogApiRequest extends BaseModel
{
    const EVENT_TYPE_IN_RECHARGE_ADD = 101;
    const EVENT_TYPE_IN_RECHARGE_RETURN = 102;
    const EVENT_TYPE_IN_RECHARGE_NOTIFY = 103;
    const EVENT_TYPE_IN_RECHARGE_QUERY = 104;
    const EVENT_TYPE_IN_REMIT_ADD = 120;
    const EVENT_TYPE_IN_REMIT_QUERY = 121;
    const EVENT_TYPE_IN_REMIT_NOTIFY = 122;
    const EVENT_TYPE_IN_BALANCE_QUERY = 130;
    const EVENT_TYPE_IN_RECHARGE_CASHIER = 140;
    const EVENT_TYPE_IN_RECHARGE_REDIRECT = 141;

    const EVENT_TYPE_OUT_RECHARGE_ADD = 201;
    const EVENT_TYPE_OUT_RECHARGE_BATCH_ADD = 202;
    const EVENT_TYPE_OUT_RECHARGE_QUERY = 203;
    const EVENT_TYPE_OUT_RECHARGE_NOTIFY = 204;
    const EVENT_TYPE_OUT_REMIT_ADD = 220;
    const EVENT_TYPE_OUT_REMIT_QUERY = 221;
    const EVENT_TYPE_OUT_REMIT_NOTIFY = 222;
    const EVENT_TYPE_OUT_BALANCE_QUERY = 230;
    const EVENT_TYPE_OUT_REGISTER_BANKCARD = 240;

    const ARR_EVENT_TYPE = [
        self::EVENT_TYPE_IN_RECHARGE_ADD    => '商户充值请求',
        self::EVENT_TYPE_IN_RECHARGE_RETURN => '三方充值同步回调',
        self::EVENT_TYPE_IN_RECHARGE_NOTIFY => '三方充值异步回调',
        self::EVENT_TYPE_IN_RECHARGE_QUERY  => '商户充值查询',
        self::EVENT_TYPE_IN_REMIT_ADD       => '商户提款请求',
        self::EVENT_TYPE_IN_REMIT_QUERY     => '商户提款查询',
        self::EVENT_TYPE_IN_REMIT_NOTIFY    => '三方提款异步回调',
        self::EVENT_TYPE_IN_BALANCE_QUERY   => '商户余额查询',
        self::EVENT_TYPE_IN_RECHARGE_CASHIER   => '充值订单访问收银台',
        self::EVENT_TYPE_IN_RECHARGE_REDIRECT   => '充值订单跳转',

        self::EVENT_TYPE_OUT_RECHARGE_ADD       => '请求到三方充值',
        self::EVENT_TYPE_OUT_RECHARGE_BATCH_ADD => '请求到三方批量充值',
        self::EVENT_TYPE_OUT_RECHARGE_QUERY     => '请求到三方充值查询',
        self::EVENT_TYPE_OUT_RECHARGE_NOTIFY    => '订单通知商户',
        self::EVENT_TYPE_OUT_REMIT_NOTIFY       => '出款通知商户',
        self::EVENT_TYPE_OUT_REMIT_ADD          => '请求到三方提款',
        self::EVENT_TYPE_OUT_REMIT_QUERY        => '请求到三方提款查询',
        self::EVENT_TYPE_OUT_BALANCE_QUERY      => '请求到三方余额查询',
        self::EVENT_TYPE_OUT_REGISTER_BANKCARD  => '请求到三方注册银行卡',
    ];

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'p_log_api_request';
    }

    /*
    * 获取事件类型描述
    *
    * @param int $groupId 分组ID
    * @return string
    * @author bootmall@gmail.com
    */
    public static function getEventTypeStr($eventType)
    {
        return self::ARR_EVENT_TYPE[$eventType]??'-';
    }
}
