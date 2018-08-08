<?php
namespace app\common\models\model;

use yii\behaviors\TimestampBehavior;

/*
 * 第三方支付渠道信息
 */
class Channel extends BaseModel
{
    const METHOD_WEBBANK = 'WY';
    const METHOD_WECHAT_QR = 'WXQR';
    const METHOD_ALIPAY_QR = 'ALIQR';
    const METHOD_QQ_QR = 'QQQR';
    const METHOD_UNIONPAY_QR = 'UNQR';
    const METHOD_WECHAT_H5 = 'WXH5';
    const METHOD_ALIPAY_H5 = 'ALIH5';
    const METHOD_QQ_H5 = 'QQH5';
    const METHOD_BANK_QUICK = 'WYKJ';
    const METHOD_JD_H5 = 'JDH5';
    const METHOD_JD_QR = 'JDQR';
    const METHOD_UNIONPAY_H5 = 'UNH5';
    const METHOD_UNIONPAY_QUICK = 'UNKJ';
    const METHOD_WECHAT_CODEBAR = 'WXTM';
    const METHOD_QQ_CODEBAR = 'QQTM';
    const METHOD_ALIAPY_CODEBAR = 'ALITM';
    const METHOD_BANK_TRANSFER = 'BANKTRANS';
    const METHOD_ALIPAY_TRANSFER = 'ALITRANS';
    const METHOD_WECHAT_TRANSFER = 'WXTRANS';

    const ARR_METHOD = [
        self::METHOD_WEBBANK    => '网银',
        self::METHOD_BANK_QUICK => '网银快捷',

        self::METHOD_WECHAT_QR      => '微信扫码',
        self::METHOD_WECHAT_H5      => '微信H5',
        self::METHOD_WECHAT_CODEBAR => '微信条码',

        self::METHOD_ALIPAY_QR      => '支付宝扫码',
        self::METHOD_ALIPAY_H5      => '支付宝H5',
        self::METHOD_ALIAPY_CODEBAR => '支付宝条码',
        self::METHOD_QQ_QR          => 'QQ扫码',
        self::METHOD_QQ_H5          => 'QQH5',
        self::METHOD_QQ_CODEBAR     => 'QQ条码',
        self::METHOD_JD_QR          => '京东扫码',
        self::METHOD_JD_H5          => '京东H5',

        self::METHOD_UNIONPAY_QR    => '银联扫码',
        self::METHOD_UNIONPAY_H5     => '银联H5',
        self::METHOD_UNIONPAY_QUICK  => '银联快捷',
        self::METHOD_BANK_TRANSFER   => '银行转账',
        self::METHOD_ALIPAY_TRANSFER => '支付宝转账',
        self::METHOD_WECHAT_TRANSFER => '微信转账',
    ];

    const ARR_METHOD_EN = [
        self::METHOD_WEBBANK        => 'webBank',
        self::METHOD_WECHAT_QR      => 'wechatQr',
        self::METHOD_WECHAT_H5      => 'wechatH5',
        self::METHOD_ALIPAY_QR      => 'alipayQr',
        self::METHOD_ALIPAY_H5      => 'alipayH5',
        self::METHOD_QQ_QR          => 'qqQr',
        self::METHOD_QQ_H5          => 'qqH5',
        self::METHOD_UNIONPAY_QR    => 'unoinPayQr',
        self::METHOD_UNIONPAY_QUICK => 'unoinPayQuick',
        self::METHOD_BANK_QUICK     => 'bankQuickPay',
        self::METHOD_JD_H5          => 'jdH5',
        self::METHOD_JD_QR          => 'jdQr',
        self::METHOD_UNIONPAY_H5      => 'unionPayH5',
        self::METHOD_WECHAT_CODEBAR   => 'wechatCodeBar',
        self::METHOD_QQ_CODEBAR       => 'qqCodeBar',
        self::METHOD_ALIAPY_CODEBAR   => 'alipayCodeBar',
        self::METHOD_BANK_TRANSFER    => 'bankTransfer',
        self::METHOD_ALIPAY_TRANSFER  => 'alipayTransfer',
        self::METHOD_WECHAT_TRANSFER  => 'wechatTransfer',
    ];

    public static function tableName()
    {
        return '{{%channels}}';
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

    public function getPayMethods()
    {
        $methods = empty($this->pay_methods)?[]:json_decode($this->pay_methods,true);

        return $methods;
    }

    /*
     * 充值渠道是否支持某个支付方式
     */
    public function hasPaymentMethod($methodId)
    {
        $has = strpos($this->methods,'"id":'.$methodId.',')!==false;
        return $has;
    }

    public static function getPayMethodsStr($methodId){
        return self::ARR_METHOD[$methodId]??'';
    }

    public static function getPayMethodEnStr($methodId){
        return self::ARR_METHOD_EN[$methodId]??'';
    }

    public function getPayMethodsArr()
    {
        $raWmethods = empty($this->pay_methods)?[]:json_decode($this->pay_methods,true);
        $methods = [];
        foreach ($raWmethods as $m){
            $methods[] = [
                'id'=>$m['id'],
                'rate'=>$m['rate'],
                'name'=>Channel::ARR_METHOD[$m['id']]??'支付方式：'.$m['id'],
            ];
        }

        return $methods;
    }

    public static function getALLChannel()
    {
        return self::find()->select('id,name')->asArray()->all();
    }


    public function getServerIps()
    {
        return empty($this->server_ips)?[]:explode(',',$this->server_ips);
    }
}