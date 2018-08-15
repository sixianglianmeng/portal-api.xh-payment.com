<?php

namespace app\components;
use app\common\exceptions\OperationFailureException;
use app\common\models\model\SiteConfig;
use Yii;

/*
 * 后端支付平台接口，直接调用即可，成功返回json中data数据，不成功抛异常
 * $ret = RpcPaymentGateway::syncRechargeOrderStatus(1800)
 */
class RpcPaymentGateway
{
    /**
     * 出款
     *
     * @param string $merchantUsername 充值账户名
     * @param array $remits 出款信息列表[['amount'=>'金额','bank_code'=>'银行代码','bank_no'=>'卡号','bank_account'=>'持卡人',
     *                      'bank_province'=>'开户省','bank_city'=>'开户市','bank_branch'=>'开户网点']]
     *
     * @throws \Exception
     * @return array
     */
    public static function remit($merchantUsername, $remits, $batOrderNo)
    {
        $ret = self::call('/remit/add',['merchant_username'=>$merchantUsername,'remits'=>$remits,'batOrderNo'=>$batOrderNo]);

        return $ret;
    }

    /**
     * 充值
     *
     * @param decimal $amount 充值金额
     * @param int $payType 充值渠道
     * @param string $bankCode 银行代码
     * @param string $merchantName 充值账户名
     * @param string $type 订单类型
     * @param string $bak 备注
     * @throws \Exception
     * @return array
     */
    public static function recharge($amount, $payType, $bankCode, $merchantName, $type=2, $bak='')
    {
        $ret = self::call('/order/add',['amount'=>$amount,'pay_type'=>$payType,'bank_code'=>$bankCode,'merchant_username'=>$merchantName,'type'=>$type,'bak'=>$bak]);

        return $ret;
    }

    /**
     * 强制到第三方查询同步充值订单状态
     *
     * @param int $inSeconds 多少小时之内的
     * @param array $orderNoList 要同步的订单号列表
     * @throws \Exception
     * @return array
     */
    public static function syncRechargeOrderStatus($inSeconds = 1800, $orderNoList = null)
    {
        $ret = self::call('/order/sync-status',['inSeconds'=>$inSeconds,'orderNoList'=>$orderNoList]);

        return $ret;
    }

    /**
     * 发送订单通知结果到商户
     *
     * @param int $inSeconds 多少小时之内的
     * @param array $orderNoList 要同步的订单号列表
     * @throws \Exception
     * @return array
     */
    public static function sendRechargeOrderNotify($inSeconds = 1800, $orderNoList = null)
    {
        $ret = self::call('/order/send-notify',['inSeconds'=>$inSeconds,'orderNoList'=>$orderNoList]);

        return $ret;
    }


    /**
     * 强制设置订单为成功
     *
     * @param array $orderNoList 要同步的订单列表[['order_no'=>'订单号','bak'=>'设置原因']]
     * @throws \Exception
     * @return array
     */
    public static function setOrderSuccess($orderNoList)
    {
        $ret = self::call('/order/set-success',['orderNoList'=>$orderNoList]);

        return $ret;
    }

    /**
     * 强制到第三方查询同步充值出款状态
     *
     * @param int $inSeconds 多少小时之内的
     * @param array $orderNoList 要同步的出款号列表
     * @throws \Exception
     * @return array
     */
    public static function syncRemitStatus($inSeconds = 1800, $orderNoList = null)
    {
        $ret = self::call('/remit/sync-status',['inSeconds'=>$inSeconds,'orderNoList'=>$orderNoList]);

        return $ret;
    }

    /**
     * 强制设置出款为成功
     *
     * @param array $orderNoList 要设置的出款列表[['order_no'=>'出款号','bak'=>'设置原因']]
     * @throws \Exception
     * @return array
     */
    public static function setRemitSuccess($orderNoList)
    {
        $ret = self::call('/remit/set-success',['orderNoList'=>$orderNoList]);

        return $ret;
    }

    /**
     * 强制设置出款为失败
     *
     * @param array $orderNoList 要设置的出款列表[['order_no'=>'出款号','bak'=>'设置原因']]
     * @throws \Exception
     * @return array
     */
    public static function setRemitFail($orderNoList)
    {
        $ret = self::call('/remit/set-fail',['orderNoList'=>$orderNoList]);

        return $ret;
    }

    /**
     * 强制设置出款为已审核
     *
     * @param array $orderNoList 要设置的出款列表[['order_no'=>'出款号','bak'=>'设置原因']]
     * @throws \Exception
     * @return array
     */
    public static function setRemitChecked($orderNoList)
    {
        $ret = self::call('/remit/set-checked',['orderNoList'=>$orderNoList]);

        return $ret;
    }

    /**
     * 冻结订单
     *
     * @param array $orderNoList 要同步的订单列表[['order_no'=>'订单号','bak'=>'设置原因']]
     * @throws \Exception
     * @return array
     */
    public static function setOrderFrozen($orderNoList)
    {
        $ret = self::call('/order/frozen',['orderNoList'=>$orderNoList]);

        return $ret;
    }

    /**
     * 解冻订单
     *
     * @param array $orderNoList 要同步的订单列表[['order_no'=>'订单号','bak'=>'设置原因']]
     * @throws \Exception
     * @return array
     */
    public static function setOrderUnFrozen($orderNoList)
    {
        $ret = self::call('/order/un-frozen',['orderNoList'=>$orderNoList]);

        return $ret;
    }

    /**
     * rpc访问接口
     *
     * @param string 接口路径
     * @param array 接口参数
     * @throws \Exception
     * @return
     */
    public static function call($path, $params=[])
    {

        $params['_nonce_'] = Util::uuid();
        $key = SiteConfig::cacheGetContent('gateway_rpc_key');
        $params['_sign_'] = md5($key.$params['_nonce_']);
        $params['op_uid'] = Yii::$app->user->identity->id;
        $params['op_username'] = Yii::$app->user->identity->username;
        $params['op_ip'] = Yii::$app->request->userIP;

        $header = [
            'Content-Type: application/json',
            'HTTP_X_RPC_KEY: '.$params['_sign_']
        ];
        $uriBase = SiteConfig::cacheGetContent('payment_api_base_uri');
        $strUrl = $uriBase . '/gateway/v1/inner';

        try {
            $api = $strUrl.$path;
            $timeout = 10000;

            $jsonData = json_encode($params);
            $jsonRet = self::postMs($api, $jsonData, $timeout, [], $header);
            Yii::info("gateway rpc call({$path}): ".$jsonData.' ret:'.$jsonRet);

            if ($jsonRet === false || empty($jsonRet)) {
                throw new OperationFailureException('远程服务器操作失败');
            }

            try{
                $ret = json_decode($jsonRet, true);
            }catch(\Exception $ex){
                throw new OperationFailureException('远程服务器响应不正确');
            }

            if (!array_key_exists('code', $ret)) {
                throw new OperationFailureException('远程服务器响应不正确' , Errno::INTERNAL_SERVER_ERROR);
            }

            if ($ret['code'] != 0) {
                throw new OperationFailureException($ret['message']);
            }

            return $ret;
        } catch (\Exception $ex) {
            $msg = $ex->getMessage();
            Yii::error('gateway rpc exception: ' . $msg);
            throw $ex;
            return [];
        }
    }

    /**
     * 以 POST 方式执行请求.
     *
     * @param string $url       请求目标地址
     * @param mixed  $params    请求参数
     * @param int    $timeout   请求超时时间毫秒
     * @param array  $options   其它cURL选项
     * @static
     * @access public
     * @throws \Exception
     * @return 错误返回:false 正确返回:结果内容
     */
    public static function postMs($url, $params, $timeout = 500, $options = array(), $header = [], &$headerCallBack = null)
    {
        $arrUrl = parse_url($url);
        $profileName = (isset($arrUrl['host']) ? strval($arrUrl['host']) : '') . (isset($arrUrl['path']) ? strval($arrUrl['path']) : '');
        Yii::profileStart($profileName);

        $ch = curl_init();
        // forward logid
        if (isset($_SERVER['LOG_ID']) && is_string($_SERVER['LOG_ID']) && !empty($_SERVER['LOG_ID'])) {
            $header[] = 'X-Ngx-LogId: ' . strval($_SERVER['LOG_ID']);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (isset($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        }
        if (isset($headerCallBack)) {
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'self::headerCallBack');
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 500);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt_array($ch, $options);
        $return = curl_exec($ch);
        Yii::profileEnd($profileName);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode!=200) {
            Yii::error('post request failed. url-' . $url . ' params-' . json_encode($params) .' '. $return);

            throw new OperationFailureException('远程服务器操作失败:'.$return);
        }
        if (curl_errno($ch)) {
            Yii::error('post request failed. url-' . $url . ' params-' . json_encode($params) . ' errno-' . curl_errno($ch) . ' error-' . curl_error($ch));

            throw new OperationFailureException('远程服务器操作失败:'.curl_error($ch));
        }
        curl_close($ch);
        return $return;
    }
}
