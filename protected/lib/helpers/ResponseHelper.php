<?php
namespace app\lib\helpers;

use app\common\models\model\LogOperation;
use app\common\models\model\UserPaymentInfo;
use app\components\Macro;
use Yii;
use power\yii2\web\Response;
use yii\helpers\ArrayHelper;

class ResponseHelper extends \power\yii2\helpers\ResponseHelper
{
    /**
     * 输出格式化.
     * 
     * @param array     $data
     * @param string    $message
     * @param integer   $code
     * @param string    $callback
     * @param string    $format     返回数据类型, 默认使用Reponse::FORMAT_JSON
     * @return array
     */
    public static function formatOutput( $code = 200, $message = '', $data = [], $callback = null)
    {
        $callback = self::getCallback($callback);
        self::$status = $code;

        $response = Yii::$app->getResponse();
        $result = [
            'data' => $data,
            'code' => $code,
            'message' => $message,
            'serverTime' => microtime(true),
            'requestId' => $_SERVER['LOG_ID'] ?? '',
        ];

        Yii::pushLog('status', $code);
        //支付网关后台接口单独处理
        if(!empty(Yii::$app->params['jsonFormatType'])
            && Yii::$app->params['jsonFormatType'] == Macro::FORMAT_PAYMENT_GATEWAY_JSON
        ){
            $result = [
                'is_success'        => $code==0?'TRUE':'FALSE',
                'error_msg'       => $message,
            ];
            $result = ArrayHelper::merge($result,$data);
            if(Yii::$app->params['merchantPayment'] && Yii::$app->params['merchantPayment'] instanceof UserPaymentInfo){
                $result['sign'] = SignatureHelper::calcSign($result, Yii::$app->params['merchantPayment']->app_key_md5, Macro::CONST_PAYMENT_GETWAY_SIGN_TYPE);
            }
        }
        elseif ($response->format == Response::FORMAT_HTML) {
//            $result['return_url'] = Yii::$app->request->referrer;
            $result = Yii::$app->controller->render('@app/modules/gateway/views/default/error',$result);
        }
        elseif ($response->format == Response::FORMAT_JSONP) {
            $result = [
                'data'      => $result,
                'callback'  => $callback,
            ];
        }

        //请求操作日志
        LogOperation::inLog($result);

        return $result;
    }
}
