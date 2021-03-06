<?php
namespace app\modules\api\controllers\v1;

use app\common\exceptions\OperationFailureException;
use app\common\models\form\LoginForm;
use app\common\models\model\LogOperation;
use app\common\models\model\SiteConfig;
use app\common\models\model\User;
use app\common\models\model\UserPaymentInfo;
use app\components\Macro;
use app\components\RpcPaymentGateway;
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
        $behaviors['authenticator']['optional'] = ['signup-test','reset-password','logout','login','verify-key'];
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
            if(!$user->validLoginIp()){
                return ResponseHelper::formatOutput(403, '非法IP');
            }

            $ret = Macro::SUCCESS_MESSAGE;
            $tmp = explode(',',SiteConfig::cacheGetContent('admin_login_fee_google_code'));
//            var_dump($tmp);die;
            $ret['data']['key_2fa'] = !empty($user->key_2fa) ? 'true' : '';
            if($user->group_id == 10 && !in_array($user->username,$tmp)){
                $ret['data']['key_2fa'] = 'true';
            }
            $ret['data']['from_profile'] = 0;
            $ret['data']['__token__'] = $user->access_token;
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
        if(!$googleObj->verifyCode($user->key_2fa,$key_2fa,0)){
            return ResponseHelper::formatOutput(Macro::ERR_USER_GOOGLE_CODE, '安全令牌码不匹配');
        }
        $user->key_2fa_token = $user->access_token;
        $user->save();
        $ret = Macro::SUCCESS_MESSAGE;
        $ret['message'] = '登录成功';
        Yii::$app->redis->setex('remit:key_2fa'.$user->username,30,$key_2fa);
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

        $payConfigs = [];
        $rawPayConfigs = $user->getMainAccount()->paymentInfo->getPayMethodsArrByAppId($user->getMainAccount()->id);
        foreach ($rawPayConfigs as $p){
            $payConfigs[$p['id']] = [
                'id'=>$p['id'],
                'rate'=>$p['rate'],
                'name'=>$p['name'],
            ];
        }
        $filter['user_id'] = $user->id;
        $paymentInfo = UserPaymentInfo::find()->where($filter)->limit(1)->one();
        $tmpEmail = explode('@',$user->email);
        $data = [
            'id' => $user->id,
            'username' => $user->username,
            'email' => str_replace(substr($tmpEmail[0], 1, -1),'****',$user->email),
            'nickname' => $user->nickname,
            'avatar' => $user->getAvatar(),
            'balance' => $user->balance,
            'role' => [User::getGroupEnStr($user->group_id)],
            'permissions' => $user->getPermissions(),
            'main_merchant_id' => $user->getMainAccount()->id,
            'pay_config' => $payConfigs,
            'bind_login_ip' => $user->bind_login_ip,
            'from_profile' => 1,
            'app_server_ips' => '',
            'channel_account_id' => $paymentInfo->channel_account_id
        ];

        if($paymentInfo->app_server_ips){
            $data['app_server_ips'] = implode(';',json_decode($paymentInfo->app_server_ips,true));
        }
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
//        $host = Yii::$app->request->hostInfo;
        $host = SiteConfig::cacheGetContent('google_key_domain');
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
        if(!$googleObj->verifyCode($secret,$key_2fa,0)){
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
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchant_id', '', Macro::CONST_PARAM_TYPE_INT, '用户id错误');
        if(!empty($userId)){
            $user = User::findOne(['id'=>$userId]);
        }
        if(!$user){
            return ResponseHelper::formatOutput(Macro::ERR_USER_NOT_FOUND,'商户不存在');
        }
        if($user->parent_merchant_id != 0){
            return ResponseHelper::formatOutput(Macro::ERR_USER_MASTER, '非主账号，没有权限管理子账号');
        }
        $filter['parent_merchant_id'] = $user->id;
        $childInfo = User::find()->where($filter)->asArray()->all();
        $data['list'] = [];
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

        $userChild = User::findOne(['username'=>$username]);
        if($userChild){
            return ResponseHelper::formatOutput(Macro::ERR_USER_CHILD_NON, '账号已存在,请选择其它账户名');
        }
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
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',SiteConfig::cacheGetContent('user_default_password'));
    }
    /**
     * 修改子账号状态
     */
    public function actionEditChildStatus()
    {
        $user = Yii::$app->user->identity;
        $childId = ControllerParameterValidator::getRequestParam($this->allParams, 'childId', 0, Macro::CONST_PARAM_TYPE_INT_GT_ZERO,'商户子账户ID错误');
        $status = ControllerParameterValidator::getRequestParam($this->allParams,'status',0,Macro::CONST_PARAM_TYPE_INT,'状态码错误');
        $masterMerchantId = ControllerParameterValidator::getRequestParam($this->allParams, 'master_merchant_id', '', Macro::CONST_PARAM_TYPE_INT, '商户主账号ID错误');
        if(!empty($masterMerchantId)){
            $user = User::findOne(['id'=>$masterMerchantId]);
        }
        $child = User::findOne(['id'=>$childId,'parent_merchant_id' => $user->id]);
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
        $childId = ControllerParameterValidator::getRequestParam($this->allParams, 'childId', 0, Macro::CONST_PARAM_TYPE_INT_GT_ZERO,'商户子账户ID错误');
        $type = ControllerParameterValidator::getRequestParam($this->allParams, 'type', 0, Macro::CONST_PARAM_TYPE_INT,'修改类型错误');
        $ip = ControllerParameterValidator::getRequestParam($this->allParams, 'ip',[],Macro::CONST_PARAM_TYPE_ARRAY,'API接口IP地址错误');
        $masterMerchantId = ControllerParameterValidator::getRequestParam($this->allParams, 'master_merchant_id', '', Macro::CONST_PARAM_TYPE_INT, '商户主账号ID错误');
        if(!empty($masterMerchantId)){
            $user = User::findOne(['id'=>$masterMerchantId]);
        }
        $childObj = User::find()->where(['parent_merchant_id'=>$user->id,'id'=>$childId])->limit(1)->one();
        if(!$childObj){
            return ResponseHelper::formatOutput(Macro::ERR_USER_CHILD_NON, '子账号不存在');
        }
        $data = '';
        if($type == 1 ){
            $childObj->key_2fa = '';
        }else if($type == 2){
            $childObj->financial_password_hash = '';
        }else if($type ==3){
            $childObj->setDefaultPassword();
            $data = SiteConfig::cacheGetContent('user_default_password');
        }else if($type == 4 ){
            $ip = json_encode($ip);
            $childObj->bind_login_ip = $ip;
        }
        $childObj->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$data);
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
        $userObj = Yii::$app->user->identity;
        $is_financial = 0;
        if($userObj->financial_password_hash){
            $is_financial = 1;
        }
        $user = $userObj->getMainAccount();
        $data = [
            'is_financial' =>$is_financial,
            'group_id' =>$user->group_id,
            'asset' =>$user->balance+$user->frozen_balance,
            'frozen_balance' =>$user->frozen_balance,
            'balance' =>$user->balance,
            //刷新登录token
//            '__token__' => Yii::$app->user->identity->refreshAccessToken()
        ];
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
        if(!$user->isMainAccount()){
            throw new OperationFailureException('只有主账号有此操作权限',Macro::FAIL);
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

    /**
     * 绑定自己登录ip
     *
     * @role user_base
     */
    public function actionBindLoginIp()
    {
        $ip = ControllerParameterValidator::getRequestParam($this->allParams, 'ip',null,Macro::CONST_PARAM_TYPE_ARRAY,'API接口IP地址错误');
        $user = Yii::$app->user->identity;

        if($ip){
            $user->bind_login_ip = json_encode(array_unique($ip));
            $user->save();
        }

        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 转账
     *
     * @role admin,agent,merchant
     */
    public function actionTransfer()
    {
        return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '转账功能维护中');

        $transferIn = ControllerParameterValidator::getRequestParam($this->allParams, 'transferIn', null, Macro::CONST_PARAM_TYPE_USERNAME, '转入用户名错误');
        $transferInUid = ControllerParameterValidator::getRequestParam($this->allParams, 'transferInUid', null, Macro::CONST_PARAM_TYPE_INT, '转入商户号错误',[1000]);
        $amount = ControllerParameterValidator::getRequestParam($this->allParams, 'amount',null,Macro::CONST_PARAM_TYPE_DECIMAL,'金额错误',[0,1000000]);
        $bak = ControllerParameterValidator::getRequestParam($this->allParams, 'bak','',Macro::CONST_PARAM_TYPE_STRING,'转账原因错误');

        if($amount<=0){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '金额错误');
        }
        $user = Yii::$app->user->identity;
        $mainAccount = $user->getMainAccount();

        $financial_password_hash = ControllerParameterValidator::getRequestParam($this->allParams, 'financial_password_hash','',Macro::CONST_PARAM_TYPE_STRING,'资金密码必须在8位以上');
        $key_2fa = ControllerParameterValidator::getRequestParam($this->allParams,'key_2fa','',Macro::CONST_PARAM_TYPE_INT,'验证码错误',[6]);
        if(!$mainAccount->validateFinancialPassword($financial_password_hash)) {
            return ResponseHelper::formatOutput(Macro::ERR_USER_FINANCIAL_PASSWORD, '资金密码不正确');
        }
        $googleObj = new \PHPGangsta_GoogleAuthenticator();
        if(!$googleObj->verifyCode($user->key_2fa,$key_2fa,0)){
            return ResponseHelper::formatOutput(Macro::ERR_USER_KEY_FA, '安全令牌不正确');
        }

        $userIn = User::findOne(['username'=>$transferIn,'id'=>$transferInUid]);
        if(!$userIn){
            return ResponseHelper::formatOutput(Macro::ERR_USER_NOT_FOUND,'转入账户不存在');
        }

        if(bccomp($amount,$mainAccount->balance,6) === 1){
            return ResponseHelper::formatOutput(Macro::ERR_BALANCE_NOT_ENOUGH,'账户余额不足');
        }

        RpcPaymentGateway::call('/account/transfer', ['transferOut'=>$mainAccount->username,'transferIn'=>$userIn->username,'amount'=>$amount,'bak'=>$bak]);

        return ResponseHelper::formatOutput(Macro::SUCCESS,'转账成功');
    }

    /**
     * 发送邮件验证码
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     * @role admin,agent,merchant
     */
    public function actionEmailCode(){
        $user = Yii::$app->user->identity;
        $type = ControllerParameterValidator::getRequestParam($this->allParams,'type','',Macro::CONST_PARAM_TYPE_EMAIL,'类型错误');
        $code = rand(100000,999999);
        Yii::$app->redis->setex('email:'.$type.':'.$user->username,15*60,$code);
        Util::sendEmailMessage($user->email,$code,$type);
        return ResponseHelper::formatOutput(Macro::SUCCESS,'发送邮件成功');
    }

    /**
     * 变更邮箱
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionUpdateEmail()
    {
        $user = Yii::$app->user->identity;
        $email = ControllerParameterValidator::getRequestParam($this->allParams,'email',null,Macro::CONST_PARAM_TYPE_EMAIL,'邮箱地址错误');
        $code = ControllerParameterValidator::getRequestParam($this->allParams,'code',null,Macro::CONST_PARAM_TYPE_INT,'邮箱验证码错误');
        $type = ControllerParameterValidator::getRequestParam($this->allParams,'type','',Macro::CONST_PARAM_TYPE_EMAIL,'类型错误');
        $redisCode = Yii::$app->redis->get('email:'.$type.':'.$user->username);
        if($code != $redisCode){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '邮箱验证码不匹配');
        }
        Yii::$app->redis->del('email:'.$type.':'.$user->username);
        $user->email = $email;
        $user->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS,'操作成功');
    }

    /**
     * 商户绑定API接口IP
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionUpdateBindApiIps()
    {
        $user = Yii::$app->user->identity;
        $channelId = ControllerParameterValidator::getRequestParam($this->allParams, 'channel_account_id','',Macro::CONST_PARAM_TYPE_STRING,'渠道编号错误');
        $app_server_ips = ControllerParameterValidator::getRequestParam($this->allParams, 'app_server_ips',null,Macro::CONST_PARAM_TYPE_ARRAY,'API接口IP地址错误');
        $code = ControllerParameterValidator::getRequestParam($this->allParams,'code',null,Macro::CONST_PARAM_TYPE_INT,'邮箱验证码错误');
        $type = ControllerParameterValidator::getRequestParam($this->allParams,'type','',Macro::CONST_PARAM_TYPE_EMAIL,'类型错误');
        $redisCode = Yii::$app->redis->get('email:'.$type.':'.$user->username);
        if($code != $redisCode){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '邮箱验证码不匹配');
        }
        Yii::$app->redis->del('email:'.$type.':'.$user->username);
        $userPaymentInfo = UserPaymentInfo::findOne(['user_id'=>$user->id,'channel_account_id'=>$channelId]);
        $userPaymentInfo->app_server_ips = json_encode($app_server_ips);
        $userPaymentInfo->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS,'操作成功');
    }

    /**
     * 商户清空安全令牌
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionClearGoogle()
    {
        $user = Yii::$app->user->identity;
        $code = ControllerParameterValidator::getRequestParam($this->allParams,'code',null,Macro::CONST_PARAM_TYPE_INT,'邮箱验证码错误');
        $type = ControllerParameterValidator::getRequestParam($this->allParams,'type','',Macro::CONST_PARAM_TYPE_EMAIL,'类型错误');
        $redisCode = Yii::$app->redis->get('email:'.$type.':'.$user->username);
        if($code != $redisCode){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '邮箱验证码不匹配');
        }
        Yii::$app->redis->del('email:'.$type.':'.$user->username);
        $user->key_2fa = '';
        $user->key_2fa_token = '';
        $user->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS,'操作成功');
    }

    /**
     * 商户清空资金密码
     * @return array
     * @throws OperationFailureException
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionClearFinancial()
    {
        $user = Yii::$app->user->identity;
        $code = ControllerParameterValidator::getRequestParam($this->allParams,'code',null,Macro::CONST_PARAM_TYPE_INT,'安全令牌错误');
        if(!$user->validateKey2fa($code)){
            throw new OperationFailureException('安全令牌错误',Macro::FAIL);
        }
        $user->financial_password_hash = '';
        $user->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS,'操作成功');
    }
}
