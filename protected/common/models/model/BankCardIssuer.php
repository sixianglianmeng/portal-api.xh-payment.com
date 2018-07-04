<?php
namespace app\common\models\model;

use app\components\Util;
use yii\behaviors\TimestampBehavior;
use Yii;

/*
 * 银行卡发卡行特征码(前六位)对照表
 */
class BankCardIssuer extends BaseModel
{
    public static function tableName()
    {
        return '{{%bank_card_issuer}}';
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
     * 根据卡号获取发卡行信息
     *
     * @return array|null
     */
    public static function getIssuerByBankNo($bankNo)
    {
        $code = substr($bankNo,0,6);
        $issuser = BankCardIssuer::findOne(['code'=>$code]);

        return $issuser;

    }

    /**
     * 检测银行卡号与平台银行代码是否匹配
     *
     * @param string $bankNo 银行卡号
     * @param string $platFormBankCode 卡号对应的平台银行代码
     * @return bool
     */
    public static function checkBankNoBankCode(string $bankNo, string $platFormBankCode)
    {
        $issuser = BankCardIssuer::getIssuerByBankNo($bankNo);
        $ret = false;
        if(!$issuser){
            $json = BankCardIssuer::getBankNoInfoFromAlipay($bankNo);
            if($json){
                $issuser = new BankCardIssuer();
                $issuser->code = substr($bankNo,0,6);
                $issuser->demo = $bankNo;
                $issuser->type = $json['cardType'];
                $issuser->ali_bank_code = $json['bank'];

                $bank = Bank::findOne(['ali_bank_code'=>$json['bank']]);
                if($bank){
                    $issuser->bank_name = $bank->bank_name;
                    $issuser->platform_bank_code = $bank->platform_bank_code;
                }else{
                    Yii::error("can not found bank info({$bankNo}) from ali code: {$json['bank']}");
                }

                $issuser->save();
            }
        }

        if($issuser && $platFormBankCode == $issuser->platform_bank_code)
        {
            $ret = true;
        }else{
            Yii::error("bank no info err: bankno:{$bankNo}, platcode:{$platFormBankCode}");
        }

        return $ret;
    }

    /**
     * 从支付宝获取银行卡银行信息
     *
     * @param string $bankNo
     * @return array
     */
    public static function getBankNoInfoFromAlipay(string $bankNo)
    {
        $url = "https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?_input_charset=utf-8&cardNo={$bankNo}&cardBinCheck=true";
        $jsonStr = Util::curlGet($url);
        $json = json_decode($jsonStr,true);
        //{"bank":"CCB","validated":true,"cardType":"DC","key":"6217002750007962402","messages":[],"stat":"ok"}
        if(empty($json['stat']) || $json['stat']!='ok'
         || empty($json['validated'])
        ){
            Yii:error("error alipay get bank card info: {$bankNo},{$jsonStr}");
            $json = [];
        }

        return $json;
    }


}