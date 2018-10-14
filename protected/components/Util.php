<?php
namespace app\components;

use app\common\exceptions\OperationFailureException;
use app\common\models\model\Channel;
use app\common\models\model\SiteConfig;
use yii\base\Security;
use Yii;

class Util
{
    /**
     * 公共方法
     */

    public static function multisort($arrays,$sort_key,$sort_order=SORT_ASC,$sort_type=SORT_NUMERIC ){
        if(is_array($arrays)){
            foreach ($arrays as $array){
                if(is_array($array)){
                    $key_arrays[] = $array[$sort_key];
                }else{
                    return false;
                }
            }
        }else{
            return false;
        }
        array_multisort($key_arrays,$sort_order,$sort_type,$arrays);
        return $arrays;
    }

    /**
     * 获取请求参数
     *
     * @param string    request参数名
     * @param mixed     参数不存在情况下的默认值
     * @param string    参数类型，在Macro 类中定义了所有的类型值，为CONST_PARAM_TYPE_开始的常量
     * @param string    验证失败情况下的消息，若设置了，会直接返回一个http json消息
     * @return mixed
     * @author bootmall
     */
    public static function getRequestParam($param, $default = null, $paramType = '', $errMsg = null, $extParamRule=[])
    {
        $val = isset($_REQUEST[$param]) ? $_REQUEST[$param] : null;
        if(is_string($val) || is_numeric($val)) $val = trim($val);

        if($paramType){
            if($val === null || false === self::validate($val,$paramType,$extParamRule)){
                if($errMsg !== null){
                    throw new \app\common\exceptions\OperationFailureException($errMsg.":{$param}",Macro::ERR_PARAM_FORMAT);
                }
            }
        }

        if (null === $val && null != $default) {
            $val = $default;
        }

        return $val;
    }

    /*
     * @param string $val 要验证的变量
     * @param string $type 变量类型，在Macro 类中定义了所有的类型值，为CONST_PARAM_TYPE_开始的常量
     * @param array $extRule 验证扩展规则。根据不同验证类型来定义。 数字类型，规则为下限和上限；字符串类型，规则为字符串长度下限和上限
     * @return boolean
     * @author bootmall
     */
    public static function validate($val,$type,$extRule=[]){
//    clog(['validate:',$val,$type]);
        //根据不同的验证规则
        //$exp可以定义为布尔值，正则表达式，可调用进行判断的对象(is_callable: 函数，类方法等)。
        switch ($type) {
            case Macro::CONST_PARAM_TYPE_NUMERIC_STRING:
                preg_match("/^\d+$/",$val,$matched);
                $exp = !empty($matched);
                if($extRule){
                    $strLen = mb_strlen($val);
                    if(count($extRule)==2){
                        $exp = $exp && $strLen >= $extRule[0] && $strLen<=$extRule[1];
                    }elseif(count($extRule)==1){
                        $exp = $exp && $strLen >= $extRule[0];
                    }
                }
                break;

            case Macro::CONST_PARAM_TYPE_INT:
                preg_match("/^\d+$/",$val,$matched);
                $exp = !empty($matched);
                if($extRule){
                    if(count($extRule)==2){
                        $exp = $exp && $val >= $extRule[0] && $val<=$extRule[1];
                    }elseif(count($extRule)==1){
                        $exp = $exp && $val >= $extRule[0];
                    }
                }
                break;
            case Macro::CONST_PARAM_TYPE_INT_GT_ZERO:
                preg_match("/^\d+$/",$val,$matched);
                $exp = !empty($matched);
                $extRule[0] = 1;
                if($extRule){
                    if(count($extRule)==2){
                        $exp = $exp && $val >= $extRule[0] && $val<=$extRule[1];
                    }elseif(count($extRule)==1){
                        $exp = $exp && $val >= $extRule[0];
                    }
                }
                break;
            case Macro::CONST_PARAM_TYPE_DECIMAL:
//                $exp = "/^\d+\.\d+$/";
//                if($extRule){
//                    $exp = preg_match($exp,$val);
//                    if($exp && count($extRule)==2){
//                        $exp = $val>=$extRule[0] && $val<=$extRule[1];
//                    }elseif($exp && count($extRule)==1){
//                        $exp = $val>=$extRule[0];
//                    }
//                }
                $exp = filter_var($val, FILTER_VALIDATE_FLOAT);
                $exp = $exp !== false;
                if($exp && $extRule){
                    if(count($extRule)==2){
                        $exp = $val>=$extRule[0] && $val<=$extRule[1];
                    }elseif(count($extRule)==1){
                        $exp = $val>=$extRule[0];
                    }
                }
                break;
            case Macro::CONST_PARAM_TYPE_ORDER_NO:
                $exp = "/^[0-9a-z_-]{10,24}$/i";
                break;
            case Macro::CONST_PARAM_TYPE_EMAIL:
                $exp = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
                $exp = $exp !== false;
//                $exp = "/^[0-9a-zA-Z]+@(([0-9a-zA-Z]+)[.])+[a-z]{2,4}$/i";
                break;
            case Macro::CONST_PARAM_TYPE_MOBILE:
                $exp =  "/^1[3|5|7|8|9]{1}[0-9]{9}$/i";
                break;
            case Macro::CONST_PARAM_TYPE_USERNAME:
                $exp =  "/^[1-9a-zA-Z]{1}[0-9a-zA-Z\-_]{5,32}$/i";
                break;
            case Macro::CONST_PARAM_TYPE_PASSWORD:
                $exp =  "/^.{5,32}$/i";
                break;
            case Macro::CONST_PARAM_TYPE_ALNUM:
                $exp =  "ctype_alnum";
                break;
            case Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE:
                $exp =  "/^[0-9a-zA-Z-_]{0,}$/i";
                if(is_array($extRule)){
                    if(count($extRule)==2){
                        $exp =  "/^[0-9a-zA-Z-_]{".$extRule[0].",".$extRule[1]."}$/i";
                    }elseif(count($extRule)==1){
                        $exp =  "/^[0-9a-zA-Z-_]{".$extRule[0].",}$/i";
                    }
                }

                break;
            case Macro::CONST_PARAM_TYPE_IDCARD:
                $exp =  "/^[1-9]{1}[0-9]{14,17}$/i";
                break;
            case Macro::CONST_PARAM_TYPE_MD5:
                $exp =  "/^[0-9a-z]{32}$/i";
                break;
            case Macro::CONST_PARAM_TYPE_BANK_NO:
                $exp = preg_match("/^\d{6,24}$/i",$val) && self::checkLuhn($val);
                break;
            case Macro::CONST_PARAM_TYPE_DATE:
                $ts = strtotime($val.' 0:0');

                $exp = checkdate(date('m',$ts), date('d',$ts), date('Y',$ts));
                break;
            case Macro::CONST_PARAM_TYPE_SORT:
                $exp =  "/^[0-9a-zA-Z_\-+]{1,32}$/i";
                break;
            case Macro::CONST_PARAM_TYPE_CHINESE:
                preg_match("/^[\x{4e00}-\x{9fa5}]+$/u",$val,$matched);
                $exp = !empty($matched);
                $extRule[0] = 1;
                $strLen = mb_strlen($val);
                if($extRule){
                    if(count($extRule)==2){
                        $exp = $exp && $strLen >= $extRule[0] && $strLen<=$extRule[1];
                    }elseif(count($extRule)==1){
                        $exp = $exp && $strLen >= $extRule[0];
                    }
                }
                break;
            case Macro::CONST_PARAM_TYPE_DATETIME:
                $ts = strtotime($val);
                $exp = $ts > 0;
                break;
            case Macro::CONST_PARAM_TYPE_IP:
                $exp = filter_var($val, FILTER_VALIDATE_IP);
                $exp = $exp !== false;
                break;
            case Macro::CONST_PARAM_TYPE_IPv4:
                $exp = filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
                $exp = $exp !== false;
                break;
            case Macro::CONST_PARAM_TYPE_IPv6:
                $exp = filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
                $exp = $exp !== false;
                break;
            case Macro::CONST_PARAM_TYPE_DEV_PLATFORM:
                $exp =  in_array($val, Macro::CONST_DEV_OS_ALL);
                break;
            case Macro::CONST_PARAM_TYPE_ENUM:
                $exp = false;
                if(is_array($extRule)){
                    $exp =  in_array($val, $extRule);
                }elseif(is_callable($extRule)){
                    $arr = call_user_func($extRule);
                    $exp =  in_array($val, $arr);
                }
                break;
            case Macro::CONST_PARAM_TYPE_STRING:
                $exp = is_string($val);
                if($extRule){
                    $strLen = mb_strlen($val);
                    if(count($extRule)==2){
                        $exp = $exp && $strLen>=$extRule[0] && $strLen<=$extRule[1];
                    }elseif(count($extRule)==1){
                        $exp = $exp && $strLen>=$extRule[0];
                    }
                }
                break;
            case Macro::CONST_PARAM_TYPE_ARRAY:
                $exp =  is_array($val);
                if($extRule){
                    $exp && count($val)>=$extRule[0];
                }
                break;
            case Macro::CONST_PARAM_TYPE_ARRAY_HAS_KEY:
                $exp =  is_array($extRule) && isset($extRule[$val]);
                break;
            case Macro::CONST_PARAM_TYPE_BANKCODE:
//                $codes = Macro::getAllBankCode();
                $exp = (!empty($val) && !empty(Macro::BANK_LIST[$val]));
                break;
            case Macro::CONST_PARAM_TYPE_PAYTYPE:
                $exp = (!empty($val) && !empty(Channel::ARR_METHOD[$val]));
                break;
            default:
                $exp = '';
                break;
        }

        if($exp === true || $exp === false){
            return $exp;
        }

        if($exp && substr($exp,0,1)!='/' && is_callable($exp)){
            return call_user_func($exp, $val);
        }

        if($exp && substr($exp,0,1)=='/'){
            preg_match($exp,$val,$matched);
            return !empty($matched);
        }

        if($exp=='' && is_callable($type)){
            return call_user_func($type, $val);
        }

        return false;
    }

    /**
     * 去除多余的转义字符
     */
    public static function doStripSlashes()
    {
        if (!get_magic_quotes_gpc()) {
            $_GET     = stripslashesDeep($_GET);
            $_POST    = stripslashesDeep($_POST);
            $_COOKIE  = stripslashesDeep($_COOKIE);
            $_REQUEST = stripslashesDeep($_REQUEST);
        }
    }

    /**
     * 递归去除转义字符
     */
    public static function stripSlashesDeep($value)
    {
        $value = is_array($value) ? array_map('stripslashesDeep', $value) : addslashes($value);
        return $value;
    }

    /**
     * 去除代码中的空白和注释
     * @param string $content 代码内容
     * @return string
     */
    public static function stripWhitespace($content)
    {
        $stripStr   = '';
        //分析php源码
        $tokens     = token_get_all($content);
        $last_space = false;
        for ($i = 0, $j = count($tokens); $i < $j; $i++) {
            if (is_string($tokens[$i])) {
                $last_space = false;
                $stripStr  .= $tokens[$i];
            } else {
                switch ($tokens[$i][0]) {
                    //过滤各种PHP注释
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        break;
                    //过滤空格
                    case T_WHITESPACE:
                        if (!$last_space) {
                            $stripStr  .= ' ';
                            $last_space = true;
                        }
                        break;
                    case T_START_HEREDOC:
                        $stripStr .= "<<<THINK\n";
                        break;
                    case T_END_HEREDOC:
                        $stripStr .= "THINK;\n";
                        for ($k = $i + 1; $k < $j; $k++) {
                            if (is_string($tokens[$k]) && $tokens[$k] == ';') {
                                $i = $k;
                                break;
                            } elseif ($tokens[$k][0] == T_CLOSE_TAG) {
                                break;
                            }
                        }
                        break;
                    default:
                        $last_space = false;
                        $stripStr  .= $tokens[$i][1];
                }
            }
        }
        return $stripStr;
    }


    /**
     * 得到字符串的utf8格式
     */
    public static function getUtf8Char($word)
    {
        if (preg_match("/^([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){1}/", $word) == true || preg_match("/([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){1}$/", $word) == true || preg_match("/([" . chr(228) . "-" . chr(233) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}[" . chr(128) . "-" . chr(191) . "]{1}){2,}/", $word) == true) {
            return $word;
        } else {
            return iconv('gbk', 'utf-8', $word);
        }
    }

    /*
     * unicode字符串转换为utf8字符串
     */
    public static function unicode2utf8($str)
    {
        if (!$str) return $str;
        $decode = json_decode($str);
        if ($decode) return $decode;
        $str    = '["' . $str . '"]';
        $decode = json_decode($str);
        if (count($decode) == 1) {
            return $decode[0];
        }
        return $str;
    }
    /**
     * 获取客户端IP地址
     * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @return mixed
     */
    public static function getClientIp($type = 0)
    {
        static $ip = '';
        $type = $type ? 1 : 0;

        if ($ip !== '') {
            return $ip[$type];
        }

        foreach (['HTTP_TRUE_CLIENT_IP','HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED',  'REMOTE_ADDR', 'HTTP_CLIENT_IP'] as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false) {// |FILTER_FLAG_NO_RES_RANGE
                        $ip = '';
                    }
                }
            }

            if($ip) break;
        }

        if (!$ip) return $ip;

        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip   = $long ? [$ip, $long] : ['0.0.0.0', 0];

        return $ip[$type];
    }

    /**
     * CURL JSON POST请求
     *
     * param string $url 请求的URL
     * param array $data post的数组
     * param array $headers 请求header
     * param int $timeoutMs 超时毫秒数
     */
    public static function curlPostJson(string $url, array $data, array $headers=[], $timeoutMs=5000)
    {
        $ch        = curl_init();
        $headers[] = "Accept-Charset: utf-8";
        $headers[] = "Accept:application/json";
        $headers[] = "Content-Type:application/json;charset=utf-8";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.87 Safari/537.36');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
        $result = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode!=200) {
            Yii::error('post request failed. url-' . $url . ' params-' . json_encode($data) .' '. $result);

            throw new OperationFailureException('请求远程失败:'.$result, $httpCode);
        }
        if (curl_errno($ch)) {
            Yii::error('post request failed. url-' . $url . ' params-' . json_encode($data) . ' errno-' . curl_errno($ch) . ' error-' . curl_error($ch));

            throw new OperationFailureException('请求远程失败:'.curl_error($ch), $httpCode);
        }

        curl_close($ch);

        return $result;
    }

    /**
     * CURL POST
     *
     * param url 抓取的URL
     * param data post的数组
     */
    public static function curlPost($url, $data, $headers=[], $timeoutMs=5000)
    {
        $ch        = curl_init();
        $headers[] = "Accept-Charset: utf-8";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
//        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.87 Safari/537.36');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
        $result = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode!=200) {
            Yii::error('post request failed. url-' . $url . ' params-' . json_encode($data) .' '. $result);

            throw new OperationFailureException('请求远程失败:'.$result, $httpCode);
        }
        if (curl_errno($ch)) {
            Yii::error('post request failed. url-' . $url . ' params-' . json_encode($data) . ' errno-' . curl_errno($ch) . ' error-' . curl_error($ch));

            throw new OperationFailureException('请求远程失败:'.curl_error($ch), $httpCode);
        }

        //关闭curl
        curl_close($ch);

        return $result;
    }

    /**
     * CURL GET
     *
     * param url 抓取的URL
     */
    public static function curlGet($url, $headers=[])
    {
        $ch        = curl_init();
        $headers[] = "Accept-Charset: utf-8";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.87 Safari/537.36');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode!=200) {
            Yii::error('post request failed. url-' . $url . ' params-' . json_encode($data) .' '. $result);

            throw new OperationFailureException('请求远程失败:'.$result, $httpCode);
        }
        if (curl_errno($ch)) {
            Yii::error('post request failed. url-' . $url . ' params-' . json_encode($data) . ' errno-' . curl_errno($ch) . ' error-' . curl_error($ch));

            throw new OperationFailureException('请求远程失败:'.curl_error($ch), $httpCode);
        }

        //关闭curl
        curl_close($ch);

        return $result;
    }

    /**
     * @desc 获取querystring
     * @param $url
     * @return array|string
     */
    public static function convertUrlQuery($url)
    {
        $arr = parse_url($url);
        $query = $arr['query'];
        if (!empty($query)) {
            $queryParts = explode('&', $query);

            $params = array();
            foreach ($queryParts as $param) {
                $item = explode('=', $param);
                $params[$item[0]] = $item[1];
            }
        } else {
            $params = '';
        }
        return $params;
    }

//时间处理
    public static function timeTrans($the_time)
    {
        $now_time = time();
        $show_time = is_numeric($the_time)?$the_time:strtotime($the_time);
        $dur = $now_time - $show_time;
        if ($dur < 0) {
            return $the_time;
        } else {
            if ($dur < 60) {
                return $dur . '秒前';
            } else {
                if ($dur < 3600) {
                    return floor($dur / 60) . '分钟前';
                } else {
                    if ($dur < 86400) {
                        return floor($dur / 3600) . '小时前';
                    } else {
                        if ($dur < 259200) {//3天内
                            return floor($dur / 86400) . '天前';
                        } else {
                            return is_numeric($the_time)?date('Y-m-d',$the_time):$the_time;
                        }
                    }
                }
            }
        }
    }

    public static function getHumanTime($t)
    {
        $f=array(
            '31536000'=>'年',
            '2592000'=>'个月',
            '604800'=>'星期',
            '86400'=>'天',
            '3600'=>'小时',
            '60'=>'分钟',
            '1'=>'秒'
        );
        foreach ($f as $k => $v) {
            if (0 !=$c=floor($t/(int)$k)) {
                return $c.$v;
            }
        }
    }

    public static function humanTime($t)
    {
        $sign = true;
        if ($t < 0) {
            $sign = false;
            $t    = -1 * $t;
        }

        $f      = array(
            '31536000' => '年',
            '2592000'  => '个月',
            '86400'    => '天',
            '3600'     => '小时',
            '60'       => '分钟',
            '1'        => '秒'
        );
        $return = '';
        foreach ($f as $k => $v) {
            if (0 != $c = floor($t / (int)$k)) {
                $return .= $c . $v;

                $t -= $c * $k;
            }
        }

        if (empty($return)) {
            $return = '0秒';
        }

        if ($sign) {
            return $return;
        } else {
            return '负' . $return;
        }
    }

    public static function headerNocache()
    {
        header("Cache-control:no-cache,no-store,must-revalidate");
        header("Pragma:no-cache");
        header("Expires:-1");
    }


    public static function headerCross()
    {
// 指定允许其他域名访问
        header('Access-Control-Allow-Origin:*');
// 响应类型
        header('Access-Control-Allow-Methods:POST');
// 响应头设置
        header('Access-Control-Allow-Headers:x-requested-with,content-type');
    }

    /**
     * 字符串转数字
     * @param $string
     * @return int|string
     */
    public static function strToNumber($string)
    {
        $len = strlen($string);
        $sum = '';
        for ($i = 0; $i < $len; $i++) {
            $num = ord($string[$i]);
            $sum += $num;
        }
        return $sum;
    }

    /**
     * 高效取出两个数组的差集
     */
    public static function arrayDiffFast($array_1, $array_2)
    {
        $array_2 = array_flip($array_2);
        foreach ($array_1 as $key => $item) {
            if (isset($array_2[$item])) {
                unset($array_1[$key]);
            }
        }

        return $array_1;
    }

    /**
     * 二维数组根据字段进行排序
     * @params array $array 需要排序的数组
     * @params string $field 排序的字段
     * @params string $sort 排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
     */
    public static function arraySequence($array, $field, $sort = 'SORT_DESC')
    {
        $arrSort = array();
        foreach ($array as $uniqid => $row) {
            foreach ($row as $key => $value) {
                $arrSort[$key][$uniqid] = $value;
            }
        }
        array_multisort($arrSort[$field], constant($sort), $array);
        return $array;
    }

    /**
     * 这个写在公共方法里边
     *
     * @param        $string
     * @param string $operation
     * @param string $key
     * @param int    $expiry
     *
     * @return string
     */
    public static function encryptionCode($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {
        $ckey_length = 4;
        $key         = md5($key != '' ? $key : 'lhs_simple_encryption_code_87063');
        $keya        = md5(substr($key, 0, 16));
        $keyb        = md5(substr($key, 16, 16));
        $keyc        = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey   = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);

        $string        = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);

        $result = '';
        $box    = range(0, 255);

        $rndkey = array();
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for ($j = $i = 0; $i < 256; $i++) {
            $j       = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp     = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a       = ($a + 1) % 256;
            $j       = ($j + $box[$a]) % 256;
            $tmp     = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if ($operation == 'DECODE') {
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc . str_replace('=', '', base64_encode($result));
        }
    }

    public static function encodeData($decodeData)
    {
        $decodeData = json_encode($decodeData);
        $decodeData = encryptionCode($decodeData, 'ENCODE');
        $data       = base64_encode($decodeData);

        return $data;
    }

    /**
     * @param string $encodeData
     * @return mixed
     */
    public static function decodeData($encodeData)
    {
        $encodeData = trim($encodeData);
        $encodeData = urldecode($encodeData);
        $encodeData = base64_decode($encodeData);
        $encodeData = encryptionCode($encodeData, 'DECODE');
        $data       = json_decode($encodeData, true);

        return $data;
    }

    public static function requestFilter($string)
    {
        $string = str_replace('%20', '', $string);
        $string = str_replace('%27', '', $string);
        $string = str_replace('%2527', '', $string);
        $string = str_replace('*', '', $string);
        $string = str_replace('"', '&quot;', $string);
        $string = str_replace('\'', '', $string);
        $string = str_replace('"', '', $string);
        $string = str_replace(';', '', $string);
        $string = str_replace('<', '&lt;', $string);
        $string = str_replace('>', '&gt;', $string);
        $string = str_replace('{', '', $string);
        $string = str_replace('}', '', $string);
        $string = str_replace('\\', '', $string);
        return $string;
    }

//签名校验
    public static function checkSha1Sign($key, $data)
    {
        ksort($data);
        $newKey = sha1(http_build_query($data));
        return $key == $newKey;
    }
//签名校验
    public static function getSha1Sign($key, $params)
    {
        $sort_data         = $params;
        $sort_data['salt'] = !empty($key) ? $key : 'payment_666.-i';
        ksort($sort_data);
        $sign_key = sha1(http_build_query($sort_data));

        $params['sign_key']  = $sign_key;
        $params['timestamp'] = time();

        return $params;
    }

    public static function uuid(string $type = 'rand24'){
//        $chars = (string)(new \MongoDB\BSON\ObjectId());
        $chars = (new Security())->generateRandomString(24);
        switch ($type){
            case 'md5':
                $chars = md5($chars);
                break;
            case 'uuid':
                $chars = md5($chars);
                $uuid  = substr($chars,0,8) . '-';
                $uuid .= substr($chars,8,4) . '-';
                $uuid .= substr($chars,12,4) . '-';
                $uuid .= substr($chars,16,4) . '-';
                $uuid .= substr($chars,20,12);
                $chars = $uuid;
                break;
            case 'rand24':
            default:
                break;
        }
        return $chars;
    }

    /**
     * 将[k->v]形式数组变换成[[k,v]]形式数组
     *
     * @param
     * @return
     */
    public static function ArrayKeyValToDimetric($array,$kName=0,$vName=1)
    {
        $newArr = [];
        foreach ($array as $k=>$v){
            $newArr[] = [$kName=>$k,$vName=>$v];
        }

        return $newArr;
    }

    /**
     * 抛出异常
     *
     * @param int $code 错误代码
     * @param string $msg 错误消息，若为空，则会自动根据错误代码从消息列表查询描述
     * @return
     */
    public static function throwException($code, $msg='')
    {
        if(empty($msg) && !empty(Macro::MSG_LIST[$code])){
            $msg = Macro::MSG_LIST[$code];
        }

        throw new OperationFailureException($msg, $code);
    }

    /**
     * 获取当前带小数点的时间戳
     *
     * @return float
     */
    public static function getMillisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }

    /**
     * 检测是否是移动类型终端访问
     * @return string
     */
    public static function isMobileDevice()
    {
        //全部变成小写字母
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        //分别进行判断
        if (strpos($agent, 'iphone')!==false
            || strpos($agent, 'ipad')!==false
            || strpos($agent, 'android')!==false
            || strpos($agent, 'micromessenger')!==false
            || strpos($agent, 'alipay')!==false
        ) {
            return true;
        }

        return false;

    }

    /**
     * 往下拉菜单等列表数组中添加一项
     *
     * @param array  $list 转换前数组
     * @param string $fieldKey 数组列表建名
     * @param string $valKey 数组列表值
     * @param bool   $changeObject2List 是否强制转换为list型数组,例如['1'=>'成功','2'=>失败]会转为[['1'=>'成功'],['2'=>失败]]
     */
    public static function addAllLabelToOptionList(array $list,bool $changeObject2List=false, string $fieldKey='id',string $valKey='val')
    {
        $istListArr = isset($list[0]);
        if($changeObject2List){
            $newList = [[$fieldKey=>Macro::SELECT_OPTION_ALL,$valKey=>'全部']];
            foreach ($list as $k=>$v){
                $newList[] = $istListArr&&is_array($v)?$v:[$fieldKey=>$k,$valKey=>$v];
            }
            $list = $newList;
        }

        //数字类型下标
        if(isset($list[0]) && !$changeObject2List){
            $newList = [[$fieldKey=>Macro::SELECT_OPTION_ALL,$valKey=>'全部']];
            foreach ($list as $k=>$v){
                $newList[] = $v;
            }
            $list = $newList;
        }
        //字符串类型下标
        elseif(!isset($list[0])){
            $list[Macro::SELECT_OPTION_ALL]='全部';
        }

        return $list;
    }

    /**
     * 银行卡Luhn算法校验
     *
     * @param string $num
     * @return bool
     */
    public static function checkLuhn($num){
        if(empty($num)) return false;
        $num_arr = str_split($num);
        krsort($num_arr);
        foreach ($num_arr as $key=>&$value){
            if(($key+1)%2 == 0){
                $value = $value*2;
                if($value>=10){
                    $value = 1 + ($value % 10);
                }
            }
        }
        $total = array_sum($num_arr);
        if(($total%10) == 0){
            // 符合Luhn算法
            return true;
        }else{
            return false;
        }
    }

    /**
     * 发送telegram消息
     *
     * @param string|array $message 消息内容
     * @param string $chatId 会话id,默认为系统普通通知会话id
     * @return bool
     */
    public static function sendTelegramMessage($message, $chatId=''){
        $telgramKey = SiteConfig::cacheGetContent('sys_notice_tegram_key');
        $telgramUrl = SiteConfig::cacheGetContent('sys_notice_tegram_url');
        if(!$chatId){
            $chatId = SiteConfig::cacheGetContent('sys_notice_tegram_business_chatid');
        }
        if(!is_string($message)) $message = json_encode($message,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $title = gethostname();
        if(Yii::$app->request->getIsConsoleRequest()){
            $title.=" ".pathinfo(WWW_DIR)['basename'];
        }else{
            $title.=" ".Yii::$app->request->hostName;
        }
        $message = "{$message}\n{$title}";
        if($telgramKey && $telgramUrl && $chatId){
            $data = [
                'msg'=> $message,
                'key'=> $telgramKey,
                'chatId'=> $chatId,
            ];
            $ret = Util::curlPost($telgramUrl,$data);
            if($ret!='ok'){
                Yii::info("error to send telegram message:{$chatId},{$message}");
            }
        }else{
            Yii::info("telegram message config error,{$telgramKey},{$chatId},{$telgramUrl}");
        }
    }

    /**
     * 发送邮件
     * @param $toEmail
     * @param $title
     * @param $message
     */
    public static function sendEmailMessage($toEmail,$message,$type)
    {
        $typeArray = [
            'updateEmail' => '变更邮箱',
            'bindApiIp' => '绑定API接口IP',
        ];
        $mail = Yii::$app->mailer->compose();
        //目标邮箱
        $mail->setTo($toEmail);
        //邮件标题
        $mail->setSubject($typeArray[$type]);
        //邮件内容，这里可以使用 HTML 代码
        $tmpTime = time() + 15*60;
        $html = "<div>验证码：".$message."</div>";
        $html .= "<div>此验证码仅限 <span style='color: red'>".$typeArray[$type]."</span>,有效时间截止".date('Y-m-d H:i:s',$tmpTime)."</div>";
        $mail->setHtmlBody($html);
        $mail->send();//发送
    }
}