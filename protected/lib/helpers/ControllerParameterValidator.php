<?php
namespace app\lib\helpers;

use app\components\Util;
use power\yii2\exceptions\ParameterValidationExpandException;

/**
 * 
 * @author booter.ui@gmail.com
 *
 */
class ControllerParameterValidator
{
    private static $numberPattern='/^\s*[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?\s*$/';
    private static $mongoDatePattern='/^1[\.\d]{12,}$/';
    private static $mongoIdPattern='/^[0-9A-Fa-f]{24}$/';
    private static $etagPattern='/^[0-9a-zA-Z_\-]+$/';
    private static $linuxPathPattern = '/^\/[^\\\:\/\*\?"<>|]+\/$/';
    private static $usernamePattern = '/^[A-Za-z]{1}[0-9A-Za-z-_]{4,14}$/';
    private static $mobilePattern = '/^1[0-9]{10}$/';

    /**
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param int $default NAN
     * @throws ParameterValidationExpandException
     * @return int
     */
    public static function checkEmpty($mixed, $porp, $default = NAN)
    {
        if (is_array($mixed)) {
            if (isset($mixed[$porp])) {
                $value=$mixed[$porp];
            } elseif (@is_nan($default)) {
                throw new ParameterValidationExpandException("$porp is required!");
            } else {
                return $default;
            }
        } else {
            $value=$mixed;
        }
        
        //处理空白字符
        if (is_string($value)) {
            $value = trim($value); 
        }

        if (!isset($value) || @is_nan($value)) {
            if (@is_nan($default)) {
                throw new ParameterValidationExpandException("$porp can't be empty!");
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param int $min default null
     * @param int $max default null
     * @param int $default NAN
     * @throws ParameterValidationExpandException
     * @return int
     */
    public static function validateInteger($mixed, $porp, $min = null, $max = null, $default = NAN)
    {

        $value = self::checkEmpty($mixed, $porp, $default);

        if (!preg_match(self::$numberPattern, "$value")) {
            if (@is_nan($default)) {
                throw new ParameterValidationExpandException("$porp must be an integer.");
            } else {
                return $default;
            }
        }

        $value=intval($value);

        if ($min!==null && $value<$min) {
            throw new ParameterValidationExpandException("$porp is too small (minimum is $min)");
        }

        if ($max!==null && $value>$max) {
            throw new ParameterValidationExpandException("$porp is too big (maximum is $max)");
        }

        return $value;

    }

    /**
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param float $min
     * @param float $max
     * @param float $default NAN
     * @throws ParameterValidationExpandException
     * @return number
     */
    public static function validateFloat($mixed, $porp, $min = null, $max = null, $default = NAN)
    {
        $value = self::checkEmpty($mixed, $porp, $default);
        
        if (!preg_match(self::$numberPattern, "$value")) {
            if (@is_nan($default)) {
                throw new ParameterValidationExpandException("$porp must be an number.");
            } else {
                return $default;
            }
        }

        $value=floatval($value);

        if ($min!==null && $value<$min) {
            throw new ParameterValidationExpandException("$porp is too small (minimum is $min)");
        }

        if ($max!==null && $value>$max) {
            throw new ParameterValidationExpandException("$porp is too big (maximum is $max)");
        }

        return $value;

    }


    /**
     * 注意：字符串特殊处理， 如果设置了默认值且字符串为空， 则返回默认值
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param int $min
     * @param int $max
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return String
     */
    public static function validateString($mixed, $porp, $min = null, $max = null, $default = NAN)
    {
        $value = self::checkEmpty($mixed, $porp, $default);
        
        //字符串特殊处理， 如果设置了默认值且字符串为空， 则返回默认值
        if (!@is_nan($default)) {
            if (empty($value)) {
                return $default;
            }
        }
        $length=mb_strlen($value); // 这里不能用strlen，字符串长度跟编码有关
        if ($min!==null && $length<$min) {
            throw new ParameterValidationExpandException("$porp is too short (minimum is $min characters)");
        }

        if ($max!==null && $length>$max) {
            throw new ParameterValidationExpandException("$porp is too long (maximum is $max characters)");
        }

        return $value;

    }

    /**
     * 注意：字符串特殊处理， 如果设置了默认值且字符串为空， 则返回默认值
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param int $min
     * @param int $max
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return String
     */
    public static function validateAlnum($mixed, $porp, $min = null, $max = null, $default = NAN)
    {
        $value = self::checkEmpty($mixed, $porp, $default);

        //字符串特殊处理， 如果设置了默认值且字符串为空， 则返回默认值
        if (!@is_nan($default)) {
            if (empty($value)) {
                return $default;
            }
        }
        if(!ctype_alnum($value)){
            throw new ParameterValidationExpandException("$porp must be a alnum");
        }
        $length=mb_strlen($value); // 这里不能用strlen，字符串长度跟编码有关
        if ($min!==null && $length<$min) {
            throw new ParameterValidationExpandException("$porp is too short (minimum is $min characters)");
        }

        if ($max!==null && $length>$max) {
            throw new ParameterValidationExpandException("$porp is too long (maximum is $max characters)");
        }

        return $value;

    }

    /**
     * 1394087667 = 2014-03-06 14:30
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param Array $validValues
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return MongoDate
     */
    public static function validateMongoDate($mixed, $porp, $default = NAN)
    {
        $value = ControllerParameterValidator::validateFloat($mixed, $porp, 0, null, $default);
        if (!$value) {
            if (@is_nan($default)) {
                throw new ParameterValidationExpandException("$porp is invalid");
            } elseif ($default) {
                $value=$default;
            } else {
                return $default;
            }
        }
        
        $startArr = explode('.', $value);
        $startUsec = 0;
        if (isset($startArr[1])) {
            $usec = $startArr[1];
            $usec = str_pad($usec, 6, '0');
            $startUsec = (substr($usec, 0, 3))*1000;
        }
        $startTime = new MongoDate(intval($startArr[0]), $startUsec);
        return $startTime;
    }

    /**
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param Array $validValues
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return String
     */
    public static function validateEnumString($mixed, $porp, $validValues, $default = NAN)
    {
        $value = ControllerParameterValidator::validateString($mixed, $porp, null, null, $default);
        if (@is_nan($default) && !in_array($value, $validValues)) {
            throw new ParameterValidationExpandException("$porp is not valid!");
        }
        return $value;
    }

    /**
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param Array $validValues
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return String
     */
    public static function validateEnumInteger($mixed, $porp, $validValues, $default = NAN)
    {
        $value = ControllerParameterValidator::validateInteger($mixed, $porp, null, null, $default);
        if (@is_nan($default) && !in_array($value, $validValues)) {
            throw new ParameterValidationExpandException("$porp is not valid!");
        }
        return $value;
    }

    /**
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return String
     */
    public static function validateMongoIdAsString($mixed, $porp, $default = NAN)
    {
        $value = ControllerParameterValidator::validateString($mixed, $porp, 24, 24, $default);
        if ($value instanceof MongoId) {
            return $value->__toString();
        }
        
        if (@is_nan($default) && !preg_match(self::$mongoIdPattern, $value)) {
            throw new ParameterValidationExpandException("$porp must be an valid mongoId.");
        }
        return $value;
    }

    /**
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return etag
     */
    public static function validateEtag($mixed, $porp, $default = NAN)
    {
        $value = ControllerParameterValidator::validateString($mixed, $porp, 5, null, $default);
        if (@is_nan($default) && !preg_match(self::$etagPattern, $value)) {
            throw new ParameterValidationExpandException("$porp must be an valid etag.");
        }
        return $value;
    }

    /**
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param float $min
     * @param float $max
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return Array
     */
    public static function validateArray($mixed, $porp, $split = ',', $min = null, $max = null, $default = NAN)
    {
        $value = self::checkEmpty($mixed, $porp, $default);
        if (null==$value) {
            if (@is_nan($default)) {
                return array(); 
            } else {
                return $default; 
            }
        }
        if (!is_array($value)) {
            $value = explode($split, $value);
        }

        $length=count($value);

        if ($min!==null && $length<$min) {
            throw new ParameterValidationExpandException("$porp is too short (minimum is $min elements).");
        }

        if ($max!==null && $length>$max) {
            throw new ParameterValidationExpandException("$porp is too long (maximum is $max elements).");
        }
        return $value;
    }

    /**
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param float $array
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return Array
     */
    public static function validateInArray($mixed, $porp, $array, $default = NAN)
    {
        $value = self::checkEmpty($mixed, $porp, $default);
        if (null==$value) {
            if (@is_nan($default)) {
                return array();
            } else {
                return $default;
            }
        }

        if (!in_array($value, $array)) {
            throw new ParameterValidationExpandException(\Yii::t('app', '{0} value error'));
        }

        return $value;
    }

    /**
     *
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return MongoId
     */
    public static function validateUserId($mixed, $porp, $default = NAN) 
    {
        $ret = ControllerParameterValidator::validateInteger($mixed, $porp, $default);
        return $ret;
    }

    /**
     * 判断是否是合法的用户名
     *
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return MongoId
     */
    public static function validateUsername($mixed, $porp, $default = NAN)
    {
        $value = self::checkEmpty($mixed, $porp, $default);

        if (@is_nan($default) && !preg_match(self::$usernamePattern, $value)) {
            throw new ParameterValidationExpandException("用户名不合法");
        }
        return $value;
    }

    /**
     * 判断是否是合法的手机号
     *
     * @param Array $mixed/mixed $data
     * @param String $porp
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return MongoId
     */
    public static function validateMobile($mixed, $porp, $default = NAN)
    {
        $value = self::checkEmpty($mixed, $porp, $default);

        if (@is_nan($default) && !preg_match(self::$mobilePattern, $value)) {
            throw new ParameterValidationExpandException("手机号不合法");
        }
        return $value;
    }

    /**
     * 判断是否是合法的路径
     * 
     * @param Array  $mixed
     * @param String $porp
     * @param String $default NAN
     * @throws ParameterValidationExpandException
     * @return String
     */
    public static function validatePath($mixed, $porp, $default = NAN)
    {
        $strValue = ControllerParameterValidator::validateString($mixed, $porp, 1, null, $default);
        
        if (@is_nan($default) && 
            preg_match(self::$linuxPathPattern, $strValue) == false
        ) {
            throw new ParameterValidationExpandException("$porp must be an valid path");
        }
        return $strValue;
    }


    /**
     * 获取请求参数
     *
     * @param mixed $request  request数据
     * @param string $param  request参数名
     * @param mixed $default  参数不存在情况下返回的默认值
     * @param string $paramType 参数类型，在Macro类中定义了所有的类型值，为CONST_PARAM_TYPE_开始的常量
     * @param string $errMsg 验证失败的错误消息，在有错误消息和默认值为null且验证失败的情况下，会直接抛出ParameterValidationExpandException异常
     * @param array $extParamRule 验证扩展规则。见validate方法定义
     * @return mixed
     * @author booter.ui@gmail.com
     */
    public static function getRequestParam($request, $param, $default = null, $paramType = '', $errMsg = null, $extParamRule=[])
    {
        $val = isset($request[$param]) ? $request[$param] : null;
        if(is_string($val) || is_numeric($val)) $val = trim($val);

        $validateRet = false;
        if($paramType){
            $validateRet = Util::validate($val,$paramType,$extParamRule);

            if(!empty($errMsg) && $default===null && false === $validateRet){
                throw new ParameterValidationExpandException("{$errMsg}: {$param}");
            }elseif(false === $validateRet){
                $val = null;
            }
        }

        if (null === $val && null !== $default) {
            $val = $default;
        }

        if(is_string($val)) $val = trim($val);

        return $val;
    }
}
