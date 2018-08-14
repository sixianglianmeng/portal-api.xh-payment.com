<?php

/**
 * 支付平台接口demo
 *
 * @version 1.2
 * @require php>=7.0。如需支持PHP5.4+，请取消所有方法中的参数类型声明
 */
class AwesomePaymentClientClient
{
    //支付服务器地址
    public $apiBaseUri = 'http://gateway.gd95516.com';
    //商户代码
    public $merchantCode = '';
    //商户密钥
    public $merchantKey = '';

    /**
     * @param string $merchantCode 商户代码
     * @param string $merchantKey 商户密钥
     * @param string $apiBaseUri 支付服务器地址
     */
    public function __construct(string $merchantCode, string $merchantKey, string $apiBaseUri=''){
        $this->merchantCode = $merchantCode;
        $this->merchantKey  = $merchantKey;
        if($apiBaseUri){
            $this->apiBaseUri  = $apiBaseUri;
        }
    }

    /**
     * 测试所有接口
     *
     */
    public static function testAll()
    {

        $merchantCode = '3035971';
        $merchantKey = 'adbf0sb6b-f4ka-32af-7329-f19d5b42';

        $apiBaseUri = 'https://gateway.gd95516.com';

        $orderNo = date('YmdHis').mt_rand(10000,99999);
        $api = new AwesomePaymentClient($merchantCode, $merchantKey, $apiBaseUri);

        //API后台下单
        AwesomePaymentClient::mlog("API后台下单");
        $order = $api->order($orderNo,'WY',mt_rand(1,10),'http://www.demo.com/recharge/notify','ABC');
        //订单查询
        AwesomePaymentClient::mlog("订单查询");
        $ret = $api->orderStatus($orderNo);

        $orderNo = date('YmdHis').mt_rand(10000,99999);
        //代付出款
        AwesomePaymentClient::mlog("代付出款");
        $ret = $api->remit($orderNo,1,'ABC','付芬芳','6228230475512431468','http://www.demo.com/remit/notify');
        //代付订单查询
        AwesomePaymentClient::mlog("代付订单查询");
        $ret = $api->remitStatus($orderNo);

        //余额查询
        AwesomePaymentClient::mlog("余额查询");
        $ret = $api->balance();
    }

    /**
     * 收款API后台下单接口
     * 下单成功后将用户重定向至返回的url参数值地址即可进行支付
     *
     * @param string $orderNo 订单号
     * @param string $payType 支付类型
     * @param float $orderAmount 订单金额
     * @param string $notifyUrl 异步通知地址
     * @param string $bankCode 网银支付银行编码
     * @param string $returnUrl 同步通知跳转地址
     * @param string $returnParams 回传参数
     *
     * @return array
     */
    public function order(string $orderNo, string $payType, float $orderAmount, string $notifyUrl,
                          string $bankCode='', string $returnUrl='', string $returnParams=''
    ){
        $params = [
            'merchant_code'=>$this->merchantCode,
            'order_no'=>$orderNo,
            'pay_type'=>$payType,
            'bank_code'=>$bankCode,
            'order_amount'=>$orderAmount,
            'order_time'=>time(),
            'customer_ip'=>self::getClinetIp(),
            'notify_url'=>$notifyUrl,
            'return_url'=>$returnUrl,
            'return_params'=>$returnParams,
        ];

        $params['sign'] = self::md5Sign($params,$this->merchantKey);

        $url = $this->apiBaseUri.'/api/v1/order';
        $ret = self::sendRequest($url,$params);
    }

    /**
     * 收款订单查询接口
     *
     * @param string $orderNo 订单号
     *
     * @return array
     */
    public function orderStatus(string $orderNo){
        $params = [
            'merchant_code'=>$this->merchantCode,
            'order_no'=>$orderNo,
            'query_time'=>time(),
        ];

        $params['sign'] = self::md5Sign($params,$this->merchantKey);

        $url = $this->apiBaseUri.'/api/v1/query';
        $ret = self::sendRequest($url,$params);

        return $ret;
    }


    /**
     * 充值回调处理(仅提供签名校验部分，实际处理流程以商户自己流程为准)
     *
     * @param sting $requestJson 回调请求json字符串
     */
    public function orderNotifyHandle($requestJson){
        //$requestJson = file_get_contents("php://input");
        if(empty($requestJson)){
            throw new \Exception("回调json字符串为空");
        }

        $request = json_decode($requestJson,true);
        if(empty($request)){
            throw new \Exception("回调json字符串转化为数组后为空");
        }
        $localSign = self::md5Sign($request,$this->merchantKey);
        if(empty($request['sign']) || $request['sign']!=$localSign){
            throw new \Exception("回调加密校验失败");
        }
        if(!empty($request['trade_status']) && $request['trade_status']=='success'
            && !empty($request['order_amount'])
        ){
            //支付成功，进行订单处理
        }
    }

    /**
     * 代付申请下单接口
     *
     * @param string $orderNo 订单号
     * @param float $orderAmount 订单金额
     * @param string $bankCode $bankCode 银行编码
     * @param string $accountName 持卡人姓名
     * @param string $accountNumber 银行卡号
     * @param string $notifyUrl 异步通知地址
     *
     * @return array
     */
    public function remit(string $orderNo,float $orderAmount,string $bankCode, string $accountName, string $accountNumber,
                          string $notifyUrl = ''
    ){
        $params = [
            'merchant_code'=>$this->merchantCode,
            'order_no'=>$orderNo,
            'order_amount'=>$orderAmount,
            'order_time'=>time(),
            'account_name'=>$accountName,
            'account_number'=>$accountNumber,
            'bank_code'=>$bankCode,
        ];

        $params['sign'] = self::md5Sign($params,$this->merchantKey);

        $url = $this->apiBaseUri.'/api/v1/remit';
        $ret = self::sendRequest($url,$params);

        return $ret;
    }

    /**
     * 查询代付订单状态
     *
     * @param string $orderNo 订单号
     *
     * @return array
     */
    public function remitStatus(string $orderNo){
        $params = [
            'merchant_code'=>$this->merchantCode,
            'order_no'=>$orderNo,
            'query_time'=>time(),
        ];

        $params['sign'] = self::md5Sign($params,$this->merchantKey);

        $url = $this->apiBaseUri.'/api/v1/remit_query';
        $ret = self::sendRequest($url,$params);

        return $ret;
    }

    /**
     * 余额查询接口
     *
     * @return array
     */
    public function balance(){
        $params = [
            'merchant_code'=>$this->merchantCode,
            'query_time'=>time(),
        ];

        $params['sign'] = self::md5Sign($params,$this->merchantKey);

        $url = $this->apiBaseUri.'/api/v1/balance';
        $ret = self::sendRequest($url,$params);

        return $ret;
    }

    /**
     * 文本日志记录
     * @param sting|array|object $log
     */
    static  public function mlog($msg){
        $file = __DIR__.'/common'.date('ymd').'.log';
        if(is_array($msg)) $log = json_encode($msg,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $msg = date("Ymd H:i:s ") . ' ' . $msg . PHP_EOL;
        echo $msg;
        file_put_contents($file, $msg,FILE_APPEND);
    }

    /**
     * 对参数进行MD5签名
     *
     * @param array $params 进行签名的参数
     * @param string $strSecret 签名密钥
     * @return string
     */
    public static function md5Sign(array $params, string $strSecret){
        unset($params['key']);

        $signParams      = [];
        foreach ($params as $key => $value) {
            if($value == '') continue;
            $signParams[] = "$key=$value";
        }

        sort($signParams,SORT_STRING);
        $strToSign = implode('&', $signParams);

        self::mlog("请求参数：".json_encode($params,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        self::mlog("参数排序后字符串：".$strToSign);
        self::mlog("加上key后字符串：".$strToSign.'&key='.$strSecret);

        $signStr = md5($strToSign.'&key='.$strSecret);
        self::mlog("md5签名值：".$signStr);
        return $signStr;
    }

    /**
     * 获取客户端IP
     *
     * @return string
     */
    static public function getClinetIp()
    {
        $ip = '';
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR', 'HTTP_CLIENT_IP'] as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                        $ip = '';
                    }
                }
            }

            if ($ip) break;
        }

        return $ip;
    }

    /**
     * CURL POST请求
     *
     * param string $url 请求的URL
     * param array $data post的数组
     * param array $headers 请求header
     * param int $timeoutMs 超时毫秒数
     */
    public static function curlPost(string $url, array $data, array $headers=[], $timeoutMs=10000)
    {
        $ch        = curl_init();
        $headers[] = "Accept-Charset: utf-8";
        $headers[] = "Accept:application/json";
        $headers[] = "Content-Type:application/json;charset=utf-8";

        $data = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.87 Safari/537.36');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeoutMs);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
//        self::mlog("{$url}, {$data}, {$httpCode}, {$result}");
        self::mlog("请求地址：{$url}");
        self::mlog("请求数据：{$data}");
        self::mlog("响应数据：{$result}");

        curl_close($ch);

        return $result;
    }

    /**
     * 发送接口请求
     *
     * @param string $url
     * @param array $data
     *
     * @return array
     */
    public static function sendRequest(string $url, array $data)
    {
        $retStr = self::curlPost($url, $data);
        $ret = json_decode($retStr, true);
        return $ret;
    }
}