<?php
namespace app\common\models\model;

/*
 * 渠道帐号支付方式表
 */
class ChannelAccountRechargeMethod extends BaseModel
{
    public static function tableName()
    {
        return '{{%channel_account_recharge_methods}}';
    }

    public function getChannelAccount()
    {
        return $this->hasOne(ChannelAccount::className(), ['id'=>'channel_account_id']);
    }
}