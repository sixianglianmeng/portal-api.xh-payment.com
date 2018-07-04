<?php
namespace app\common\models\model;

use yii\behaviors\TimestampBehavior;

/*
 * 银行列表
 */
class Bank extends BaseModel
{
    static $bankList = [];

    public static function tableName()
    {
        return '{{%bank_list}}';
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
     * 获取所有平台支持的银行列表
     * 
     * @return array
     */
    public static function getPlatformBankList()
    {
        if(self::$bankList){
            return self::$bankList;
        }

        $bankList = self::find()->select('platform_bank_code,bank_name')->where(['<>','platform_bank_code',''])
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