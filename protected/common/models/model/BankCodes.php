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
        return self::find()->select('platform_bank_code,bank_name')->where(['in','channel_id',$channelIds])->cache(10)->distinct()->asArray()->all();
    }

    /**
     * 获取通道银行
     */
    public static function getRechargeBankList($channelIds)
    {
        return self::find()->select('platform_bank_code,bank_name')->where(['in','channel_id',$channelIds])->andWhere(['can_recharge'=>1])->cache(10)->distinct()->asArray()->all();
    }

    /**
     * 获取通道银行
     */
    public static function getRemitBankList($channelIds)
    {
        return self::find()->select('platform_bank_code,bank_name')->where(['in','channel_id',$channelIds])->andWhere(['can_remit'=>1])->cache(10)->distinct()->asArray()->all();
    }

    /**
     * 获取三方银行代码
     *
     * @param int $channelId 渠道id
     * @param string $platformCode 本支付平台的银行代码
     * @param string $type 查找类型recharge支持充值,remit支持代付,all支持所有
     *
     * @return string
     */
    public static function getChannelBankCode(int $channelId, string $platformCode, string $type='recharge')
    {
        $filter = ['channel_id'=>$channelId,'platform_bank_code'=>$platformCode];
        switch ($type){
            case 'recharge':
                $filter['can_recharge']=1;
                break;
            case 'remit':
                $filter['can_remit']=1;
                break;
            case 'all':
                $filter['can_recharge']=1;
                $filter['can_remit']=1;
                break;

        }
        $code = self::findOne($filter);

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