<?php
namespace app\common\models\form;

use SebastianBergmann\CodeCoverage\Util;
use Yii;
use app\common\models\model\User;

/**
 * Login form
 */
class LoginForm extends BaseForm
{
    public $username;
    public $password;

    private $_user;

    const GET_ACCESS_TOKEN = 'generate_access_token';

    public function init ()
    {
        parent::init();
        $this->on(self::GET_ACCESS_TOKEN, [$this, 'onGenerateAccessToken']);
    }


    /**
     * @inheritdoc
     * 对客户端表单数据进行验证的rule
     */
    public function rules()
    {
        return [
            [['username'], 'required','message' => '用户名错误','skipOnError' => false],
            [['password'], 'required','message' => '密码错误','skipOnError' => false],
            ['password', 'validatePassword'],
        ];
    }

    /**
     * 自定义的密码认证方法
     */
    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $this->_user = $this->getUser();

            if (!$this->_user) {
                $this->addError($attribute, '用户名或密码错误(code:101)');
                return false;
            }
            if ($this->_user->status != User::STATUS_ACTIVE) {
                $this->addError($attribute, '账户未激活(code:102)');
                return false;
            }
            if (!$this->_user->validatePassword($this->password)) {
                $this->addError($attribute, '用户名或密码错误(code:103)');
                return false;
            }
        }
    }
    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'username' => '用户名',
            'password' => '密码',
        ];
    }
    /**
     * Logs in a user using the provided username and password.
     *
     * @return boolean whether the user is logged in successfully
     */
    public function login()
    {
        if ($this->validate()) {
            if($this->_user->status != User::STATUS_ACTIVE){
                return null;
            }else{
                $this->trigger(self::GET_ACCESS_TOKEN);
                return $this->_user;
            }
        } else {
            return null;
        }
    }

    /**
     * 根据用户名获取用户的认证信息
     *
     * @return User|null
     */
    protected function getUser()
    {
        if ($this->_user === null) {
            $this->_user = User::findOne(['username'=>$this->username]);
        }

        return $this->_user;
    }

    /**
     * 登录校验成功后，为用户生成新的token
     * 如果token失效，则重新生成token
     */
    public function onGenerateAccessToken ()
    {
//        if (!User::accessTokenIsValid($this->_user->access_token)) {
//            $this->_user->generateAccessToken();
//        }
        //登录后强制重置
        $this->_user->generateAccessToken();

        $this->_user->last_login_time = time();
        $this->_user->last_login_ip = Yii::$app->request->userIP;
        $this->_user->save(false);
    }
}