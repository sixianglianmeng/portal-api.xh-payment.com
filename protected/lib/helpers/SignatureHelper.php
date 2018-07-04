<?php
namespace app\lib\helpers;

use Yii;
use power\yii2\helpers\SecurityHelper;

class SignatureHelper extends SecurityHelper
{
    public static function calcSign($arrParams, $strSecret, $signType='MD5')
    {
        foreach ($arrParams as $strKey => $strVal) {
//             $arrParams[$strKey] = rawurlencode($strVal);
        }
        $signType = strtoupper($signType);
        $signedStr = '';
        switch ($signType){
            case 'MD5':
                $signedStr = self::md5Sign($arrParams, $strSecret);
                break;
            case 'RSA':
                break;
            default:
                break;
        }

        return $signedStr;
    }

    public static function md5Sign($params, $strSecret){
        if (is_array($params)) {
            unset($params['key']);
            $a      = $params;
            $params = array();
            foreach ($a as $key => $value) {
                if($value=='') continue;
                $params[] = "$key=$value";
            }
            sort($params,SORT_STRING);
            $params = implode('&', $params);
        } elseif (is_string($params)) {

        } else {
            return false;
        }

        $signStr = md5($params.'&key='.$strSecret);
//        Yii::info(['md5Sign string: ',$signStr,$params]);
        return $signStr;
    }
}
