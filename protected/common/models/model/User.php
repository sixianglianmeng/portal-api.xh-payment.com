<?php
namespace app\common\models\model;

use app\common\exceptions\OperationFailureException;
use app\components\Macro;
use app\components\Util;
use Yii;
use yii\base\Request;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\filters\RateLimitInterface;
use yii\web\IdentityInterface;

class User extends ActiveRecord implements IdentityInterface, RateLimitInterface
{
    const STATUS_INACTIVE=0;
    const STATUS_ACTIVE=10;
    const STATUS_BANED=20;
    const ARR_STATUS = [
        self::STATUS_INACTIVE => '未激活',
        self::STATUS_ACTIVE => '正常',
        self::STATUS_BANED => '已禁用',
    ];

    const GROUP_ADMIN = 10;
    const GROUP_AGENT = 20;
    const GROUP_MERCHANT = 30;

    const ARR_GROUP = [
        self::GROUP_ADMIN => '管理员',
        self::GROUP_MERCHANT => '商户',
        self::GROUP_AGENT => '代理',
    ];

    const ARR_GROUP_EN = [
        self::GROUP_ADMIN => 'admin',
        self::GROUP_MERCHANT => 'merchant',
        self::GROUP_AGENT => 'agent',
    ];

    const DEFAULT_RECHARGE_RATE = 0.6;
    const DEFAULT_REMIT_FEE = 0.6;

    const ACCOUNT_OPEN_FEE_STATUS_NO = 0;
    const ACCOUNT_OPEN_FEE_STATUS_YES = 1;
    const ARR_ACCOUNT_OPEN_FEE_STATUS = [
        self::ACCOUNT_OPEN_FEE_STATUS_NO => '未缴费',
        self::ACCOUNT_OPEN_FEE_STATUS_YES => '已缴费',
    ];

    public static function tableName()
    {
        return '{{%users}}';
    }

    public function behaviors() {
        return [TimestampBehavior::className(),];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['status','default','value'=>self::STATUS_INACTIVE],
            ['status','in','range'=>[self::STATUS_ACTIVE,self::STATUS_INACTIVE,self::STATUS_BANED]],
        ];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert){
            //按规则生成uid,(2位分组id+1位是否主账号+当前规则下数据库最大值区3位之后)+10-500随机数,且总长度中6位以上
            $uidPrefix = ($this->group_id<10?99:$this->group_id).''.intval($this->isMainAccount());
            $parentMerchantIdStr = $this->isMainAccount()?"AND parent_merchant_id=0":"AND parent_merchant_id>0";
            $maxPrefixId = Yii::$app->db->createCommand("SELECT id from ".User::tableName()." WHERE group_id={$this->group_id} $parentMerchantIdStr ORDER BY id DESC LIMIT 1")
                ->queryScalar();
            if($maxPrefixId>1000){
                $maxPrefixId = substr($maxPrefixId,3);
            }
            if($maxPrefixId<1000)  $maxPrefixId = mt_rand(1000,1500);
            $this->id = intval($uidPrefix.$maxPrefixId)+mt_rand(10,500);
        }

        return true;
    }

    public function getPaymentInfo()
    {
        return $this->hasOne(UserPaymentInfo::className(), ['user_id'=>'id']);
    }

    public static function findActive($id){
        return static::findOne(['id'=>$id,'status'=>self::STATUS_ACTIVE]);
    }

    public static function getUserByMerchantId($id){
        $user = static::findOne(['app_id'=>$id,'status'=>self::STATUS_ACTIVE]);

        return $user;
    }

    public static function findByUsername($username){
        return static::findOne(['username'=>$username,'status'=>self::STATUS_ACTIVE]);
    }

    public function getAllParentAgentId()
    {
        return empty($this->all_parent_agent_id)?[]:json_decode($this->all_parent_agent_id,true);
    }

    public function getParentAgent()
    {
        return $this->hasOne(User::class, ['id'=>'parent_agent_id']);
    }

    /**
     * 商户开户费信息
     *
     * @return \yii\db\ActiveQuery
     */
    public function getAccountOpenFeeInfo()
    {
        return $this->hasOne(AccountOpenFee::class, ['user_id'=>'id']);
    }

    /**
     * 是否需要支付开户费
     *
     * 商户且设置了开户费且未支付的需要进行支付
     */
    public function needPayAccountOpenFee()
    {
        $paid = false;
        if($this->group_id == self::GROUP_MERCHANT){
            $accountFee = $this->accountOpenFeeInfo;
            if($accountFee && $accountFee->needPay()){
                $paid = true;
            }
        }

        return $paid;
    }

    /**
     * 获取开户费订单链接
     *
     * 商户且设置了开户费且未支付的需要进行支付
     */
    public function getPayAccountOpenFeeOrderUrl()
    {
        $accountFee = $this->accountOpenFeeInfo;
        if($accountFee->fee>0){

        }

        return $paid;
    }

    /*
    * 获取状态描述
    *
    * @param int $status 状态ID
    * @return string
    * @author bootmall@gmail.com
    */
    public static function getStatusStr($status)
    {
        return self::ARR_STATUS[$status]??'-';
    }

    /*
    * 获取分组描述
    *
    * @param int $groupId 分组ID
    * @return string
    * @author bootmall@gmail.com
    */
    public static function getGroupStr($groupId)
    {
        return self::ARR_GROUP[$groupId]??'-';
    }

    /*
    * 获取分组英文描述
     *
    * @param int $groupId 分组ID
    * @return string
    * @author bootmall@gmail.com
    */
    public static function getGroupEnStr($groupId)
    {
        return self::ARR_GROUP_EN[$groupId]??'';
    }

    /**
     * 注销
     */
    public function logOut()
    {
        $this->access_token = '';
        $this->save();
    }

    public function getTags()
    {
        return $this->hasMany(Tag::className(), ['id' => 'object_id'])
            ->viaTable(TagRelation::tableName(), ['tag_id' => 'id']);
    }

    /*
     * 根据uid获取他的标签
     *
     * @param int $uid 用户UID
     */
    public static function getTagsArr($uid)
    {
        $tags = (new \yii\db\Query())->from(TagRelation::tableName().' r')
            ->select(['t.id', 't.name'])
            ->leftJoin(Tag::tableName().' t', 't.id=r.tag_id')
        ->where(['r.object_type' => 1,'r.object_id'=>$uid])
            ->all();
        return $tags;
//        $sql = "select t.* form ".Tag::tableName()." t,".TagRelation::tableName()." r WHERE t.id=r.tag_id AND r.object_type=1 AND t.id={$uid}";
    }
    /**
     * 根据uids获取他们的上级代理的用户名
     */
    public static function getParentUserName($uids)
    {
        return self::find()->where(['in','id',$uids])->select('id,username')->asArray()->all();
    }

    /**
     * 设置用户基础角色
     */
    public function setBaseRole()
    {
        $auth    = Yii::$app->authManager;
        $baseRole = $auth->getRole(AuthItem::ROLE_USER_BASE);
        $auth->revoke($baseRole, $this->id);
        $auth->assign($baseRole, $this->id);

        $this->delPermissionCache($this->id);
    }

    /**
     * 设置用户分组对应角色(含基础权限)
     */
    public function setGroupRole()
    {
        $groupStr = self::getGroupEnStr($this->group_id);
        $auth    = Yii::$app->authManager;
        //主账户才授予分组权限,子账户需要主账户单独赋予角色
        if($groupStr && $this->isMainAccount()){
            $baseRole = $auth->getRole($groupStr);
            $auth->revoke($baseRole, $this->id);
            $auth->assign($baseRole, $this->id);
        }
        $this->setBaseRole();

    }


    /**
     * 获取代理
     * 切换上级 获取出款费率，收款费率 比需要切换上级代理的商户费率 小的
     */
    public static function getAgentAll($agentIds,$methods,$remit_fee)
    {
        $allAgentIds = [];
        $paymethodInfoQuery = UserPaymentInfo::find();
        $paymethodInfoQuery->andWhere(['<=','remit_fee',$remit_fee]);
        $paymethodInfoQuery->andWhere(['not in','user_id',$agentIds]);
        $paymethodInfoQuery->select('user_id');
        $paymethodInfo = $paymethodInfoQuery->asArray()->all();
        if($paymethodInfo){
            foreach ($paymethodInfo as $key => $val){
                $allAgentIds[$key] = $val['user_id'];
            }
        }
        if(empty($allAgentIds)){
            return $allAgentIds;
        }else{
            $filter = ['and',['in','merchant_id',$allAgentIds]];
            $minFeeFilter = ['or'];
            $countMethods = 0;
            foreach ($methods as $key => $val){
                if($val > 0 ){
                    $minFeeFilter[]=["and","method_id='{$key}'","fee_rate<={$val}"];
                    $countMethods += 1;
                }
            }
            $filter[] = $minFeeFilter;
            $merchantRechargeMethods = (new Query())->select('count(id) as total,merchant_id')->from(MerchantRechargeMethod::tableName())->where($filter)->groupBy('merchant_id')->all();
            if($merchantRechargeMethods){
                $allAgentIds = [];
                foreach ($merchantRechargeMethods as $key => $val){
                    if($val['total'] == $countMethods){
                        $allAgentIds[$key] = $val['merchant_id'];
                    }
                }
            }
//            var_dump($allAgentIds);die;
            if(empty($allAgentIds)){
                return $allAgentIds;
            }else{
                return self::find()->where(['in','id',$allAgentIds])->andWhere(['group_id' => 20])->select('id,username')->asArray()->all();
            }
        }
    }

    /**
     * 是否是商户账户
     */
    public function isMerchant()
    {
        return $this->group_id==self::GROUP_MERCHANT;
    }

    /**
     * 是否是代理账户
     */
    public function isAgent()
    {
        return $this->group_id==self::GROUP_AGENT;
    }

    /**
     * 是否是主账号
     */
    public function isMainAccount()
    {
        return $this->parent_merchant_id==0;
    }

    /**
     * 获取主账号
     */
    public function getMainAccount()
    {
        if($this->parent_merchant_id==0){
            return $this;
        }else{
            return self::findOne(['id'=>$this->parent_merchant_id]);
        }
    }

    /**
     * 获取代理的所有下级账户
     *
     * @param $uid 用户ID
     * @return array
     */
    public static function getAllAgentChildren($uid)
    {
        $query = User::find();
        $query->andWhere(['like','all_parent_agent_id',','.$uid.',']);
        $query->orWhere(['like','all_parent_agent_id','['.$uid.']']);
        $query->orWhere(['like','all_parent_agent_id','['.$uid.',']);
        $query->orWhere(['like','all_parent_agent_id',','.$uid.']']);
        $query->select('all_parent_agent_id');
        $children = $query->all();

        return $children;
    }
    
    /***************web接口用户登录,角色权限等相关方法******************/
    
    
    public static function findIdentity($id){
        return static::findOne(['id'=>$id,'status'=>self::STATUS_ACTIVE]);
    }

    public static function findByPasswordResetToken($token){
        if(!static::isPasswordResetTokenValid($token)){
            Util::throwException(Macro::ERR_NEED_LOGIN,'密码重置令牌已过期');
        }

        return static::findOne([
            "password_reset_token"=>$token,
            "status"=>self::STATUS_ACTIVE,
        ]);
    }

    public static function isPasswordResetTokenValid($token){
        if(empty($token)){
            return false;
        }
        $expire=Yii::$app->params['user.passwordResetTokenExpire'];
        $parts=  explode("_", $token);
        $timestamp=(int)end($parts);
        return $timestamp+$expire >= time();
    }

    /**
     * 检测当前用户是否进行了2次令牌校验
     *
     * @return bool
     */
    public function is2faChecked(){
        if(!empty($this->key_2fa) && $this->key_2fa_token != $this->access_token){
            return false;
        }

        return true;
    }

    public function getId(){
        return $this->getPrimaryKey();
    }

    public function getAuthKey(){
        return $this->auth_key;
    }

    public function generateAuthKey(){
        $this->auth_key=Yii::$app->security->generateRandomString();
    }

    public function validateAuthKey($authKey){
        return $this->getAuthKey()===$authKey;
    }

    public function validatePassword($password){
        $password = $this->getSlatedPlainPassword($password);

        return Yii::$app->security->validatePassword($password,$this->password_hash);
    }

    /**
     * 检测当前请求ip是否中商户配置的白名单中
     * @return bool
     */
    public function validLoginIp()
    {
        if($this->bind_login_ip){
            $ip = Util::getClientIp();
            $allowIps = json_decode($this->bind_login_ip);

            if($allowIps && !in_array($ip,$allowIps)){
                return false;
            }
        }

        return true;
    }

    public function setDefaultPassword(){
        $password = SiteConfig::cacheGetContent('user_default_password');
        $this->setPassword($password);
        return $this;
    }
    public function setPassword($password){
        $this->setSalt();
        $password = $this->getSlatedPlainPassword($password);
        $this->password_hash=Yii::$app->security->generatePasswordHash($password);
    }

    public function setSalt(){
        $this->salt=Yii::$app->security->generateRandomString(16);
    }

    public function genratePasswordResetToken(){
        $this->password_reset_token=Yii::$app->security->generateRandomString()."_".time();
    }

    public function removePasswordResetToken(){
        $this->password_reset_token=null;

    }
    public function setFinancialPassword($password)
    {
        $password = md5($password.$this->username);
        $this->financial_password_hash=Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * 检测资金密码是否设置
     */
    public function checkFinancialPasswordIsSet()
    {
        return $this->financial_password_hash!='';
    }

    public function validateFinancialPassword($password){
        if(!$this->checkFinancialPasswordIsSet()){
            throw new OperationFailureException('请先设置资金密码',Macro::FAIL);
        }

        $cacheKey = 'validateFinancialPassword_ret_'.$this->id;
        $limitData = Yii::$app->cache->get($cacheKey);
        $limitDuration = 1800;
        $maxTime = 5;
        if($limitData){
            $limitData = json_decode($limitData,true);
            if(isset($limitData['ts']) && $limitData['ts']>(time()-$limitDuration)){
                if(isset($limitData['times']) && $limitData['times']>=$maxTime){
                    throw new OperationFailureException('资金密码失败次数过多，请稍候重试或联系客服。',Macro::FAIL);
                }

            }
        }else{
            $limitData = [
                'times'=>0,
                'ts'=>time(),
            ];
        }
        $password = md5($password.$this->username);
        $ret = false;
        if(!Yii::$app->security->validatePassword($password,$this->financial_password_hash)){
            if(empty($limitData['times'])) $limitData['times'] = 0;
            $limitData['times'] += 1;
            $limitData['ts'] = time();
            Yii::info('validate err: '.$cacheKey.\GuzzleHttp\json_encode($limitData));
        }else{
            $ret = true;
            $limitData = [];
        }
        Yii::$app->cache->set($cacheKey,json_encode($limitData),$limitDuration);

        return $ret;
    }

    /**
     * 校验用户二步验证码是否设置
     */
    public function checkKey2faIsSet()
    {
        return $this->key_2fa!='';
    }

    /**
     * 校验用户二步验证码(google验证码)
     * @param string $key2fa
     * @return bool
     */
    public function validateKey2fa(string $key2fa){
        if(!$this->checkKey2faIsSet()){
            throw new OperationFailureException('请先设置安全密码',Macro::FAIL);
        }

        $cacheKey = 'validateKey2fa_ret_'.$this->id;
        $limitData = Yii::$app->cache->get($cacheKey);
        $limitDuration = 1800;
        $maxTime = 5;
        if($limitData){
            $limitData = json_decode($limitData,true);
            if(isset($limitData['ts']) && $limitData['ts']>(time()-$limitDuration)){
                if(isset($limitData['times']) && $limitData['times']>=$maxTime){
                    throw new OperationFailureException('安全密码失败次数过多，请稍候重试或联系客服。',Macro::FAIL);
                }
            }
        }else{
            $limitData = [
                'times'=>0,
                'ts'=>time(),
            ];
        }

        $ret = false;
        $googleObj = new \PHPGangsta_GoogleAuthenticator();
        if(!$googleObj->verifyCode($this->key_2fa, $key2fa,2)){
            $limitData['times'] += 1;
            $limitData['ts'] = time();
        }else{
            $ret = true;
            $limitData = [];
        }
        Yii::$app->cache->set($cacheKey,json_encode($limitData),$limitDuration);

        return $ret;
    }

    /**
     * 清除用户资金密码等安全判断缓存
     * 可在用户资金密码等错误次数过多，需要手动恢复时调用
     */
    public function clearSecurityErrorCache()
    {
        Yii::$app->cache->delete('validateKey2fa_ret_'.$this->id);
        Yii::$app->cache->delete('validateFinancialPassword_ret_'.$this->id);
    }


    public function getSlatedPlainPassword($password)
    {
        $password = md5($this->salt.$password.$this->username);

        return $password;
    }

    public function getToken()
    {
        return $this->access_token;
    }

    public function getName()
    {
        return $this->username;
    }

    public function getNickname()
    {
        return $this->nickname;
    }

    public function getAvatar()
    {
        if(!$this->avatar){
            $this->avatar = Yii::$app->request->hostInfo.'/assets/imgs/avatar-default.png';
        }

        return $this->avatar;
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        if(!static::accessTokenIsValid($token)){
            Util::throwException(Macro::ERR_NEED_LOGIN,'登录令牌已过期');
        }

        return static::findOne(['access_token' => $token]);
    }

    public function loginByAccessToken($accessToken, $type) {
        //查询数据库中有没有存在这个token
        return static::findIdentityByAccessToken($accessToken, $type);
    }

    /**
     * 返回在单位时间内允许的请求的最大数目，例如，[10, 60] 表示在60秒内最多请求10次。
     *
     * @author booter.ui@gmail.com
     */
    public function getRateLimit($request, $action)
    {
        $limit = Yii::$app->params['user.rateLimit'];
        if(empty($limit)) $limit = [20, 60];
        return $limit;
    }

    /**
     * 返回剩余的允许的请求数
     *
     * @author booter.ui@gmail.com
     */
    public function loadAllowance($request, $action)
    {
        return [$this->rest_allowance, $this->rest_allowance_updated_at];
    }

    /**
     * 保存请求时的UNIX时间戳
     *
     * @author booter.ui@gmail.com
     */
    public function saveAllowance($request, $action, $allowance, $timestamp)
    {
        $this->rest_allowance = $allowance;
        $this->rest_allowance_updated_at = $timestamp;
        $this->save();
    }

    /**
     * 生成 access_token
     */
    public function generateAccessToken()
    {
        $this->access_token = md5(Yii::$app->security->generateRandomString()). '_' . time();
        return $this->access_token;
    }

    /**
     * 刷新access_token
     */
    public function refreshAccessToken()
    {
        $token = md5(Yii::$app->security->generateRandomString()). '_' . time();

        //如果是校验过令牌的,同时刷新令牌
        if($this->access_token == $this->key_2fa_token){
            $this->key_2fa_token = $token;
        }
        $this->access_token = $token;
        $this->save();
        return $this->access_token;
    }

    /**
     * 校验access_token是否有效
     */
    public static function accessTokenIsValid($token)
    {
        $timestamp = (int) substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.apiTokenExpire'];
        $valid =  $timestamp + $expire >= time();
        $valid = $valid && Util::validate($token,Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,[40]);
        if(!$valid){
            Yii::error("accessTokenIsValid: {$token},".json_encode($valid));
        }
        return $valid;
    }

    /**
     * 是否有某个action的权限
     *
     * return array
     */
    public function hasPrivilegeOfAction(Request $request)
    {
        $role = static::ROLES[static::ROLE_NONE];

        if($this->group_id && !empty(static::ROLES[$this->group_id])){
            $role = static::ROLES[$this->group_id];
        }
        return [$role];
    }

    /**
     * 获取当前用户组名
     *
     * return array
     */
    public function getRole()
    {
        $role = '';

        if($this->group_id && !empty(static::ARR_GROUP[$this->group_id])){
            $role = static::ARR_GROUP[$this->group_id];
        }
        return [$role];
    }

    /**
     * 获取当前用户组名
     *
     * @param boolean $fromCache 是否从缓存中获取权限列表,默认是
     * return array
     */
    public function getPermissions($fromCache=true)
    {
        $permissions = [];

        if($fromCache){
            $permissions = Yii::$app->redis->hget(Macro::CACHE_HSET_USER_PERMISSION,$this->id);
            if($permissions) $permissions = json_decode($permissions, true);
        }

        if(!$permissions){
            $auth    = Yii::$app->authManager;
            $userAllPermssions = $auth->getPermissionsByUser($this->id);

            foreach ($userAllPermssions as $i=>$p){
                $parentPos = strpos($p->name,'|');

                if($parentPos!==false){
                    $p->name = substr($p->name,$parentPos+1);
                    $parent = substr($p->name,0,$parentPos);
                    if(!in_array($parent,$permissions)){
                        $permissions[] = $parent;
                    }
                }

                $permissions[] = $p->name;
            }

            Yii::$app->redis->hset(Macro::CACHE_HSET_USER_PERMISSION,$this->id,json_encode($permissions));

        }

        return $permissions;
    }

    /**
     * 清除用户权限缓存
     *
     * @param int $uid 指定的uid,传0表示删除所有人缓存
     * return array
     */
    public static function delPermissionCache($uid)
    {
        if($uid){
            Yii::$app->redis->hdel(Macro::CACHE_HSET_USER_PERMISSION,$uid);
        }else{
            Yii::$app->redis->del(Macro::CACHE_HSET_USER_PERMISSION);
        }

    }

    /**
     * 获取当前用户组ID
     *
     * return array
     */
    public function getRoleIds()
    {
        $role = [];

        if($this->group_id && !empty(static::ARR_GROUP[$this->group_id])){
            $role[] = $this->group_id;
        }
        return $role;
    }

    /**
     * 是否是管理员组
     *
     * return boolean
     */
    public function isAdmin()
    {
        return $this->group_id == self::GROUP_ADMIN;
    }

    public function isSuperAdmin()
    {
        $ret = false;
        if($this->isAdmin() && $this->parent_merchant_id==0){
            $ret = true;
        }

        return $ret;
    }
}