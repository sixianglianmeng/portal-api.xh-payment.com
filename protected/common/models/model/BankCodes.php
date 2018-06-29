<?php
namespace app\common\models\model;

use yii\behaviors\TimestampBehavior;

/*
 * 帐变表
 */
class BankCodes extends BaseModel
{
    //所有银行列表
    public static $bankList = null;

    public static function tableName()
    {
        return '{{%bank_codes}}';
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

    /**
     * 获取通道银行
     */
    public static function getBankList($channelIds)
    {
        return self::find()->select('platform_bank_code,bank_name')->where(['in','channel_id',$channelIds])->cache(300)->distinct()->asArray()->all();
    }

    /**
     * 获取三方银行代码
     *
     * @param int $channelId 渠道id
     * @param string $platformCode 本支付平台的银行代码
     *
     * @return string
     */
    public static function getChannelBankCode($channelId, $platformCode)
    {
        $code = self::findOne(['channel_id'=>$channelId,'platform_bank_code'=>$platformCode]);

        return $code?$code->channel_bank_code:'';
    }

    /**
     * 获取所有银行列表
     * 
     * @return array
     */
    public static function getAllBankList()
    {
        if(self::$bankList){
            return self::$bankList;
        }

        $bankList = self::find()->select('platform_bank_code,bank_name')->groupBy('platform_bank_code')
            ->cache(300)->asArray()->all();
        foreach ($bankList as $b){
            self::$bankList[$b['platform_bank_code']] = $b;
        }

        return self::$bankList;
    }

    /**
     * 根据平台银行代码获取银行名称
     *
     * @return array
     */
    public static function getBankNameByCode($code)
    {
        $banks = self::getAllBankList();

        return $banks[$code]['bank_name']??$code;

    }
}