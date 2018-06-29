<?php
namespace app\lib\helpers;

use Yii;

class UtilHelper
{
    /**
     * MongoDate 转换为 float
     *
     * @param MongoDate $_date            
     * @static
     *
     * @access public
     * @return float
     */
    public static function mongoDate2Float(\MongoDate $date)
    {
        $sec = $date->sec;
        $usec = $date->usec;
        return $sec + ($usec / 1000000);
    }

    /**
     * 浮点型转换为MongoDate.
     *
     * @param float $floatNum            
     * @static
     *
     * @access public
     * @return \MongoDate
     */
    public static function float2MongoDate($floatNum)
    {
        $aryDate = explode('.', $floatNum);
        $sec = intval($aryDate[0]);
        
        if (isset($aryDate[1]) == true) {
            $usec = intval(str_pad($aryDate[1], 6, '0'));
        } else {
            $usec = 0;
        }
        return new \MongoDate($sec, $usec);
    }
    
    /**
     * 对内容加密.
     *
     * @param string    $strRawInput   加密内容
     * @param string    $strSecretKey  加密私钥
     * @return string
     */
    public static function encrypt($strRawInput, $strSecretKey)
    {
        Yii::profileStart(__CLASS__ . ':' . __FUNCTION__);
        $algorithm = new Crypt3Des($strSecretKey, $strSecretKey);
        $strEncOutput = $algorithm->encrypt($strRawInput);
        Yii::profileEnd(__CLASS__ . ':' . __FUNCTION__);
        return $strEncOutput;
    }
    
    /**
     * 对内容解密.
     *
     * @param string    $strEncInput    解密内容
     * @param string    $strSecretKey   解密私钥
     * @return string/false 若解密失败，返回false
     */
    public static function decrypt($strEncInput, $strSecretKey)
    {
        Yii::profileStart(__CLASS__ . ':' . __FUNCTION__);
        $algorithm = new Crypt3Des($strSecretKey, $strSecretKey);
        $strRawOutput = $algorithm->decrypt($strEncInput);
        Yii::profileEnd(__CLASS__ . ':' . __FUNCTION__);
        return $strRawOutput;
    }
}
