<?php
namespace app\modules\api\controllers\v1;

use app\modules\api\controllers\BaseController;
use Yii;
use app\lib\helpers\ControllerParameterValidator;
use app\components\Macro;
use power\yii2\captcha\CaptchaBuilder;
use app\lib\helpers\ResponseHelper;

/*
 * 验证码
 */
class CaptchaController extends BaseController
{
    public function beforeAction($action)
    {
        return parent::beforeAction($action);
    }

    public function behaviors()
    {
        $parentBehaviors = parent::behaviors();
        //验证码不需要token验证
        $behaviors['authenticator']['optional'] = ['index','verify','get-sms-captcha'];
        return \yii\helpers\ArrayHelper::merge($parentBehaviors, $behaviors);
    }

    /**
     * 获取验证码
     */
    public function actionIndex()
    {
        $captcha = new CaptchaBuilder([
            'maxLength' => 4, //最大显示个数
            'minLength' => 4,//最少显示个数
            'padding' => 5,//间距
            'height'=>36,//高度
            'width' => 80,  //宽度
//            'foreColor'=>0xffffff,     //字体颜色
            'offset'=>4,        //设置字符偏移量,
            //背景字符个数
            'disturbCharCount'=>0,
            //干扰线数量
            'curveCount'=>0
        ]);

        $base64 = $captcha->base64();

        $data = [
//            'code' =>  $captcha->getVerifyCode(),
            'sid' =>  $captcha->getSessionKey(),
            'base64Img' => $base64,
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
    }

    /**
     * 校验验证码
     */
    public function actionVerify()
    {
        $arrAllParams   = $this->getAllParas();

        $captchCode = ControllerParameterValidator::validateString($arrAllParams, 'captchaCode', 4,6);
        $captchSid = ControllerParameterValidator::validateString($arrAllParams, 'captchaSid', 10,64);

//        $captchaValidate  = new \yii\captcha\CaptchaAction('captcha',Yii::$app->controller);
        $captcha = new CaptchaBuilder(['sessionKey'=>$captchSid,'testLimit'=>20]);
        $ret = $captcha->validate($captchCode);
        if($ret){
            return ResponseHelper::formatOutput(Macro::SUCCESS);
        }else{
            return ResponseHelper::formatOutput(Macro::FAILED_MESSAGE);
        }
    }
}
