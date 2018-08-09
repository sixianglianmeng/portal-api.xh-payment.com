<?php
namespace app\modules\api\controllers\v1;

use app\common\exceptions\OperationFailureException;
use app\common\models\form\LoginForm;
use app\common\models\model\LogOperation;
use app\common\models\model\User;
use app\components\Macro;
use app\components\Util;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use power\yii2\captcha\CaptchaBuilder;
use Yii;

class UserController extends BaseController
{
    public function behaviors()
    {
        $parentBehaviors = parent::behaviors();
        //验证码不需要token验证
        $behaviors['authenticator']['optional'] = ['signup-test','reset-password','logout','login','user-check','verify-key'];
        $behaviors = \yii\helpers\ArrayHelper::merge($parentBehaviors, $behaviors);

        return $behaviors;
    }

    /**
     * 前置action
     *
     * @author bootmall@gmail.com
     */
    public function beforeAction($action){
        return parent::beforeAction($action);
    }

    /**
     * 登录
     */
    public function actionLogin ()
    {
        $captchCode = ControllerParameterValidator::getRequestParam($this->allParams, 'captchaCode', null,Macro::CONST_PARAM_TYPE_STRING,'验证码参数错误',[4,6]);
        $captchSid = ControllerParameterValidator::getRequestParam($this->allParams, 'captchaSid', null,Macro::CONST_PARAM_TYPE_STRING,'验证码参数错误',[10,64]);

        //        $captchaValidate  = new \yii\captcha\CaptchaAction('captcha',Yii::$app->controller);
        $captcha = new CaptchaBuilder(['sessionKey'=>$captchSid,'testLimit'=>2]);
        $ret = $captcha->validate($captchCode);
        if(!$ret){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'验证码校验失败');
        }

        $form = new LoginForm;
        $form->setAttributes(Yii::$app->request->post());
        if ($user = $form->login()) {
            $ret = Macro::SUCCESS_MESSAGE;
            $ret['data']['key_2fa'] = $user->key_2fa;
            $ret['data']['access_token'] = $user->access_token;
            $ret['data']['id'] = $user->id;
            $ret['data']['username'] = $user->username;
            $ret['data']['nickname'] = $user->nickname;
            $ret['data']['role'] = [User::getGroupEnStr($user->group_id)];
            $ret['data']['avatar'] = $user->getAvatar();
            $ret['message'] = '登录成功';
            $ret['data']['permissions'] = $user->getPermissions();
            Yii::$app->user->switchIdentity($user);

        } else {
            $ret = Macro::FAILED_MESSAGE;
            $ret['code'] = Macro::ERROR_LOGIN_INFO;
            $ret['message'] = $form->getErrorsString();
        }

        return ResponseHelper::formatOutput($ret['code'], $ret['message'], $ret['data']);
    }
    /**
     * 验证安全令牌
     */
    public function actionVerifyKey()
    {
        $user = Yii::$app->user->identity;
        $key_2fa = ControllerParameterValidator::getRequestParam($this->allParams, 'key_2fa',0,Macro::CONST_PARAM_TYPE_INT,'安全码错误',[6]);

        $googleObj = new \PHPGangsta_GoogleAuthenticator();
        if(!$googleObj->verifyCode($user->key_2fa,$key_2fa,1)){
            return ResponseHelper::formatOutput(Macro::ERR_USER_GOOGLE_CODE, '验证码不匹配');
        }
        $user->key_2fa_token = $user->access_token;
        $user->save();
        $ret = Macro::SUCCESS_MESSAGE;
        $ret['message'] = '登录成功';
        return ResponseHelper::formatOutput($ret['code'], $ret['message'], $ret['data']);
    }
    /**
     * 重置密码
     */
    public function actionResetPassword()
    {
        //系统暂不支持重置密码
        return;

        $arrAllParams   = $this->getAllParas();
        $username = ControllerParameterValidator::getRequestParam($arrAllParams, 'username','',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'用户名错误');
//        $smsCode = ControllerParameterValidator::getRequestParam($arrAllParams, 'smsCode','',Macro::CONST_PARAM_TYPE_ALNUM,'短信验证码错误：1',[4,6]);
        $password = ControllerParameterValidator::getRequestParam($arrAllParams,'password','',Macro::CONST_PARAM_TYPE_STRING,'密码最小为6位',[6]);

        $smsCheckRet = $ret = LogicSms::checkVerifyCode($username, 'findPwd', $smsCode);
        if(!$smsCheckRet){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '短信验证码错误');
        }

        $user = User::findByUsername($username);
        if(empty($user)){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '用户名错误或账户已被禁用');
        }

        $user->setPassword($password);
        $user->save(false);

        $logOperation = new LogOperation();
        $logOperation->type = LogOperation::TYPE_LOGIN;
        $logOperation->user_id = $user->id;
        $logOperation->username = $user->username;
        $logOperation->save();

        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 注销
     */
    public function actionLogout ()
    {
        if(!Yii::$app->user->isGuest){
            Yii::$app->user->identity->logOut();
        }

        Yii::$app->user->logout();
        $ret = Macro::SUCCESS_MESSAGE;
        $ret['message'] = '注销成功';

        return $ret;
    }

    /**
     * 获取自己账户信息
     */
    public function actionProfile ()
    {
        $user = Yii::$app->user->identity;
        $data = [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'nickname' => $user->nickname,
            'avatar' => $user->getAvatar(),
            'balance' => $user->balance,
            'role' => [User::getGroupEnStr($user->group_id)],
            'permissions' => $user->getPermissions(),
            'main_merchant_id' => $user->getMainAccount()->id,
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 修改密码
     */
    public function actionEditPass()
    {
        $oldPass = ControllerParameterValidator::getRequestParam($this->allParams,'oldPass','',Macro::CONST_PARAM_TYPE_PASSWORD,'旧密码错误',[6,16]);
        $newPass = ControllerParameterValidator::getRequestParam($this->allParams,'newPass','',Macro::CONST_PARAM_TYPE_PASSWORD,'新密码错误',[6,16]);
        $confirmPass = ControllerParameterValidator::getRequestParam($this->allParams,'confirmPass','',Macro::CONST_PARAM_TYPE_PASSWORD,'确认密码错误',[6,16]);
        $user = Yii::$app->user->identity;

        if(!$user->validatePassword($oldPass)){
            return ResponseHelper::formatOutput(Macro::ERR_USER_PASSWORD, '旧密码不正确');
        }
        if($newPass != $confirmPass){
            return ResponseHelper::formatOutput(Macro::ERR_USER_PASSWORD_CONFIRM, '新密码与确认密码不一致');
        }
        $user->setPassword($newPass);
        $user->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 生成谷歌二维码
     */
    public function actionGetGoogleCode()
    {
        $user = Yii::$app->user->identity;

        if($user->needPayAccountOpenFee()){
            throw new OperationFailureException('请先缴纳开户费用',Macro::FAIL);
        }

        if($user->key_2fa){
            return ResponseHelper::formatOutput(Macro::ERR_USER_GOOGLE_CODE, '安全令牌已设置');
        }
        $googleObj = new \PHPGangsta_GoogleAuthenticator();
        $secret = $googleObj->createSecret();
        Yii::$app->redis->setex('google_secret'.$user->id,5*60,$secret);
        //$code = $googleObj->getCode($secret);
        $name = $user->username;
        //$url = $googleObj->getQRCodeGoogleUrl($name, $secret);
        $host = Yii::$app->request->hostInfo;
        $expectedChl = 'otpauth://totp/'.$name.'@'.$host.'?secret='.$secret;

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功',$expectedChl);
    }
    /**
     * 设置谷歌验证码
     */
    public function actionSetGoogleCode()
    {
        $user = Yii::$app->user->identity;
        if($user->needPayAccountOpenFee()){
            throw new OperationFailureException('请先缴纳开户费用',Macro::FAIL);
        }

        $key_2fa = ControllerParameterValidator::getRequestParam($this->allParams,'key_2fa','',Macro::CONST_PARAM_TYPE_INT,'验证码错误',[6]);
        $googleObj = new \PHPGangsta_GoogleAuthenticator();
        $secret = Yii::$app->redis->get('google_secret'.$user->id);
        if(!$googleObj->verifyCode($secret,$key_2fa,2)){
            return ResponseHelper::formatOutput(Macro::ERR_USER_GOOGLE_CODE, '验证码不匹配');
        }
        Yii::$app->redis->del('google_secret'.$user->id);
        $user = Yii::$app->user->identity;
        $user->key_2fa = $secret;
        $user->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 获取商户key
     */
    public function actionGetAuthKey()
    {
        $user = Yii::$app->user->identity;
        if(!$user->isMainAccount()){
            throw new OperationFailureException('只有主账号有此操作权限',Macro::FAIL);
        }

        if($user->needPayAccountOpenFee()){
            throw new OperationFailureException('请先缴纳开户费用',Macro::FAIL);
        }

        $this->checkSecurityInfo();

        $payInfo = Yii::$app->user->identity->paymentInfo;
        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功',$payInfo->app_key_md5);
    }

    /**
     * 修改商户key
     */
    public function actionEditAuthKey()
    {
        $user = Yii::$app->user->identity;
        if(!$user->isMainAccount()){
            throw new OperationFailureException('只有主账号有此操作权限',Macro::FAIL);
        }

        if($user->needPayAccountOpenFee()){
            throw new OperationFailureException('请先缴纳开户费用',Macro::FAIL);
        }

        $this->checkSecurityInfo();

        $authKey = ControllerParameterValidator::getRequestParam($this->allParams,'authKey','',Macro::CONST_PARAM_TYPE_STRING,'商户Key格式错误',[0,50]);
        $payInfo = Yii::$app->user->identity->paymentInfo;
        $payInfo->app_key_md5 = $authKey;
        $payInfo->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 子账号管理
     */
    public function actionChildList()
    {
        $user = Yii::$app->user->identity;
        if($user->parent_merchant_id != 0){
            return ResponseHelper::formatOutput(Macro::ERR_USER_MASTER, '非主账号，没有权限管理子账号');
        }
        $filter['parent_merchant_id'] = $user->id;
        $childInfo = User::find()->where($filter)->asArray()->all();
        foreach ($childInfo as $key => $val){
            $val['last_login_time'] = date("Y-m-d H:i:s",$val['last_login_time']);
            $val['created_at'] = date("Y-m-d H:i:s",$val['created_at']);
            $val['status_name'] = User::ARR_STATUS[$val['status']];
            $val['is_key_2fa'] = $val['key_2fa'] ? '已设置' : '未设置';
            $val['is_financial'] = $val['financial_password_hash'] ? '已设置' : ' 未设置';
            $data['list'][$key] = $val;
        }
        $data['statusOptions'] = User::ARR_STATUS;
        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }
    /**
     * 添加子账户
     */
    public function actionAddChild()
    {
        $username = ControllerParameterValidator::getRequestParam($this->allParams,'username',null,Macro::CONST_PARAM_TYPE_USERNAME,'登录账户错误',[6,16]);
        $nickname = ControllerParameterValidator::getRequestParam($this->allParams,'nickname',null,Macro::CONST_PARAM_TYPE_STRING,'昵称错误');
        $email = ControllerParameterValidator::getRequestParam($this->allParams,'email',null,Macro::CONST_PARAM_TYPE_EMAIL,'邮箱错误');
        $status = ControllerParameterValidator::getRequestParam($this->allParams,'status',0,Macro::CONST_PARAM_TYPE_INT,'状态码错误');

        $user = Yii::$app->user->identity;
        $userChild = new User();
        if($username){
            $userChild->username = $username;
        }
        if($nickname){
            $userChild->nickname = $nickname;
        }
        $userChild->setDefaultPassword();
        if($email){
            $userChild->email = $email;
        }
        if($status){
            $userChild->status = $status;
        }
        $userChild->group_id = $user->group_id;
        $userChild->auth_key = $user->auth_key;
        $userChild->parent_agent_id = $user->parent_agent_id;
        $userChild->all_parent_agent_id = $user->all_parent_agent_id;
        $userChild->parent_merchant_id = $user->id;
        $userChild->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }
    /**
     * 修改子账号状态
     */
    public function actionEditChildStatus()
    {
        $user = Yii::$app->user->identity;
        $childId = ControllerParameterValidator::getRequestParam($this->allParams, 'childId', 0,
            Macro::CONST_PARAM_TYPE_INT_GT_ZERO,'商户子账户ID错误');
        $status = ControllerParameterValidator::getRequestParam($this->allParams,'status',0,Macro::CONST_PARAM_TYPE_INT,'状态码错误');
//        $child = User::findOne(['id'=>$childId,'parent_merchant_id' => $user->id]);
        $child = User::find()->where(['id'=>$childId,'parent_merchant_id' => $user->id])->limit(1)->one();
        if(!$child){
            return ResponseHelper::formatOutput(Macro::ERR_USER_CHILD_NON, '子账号不存在');
        }
        $child->status = $status;
        $child->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }
    /**
     * 子账户密码清除
     * 清空子账号的资金密码者安全令牌或者重置子账号登录密码
     */
    public function actionClearChildPassKey()
    {
        $user = Yii::$app->user->identity;
        $childId = ControllerParameterValidator::getRequestParam($this->allParams, 'childId', 0,
            Macro::CONST_PARAM_TYPE_INT_GT_ZERO,'商户子账户ID错误');
        $type = ControllerParameterValidator::getRequestParam($this->allParams, 'type', 0,
            Macro::CONST_PARAM_TYPE_INT,'修改类型错误');

        $childObj = User::find()->where(['parent_merchant_id'=>$user->id,'id'=>$childId])->limit(1)->one();
        if(!$childObj){
            return ResponseHelper::formatOutput(Macro::ERR_USER_CHILD_NON, '子账号不存在');
        }
        if($type == 1 ){
            $childObj->key_2fa = '';
        }else if($type == 2){
            $childObj->financial_password_hash = '';
        }else if($type ==3){
            $childObj->setDefaultPassword();
        }
        $childObj->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 账户是否能提款
     *
     * @roles agent,merchant
     */
    public function actionCheckCanRemit()
    {
        $userObj = Yii::$app->user->identity;
        $user = $userObj->getMainAccount();
        if($user->balance<=0){
            return ResponseHelper::formatOutput(Macro::ERR_BALANCE_NOT_ENOUGH,'余额不足，不能提款');
        }
        if($user->paymentInfo->allow_manual_remit<=0){
            return ResponseHelper::formatOutput(Macro::ERR_ALLOW_MANUAL_REMIT,'不支持手工提款');
        }
        $user->paymentInfo->getRemitChannel();
        if(!$user->paymentInfo->remit_channel_account_id){
            return ResponseHelper::formatOutput(Macro::ERR_REMIT_CHANNEL_NOT_ENOUGH,'未指定出款渠道，请联系客服设置');
        }
        if(empty($user->key_2fa)||empty($user->financial_password_hash)){
            return ResponseHelper::formatOutput(Macro::ERR_USER_2FA_EMPTY, '请先设置资金密码和手机令牌');
        }

        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 检查资金密码是否设置
     *
     * @roles user_base,admin
     */
    public function actionUserCheck()
    {
        $user = Yii::$app->user->identity;
        $data = [
            'is_financial' =>0,
            'group_id' =>$user->group_id,
            'asset' =>$user->balance+$user->frozen_balance,
            'frozen_balance' =>$user->frozen_balance,
            'balance' =>$user->balance,
        ];
        if($user->financial_password_hash){
            $data['is_financial'] = 1;
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 设置/修改资金密码
     */
    public  function actionEditFinancialPass()
    {
        $oldPass = ControllerParameterValidator::getRequestParam($this->allParams,'oldPass','',Macro::CONST_PARAM_TYPE_PASSWORD,'旧密码错误',[6,16]);
        $newPass = ControllerParameterValidator::getRequestParam($this->allParams,'newPass','',Macro::CONST_PARAM_TYPE_PASSWORD,'新密码错误',[6,16]);
        $confirmPass = ControllerParameterValidator::getRequestParam($this->allParams,'confirmPass','',Macro::CONST_PARAM_TYPE_PASSWORD,'确认密码错误',[6,16]);
        $user = Yii::$app->user->identity;

        if($user->needPayAccountOpenFee()){
            throw new OperationFailureException('请先缴纳开户费用',Macro::FAIL);
        }

        if($oldPass && !$user->validateFinancialPassword($oldPass)){
            return ResponseHelper::formatOutput(Macro::ERR_USER_PASSWORD, '旧密码不正确');
        }
        if($confirmPass && $newPass != $confirmPass){
            return ResponseHelper::formatOutput(Macro::ERR_USER_PASSWORD_CONFIRM, '新密码与确认密码不一致');
        }
        $user->setFinancialPassword($newPass);
        $user->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 检测是否是主账户及校验安全属性(资金密码或google令牌)
     */
    protected function checkSecurityInfo(){
        $user = Yii::$app->user->identity;

        //检测有效期，例如5分钟内检测过可跳过
        $checkValidDuration = 300;
        $cacheKey = 'checkMainAccountAndSecurityInfo_ret_'.$this->id;
        $ts = Yii::$app->cache->get($cacheKey);
        if($ts && $ts>(time()-$checkValidDuration)){
            return true;
        }

        $finacialPwd = ControllerParameterValidator::getRequestParam($this->allParams,'finacialPwd','',Macro::CONST_PARAM_TYPE_PASSWORD,'资金密码格式错误',[6,16]);
        $key2fa = ControllerParameterValidator::getRequestParam($this->allParams,'key2fa','',Macro::CONST_PARAM_TYPE_INT,'安全令牌验证码格式错误',[6]);
        if(empty($finacialPwd) && empty($key2fa)){
            throw new OperationFailureException('资金密码或安全令牌格式错误',Macro::FAIL);
        }

        $validate = false;
        if($finacialPwd){
            if($user->validateFinancialPassword($finacialPwd)){
                $validate=true;
            }else{
                throw new OperationFailureException('资金密码错误',Macro::FAIL);
            }

        }
        if($key2fa){
            if($user->validateKey2fa($key2fa)){
                $validate=true;
            }else{
                throw new OperationFailureException('安全令牌错误',Macro::FAIL);
            }

        }
        if(!$validate){
            throw new OperationFailureException('资金密码或安全令牌错误',Macro::FAIL);
        }

        Yii::$app->cache->set($cacheKey,time(),$checkValidDuration);
    }
}
