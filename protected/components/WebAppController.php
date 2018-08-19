<?php

    namespace app\components;

    use app\common\exceptions\OperationFailureException;
    use app\lib\helpers\ResponseHelper;
    use power\yii2\exceptions\ParameterValidationExpandException;
    use power\yii2\log\LogHelper;
    use power\yii2\net\exceptions\SignatureNotMatchException;
    use Yii;
    use yii\filters\auth\CompositeAuth;
    use yii\filters\auth\HttpBasicAuth;
    use yii\filters\auth\HttpBearerAuth;
    use yii\filters\auth\QueryParamAuth;
    use yii\filters\Cors;
    use yii\filters\RateLimiter;
    use yii\rest\Controller;
    use yii\web\UnauthorizedHttpException;

    /**
     * WebAppController is the customized base web app api controller class.
     * All controller classes for this application should extend from this base class.
     */
    class WebAppController extends Controller
    {
        public $allParams;
        public $user;

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
            $corsOriginDomain = empty(Yii::$app->params['corsOriginDomain']) ? ['*'] : Yii::$app->params['corsOriginDomain'];
            LogHelper::pushLog('corsOriginDomain', $corsOriginDomain);

            //fixed cors: axios must has header: Access-Control-Allow-Headers
            $reqHeaders = Yii::$app->request->headers;
            if (Yii::$app->getRequest()->getMethod() == 'OPTIONS'
                //            && $reqHeaders->has('access-control-request-origin')
                && $reqHeaders->has('access-control-request-method')
            ) {
                header('Access-Control-Allow-Origin:*');
                header('Access-Control-Allow-Methods:POST');
                header('Access-Control-Allow-Headers:x-requested-with,authorization,content-type,x-client-id');
                exit;
            }

            if ($reqHeaders->has('access-control-request-headers')) {
                $headers = Yii::$app->response->headers;
                $headers->add('Access-Control-Allow-Headers', $reqHeaders->get('access-control-request-headers'));
            }

            $behaviors = [
                //corsFilter不放第一个有可能导致不起作用
                'corsFilter'            => [
                    'class' => Cors::className(),
                    'cors'  => [
                        'Origin'                        => ['*'],//定义允许来源的数组
                        'Access-Control-Request-Method' => ['POST'],//允许动作的数组
                    ],
                ],
                'checkCommonParameters' => [
                    'class' => \app\components\filters\CheckCommonParameters::className(),
                ],

                //接口只支持json和protobug格式
                'contentNegotiate'      => [
                    'class'   => \yii\filters\ContentNegotiator::className(),
                    'formats' => [
                        'application/json'       => \power\yii2\web\Response::FORMAT_JSON,
                        'application/x-protobuf' => \power\yii2\web\Response::FORMAT_PROTOBUF,
                    ],
                ],

                'authenticator' => [
                    'class'       => CompositeAuth::className(),
                    'authMethods' => [
                        HttpBasicAuth::className(),
                        HttpBearerAuth::className(),
                        QueryParamAuth::className(),
                    ],
                    //                'tokenParam' => 'access-token',
                    'optional'    => [
                        'login',
                    ],
                ],
            ];

            $rateLimit = true;
            if (!empty($rateLimit)) {
                $behaviors['rateLimiter'] = [
                    'class'                  => RateLimiter::className(),
                    'enableRateLimitHeaders' => true,
                ];
            }

            return $behaviors;
        }

        public function accessRules()
        {
            return \yii\helpers\ArrayHelper::merge(
                [
                    'allow' => true,
                    'roles' => ['?'],
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
            } catch (UnauthorizedHttpException $e) {
                return ResponseHelper::formatOutput(Macro::ERR_PERMISSION, $e->getMessage());
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
                if(!$showRawExceptionMessage) $msg = "服务器繁忙,请稍候重试(500)";
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
