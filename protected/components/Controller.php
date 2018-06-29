<?php
namespace app\components;

use Yii;
use power\yii2\log\LogHelper;
use app\lib\helpers\ResponseHelper;
use power\yii2\exceptions\ParameterValidationExpandException;
use power\yii2\net\exceptions\SignatureNotMatchException;

/**
 * Controller is the customized base controller class.
 * All controller classes for this application should extend from this base class.
 */
class Controller extends \power\yii2\web\Controller
{
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
    
    public function behaviors()
    {
        $arrBehaviors = [
            'checkCommonParameters' => [
                'class'     => \app\components\filters\CheckCommonParameters::className(),
            ],
            'contentNegotiate'  => [
                'class'     => \yii\filters\ContentNegotiator::className(),
                'formats'   => [
                    'application/json'        => \power\yii2\web\Response::FORMAT_JSON,
                    'application/x-protobuf'  => \power\yii2\web\Response::FORMAT_PROTOBUF,
                ],
            ]
        ];
        
        if (Yii::$app->getNet()->getIsEnabled() == false) {
            $arrBehaviors = array_merge(
                $arrBehaviors,
                [
                    'verifySign' => [
                        'class'     => \app\components\filters\VerifySign::className(),
                        'godSig'    => '56610f9fce1cdAcs07098cd80d',
                    ],
                ]
            );
        }
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
            return ResponseHelper::formatOutput(Macro::ERR_PARAM_FORMAT,$e->getMessage());
        } catch (SignatureNotMatchException $e) {
            return ResponseHelper::formatOutput(Macro::ERR_PARAM_SIGN,$e->getMessage());
        } catch (\Exception $e) {
            LogHelper::error(
                sprintf(
                    'unkown exception occurred. %s:%s trace: %s',
                    get_class($e), 
                    $e->getMessage(),
                    str_replace("\n", " ", $e->getTraceAsString())
                )
            );
            if (YII_DEBUG) {
                throw $e;
            } else {
                return ResponseHelper::formatOutput(Macro::INTERNAL_SERVER_ERROR, $e->getMessage());
            }
        }
    }
}
