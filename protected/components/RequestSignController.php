<?php

namespace app\components;

use Yii;
use power\yii2\log\LogHelper;
use app\lib\helpers\ResponseHelper;
use power\yii2\exceptions\ParameterValidationExpandException;
use power\yii2\net\exceptions\SignatureNotMatchException;
use app\common\exceptions\OperationFailureException;

/**
 * RequestSignController is the customized with request parameters signed base controller class.
 * All controller classes for this application should extend from this base class.
 */
class RequestSignController extends \power\yii2\web\Controller
{
    public $allParams;

    public function init()
    {
        parent::init();
        // 增加Ajax标识，用于异常处理
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        }
        !defined('MODULE_NAME') && define('MODULE_NAME', SYSTEM_NAME);

        LogHelper::pushLog('params', $_REQUEST);
    }

    public function beforeAction($action)
    {
        $this->getAllParams();
        return parent::beforeAction($action);
    }

    public function behaviors()
    {
        $arrBehaviors = [
            //检查公共函数
            'checkCommonParameters' => [
                'class' => \app\components\filters\CheckCommonParameters::className(),
            ],
            //设置响应格式
            'contentNegotiate' => [
                'class' => \yii\filters\ContentNegotiator::className(),
                'formats' => [
                    'application/json' => \power\yii2\web\Response::FORMAT_JSON,
                    'text/html' => \power\yii2\web\Response::FORMAT_HTML,
                    'application/x-protobuf' => \power\yii2\web\Response::FORMAT_PROTOBUF,
                ],
            ]
        ];

        $arrBehaviors = array_merge(
            $arrBehaviors,
            [
                'verifySign' => [
                    'class' => \app\components\filters\VerifySign::className(),
                    'godSig' => '56610f9fce1cdAcs07098cd80d',
                ],
            ]
        );

        return $arrBehaviors;
    }

    public function accessRules()
    {
        return \yii\helpers\ArrayHelper::merge(
            [
                'allow' => true,
                'roles' => ['?']
            ],
            parent::accessRules()
        );
    }

    public function runAction($id, $params = [])
    {
        try {
            return parent::runAction($id, $params);
        } catch (ParameterValidationExpandException $e) {
            return ResponseHelper::formatOutput(Macro::ERR_PARAM_FORMAT, $e->getMessage());
        } catch (SignatureNotMatchException $e) {
            return ResponseHelper::formatOutput(Macro::ERR_PARAM_SIGN, $e->getMessage());
        } catch (OperationFailureException $e) {
            return $this->handleException($e, true);
        } catch (\Exception $e) {
            LogHelper::error(
                sprintf(
                    'unkown exception occurred. %s:%s trace: %s',
                    get_class($e),
                    $e->getMessage(),
                    str_replace("\n", " ", $e->getTraceAsString())
                )
            );

            return $this->handleException($e);
        }
    }

    /**
     * @param $e 异常对象
     * @param bool $showRawExceptionMessage 是否显示原始的异常信息,建议未捕捉的异常不显示
     * @return array
     */
    protected function handleException($e, $showRawExceptionMessage = false)
    {
        $errCode = $e->getCode();
        $msg     = $e->getMessage();
        if (empty($msg) && !empty(Macro::MSG_LIST[$errCode])) {
            $msg = Macro::MSG_LIST[$errCode];
        }

        if ($errCode === Macro::SUCCESS) $errCode = Macro::FAIL;
        if (YII_DEBUG) {
            throw $e;
            return ResponseHelper::formatOutput($errCode, $msg);
        } else {
            $code = Macro::INTERNAL_SERVER_ERROR;
            if (property_exists($e, 'statusCode')) {
                $code                           = $e->statusCode;
                Yii::$app->response->statusCode = $code;
            }
            if (!$showRawExceptionMessage) $msg = "服务器繁忙,请稍候重试(500)";
            return ResponseHelper::formatOutput($errCode, "服务器内部错误");
        }
    }


    protected function getAllParams()
    {
        $arrQueryParams  = Yii::$app->getRequest()->getQueryParams();
        $arrBodyParams   = Yii::$app->getRequest()->getBodyParams();
        $this->allParams = $arrQueryParams + $arrBodyParams;

        return $this->allParams;
    }
}
