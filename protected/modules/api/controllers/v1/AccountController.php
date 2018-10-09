<?php
/**
 * Created by PhpStorm.
 * Date: 2018/5/12
 * Time: 上午12:14
 */
namespace app\modules\api\controllers\v1;

use app\common\models\logic\LogicElementPagination;
use app\common\models\model\Channel;
use app\common\models\model\ChannelAccount;
use app\common\models\model\ChannelAccountRechargeMethod;
use app\common\models\model\Financial;
use app\common\models\model\MerchantRechargeMethod;
use app\common\models\model\Order;
use app\common\models\model\Remit;
use app\common\models\model\SiteConfig;
use app\common\models\model\User;
use app\common\models\model\UserPaymentInfo;
use app\components\Macro;
use app\components\Util;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use yii\helpers\ArrayHelper;

/*
 * 商户管理
 */
class AccountController extends BaseController
{
    //基础查询参数，例如查询订单只能查询自己的
    protected $baseFilter = [];

    public function behaviors()
    {
        $parentBehaviors = parent::behaviors();
        //验证码不需要token验证
        $behaviors = [];
        $behaviors = \yii\helpers\ArrayHelper::merge($parentBehaviors, $behaviors);

        return $behaviors;
    }

    /**
     * 前置action
     *
     * @author bootmall@gmail.com
     */
    public function beforeAction($action)
    {
        $ret = parent::beforeAction($action);

        return $ret;
    }
    /**
     * 新增下级商户
     */
    public function actionEdit()
    {
        $arrAllParams   = $this->getAllParams();

        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', 0, Macro::CONST_PARAM_TYPE_INT, '用户id错误');
        $username = ControllerParameterValidator::getRequestParam($arrAllParams, 'username',null,Macro::CONST_PARAM_TYPE_USERNAME,'登录账户错误');
        $email = ControllerParameterValidator::getRequestParam($arrAllParams, 'email',null,Macro::CONST_PARAM_TYPE_EMAIL,'email错误',
            User::ARR_STATUS);
        //管理员只能开代理
        $data['group_id'] = ControllerParameterValidator::getRequestParam($arrAllParams, 'group_id',null,Macro::CONST_PARAM_TYPE_ARRAY_HAS_KEY,'账户类型错误',User::ARR_GROUP);
        $data['remit_fee'] = ControllerParameterValidator::getRequestParam($arrAllParams, 'remit_fee',null,Macro::CONST_PARAM_TYPE_DECIMAL,'结算手续费错误');
        $data['pay_method'] = ControllerParameterValidator::getRequestParam($arrAllParams, 'pay_method',null,Macro::CONST_PARAM_TYPE_ARRAY,'收款方式及费率配置错误');
        $data['recharge_quota_pertime'] = ControllerParameterValidator::getRequestParam($this->allParams, 'recharge_quota_pertime',0,Macro::CONST_PARAM_TYPE_DECIMAL,'充值单笔限额错误');
        $data['remit_quota_pertime'] = ControllerParameterValidator::getRequestParam($this->allParams, 'remit_quota_pertime',0,Macro::CONST_PARAM_TYPE_DECIMAL,'提款单笔限额错误');

        $data['allow_api_recharge'] = ControllerParameterValidator::getRequestParam($this->allParams, 'allow_api_recharge',1,Macro::CONST_PARAM_TYPE_INT,'允许接口收款错误');
        $data['allow_manual_recharge'] = ControllerParameterValidator::getRequestParam($this->allParams, 'allow_manual_recharge',1,Macro::CONST_PARAM_TYPE_INT,'允许手工收款错误');
        $data['allow_api_remit'] = ControllerParameterValidator::getRequestParam($this->allParams, 'allow_api_remit',1,Macro::CONST_PARAM_TYPE_INT,'允许接口结算错误');
        $data['allow_manual_remit'] = ControllerParameterValidator::getRequestParam($this->allParams, 'allow_manual_remit',1,Macro::CONST_PARAM_TYPE_INT,'允许手工结算错误');
        $data['allow_api_fast_remit'] = ControllerParameterValidator::getRequestParam($this->allParams, 'allow_api_fast_remit','',Macro::CONST_PARAM_TYPE_INT,'接口结算不需审核错误');
        $data['allow_manual_fast_remit'] = ControllerParameterValidator::getRequestParam($this->allParams, 'allow_manual_fast_remit','',Macro::CONST_PARAM_TYPE_INT,'手工结算不需审核错误');
        //收款和出款通道在通道切换处统一设置
        $channelAccountId = ControllerParameterValidator::getRequestParam($arrAllParams, 'channel',0,Macro::CONST_PARAM_TYPE_INT_GT_ZERO,'收款通道错误');
        $remitChannelAccountId = ControllerParameterValidator::getRequestParam($arrAllParams, 'remit_channel',0,Macro::CONST_PARAM_TYPE_INT_GT_ZERO,'出款通道错误');
        $userObj = Yii::$app->user->identity;
        $parentAccount = $userObj->getMainAccount();

        //校验渠道
        if($channelAccountId){
            $channel = ChannelAccount::find()->where(['id'=>$channelAccountId,'status'=>ChannelAccount::STATUS_ACTIVE])->limit(1)->one();
            if(!$channel){
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'收款渠道帐号不存在或未激活');
            }
            //收款费率不能低于通道最低费率
            $channelMinRate = $channel->getPayMethodsArr();
            foreach ($data['pay_method'] as $i=>$pm){
                if(empty($pm['rate'])){
                    unset($data['pay_method'][$i]);
                    continue;
                }

                foreach ($channelMinRate as $cmr){
                    if($pm['id']==$cmr->id && $pm['status'] == '1'){
                        if($pm['rate']<$cmr->fee_rate){
                            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,"收款渠道费率不能低于渠道最低费率(".Channel::ARR_METHOD[$pm['id']].":{$cmr->fee_rate})");
                        }
                    }
                }
            }
        }
        //校验出款渠道
        if($remitChannelAccountId){
            $remitChannel = ChannelAccount::find()->where(['id'=>$remitChannelAccountId,'status'=>ChannelAccount::STATUS_ACTIVE])->limit(1)->one();
            if(!$remitChannel){
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'出款渠道不存在或未激活');
            }
        }

        $data['remit_fee_rebate'] = 0;
        $parentMinRate = $parentAccount ? $parentAccount->paymentInfo->payMethods:[];
        foreach ($data['pay_method'] as $i => $pm) {
            $data['pay_method'][$i]['parent_method_config_id']     = 0;
            $data['pay_method'][$i]['parent_recharge_rebate_rate'] = 0;
            $data['pay_method'][$i]['all_parent_method_config_id'] = [];
            if($parentMinRate){
                foreach ($parentMinRate as $k => $cmr) {
                    if ($pm['id'] == $cmr->method_id && $pm['status'] == '1') {
                        if ($pm['rate'] < $cmr->fee_rate) {
                            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, "收款渠道费率不能低于上级费率(" . Channel::ARR_METHOD[$pm['id']] . ":{$cmr->fee_rate})");
                        }
                        //提前计算好需要给上级的分润比例
                        $data['pay_method'][$i]['parent_method_config_id']     = $cmr->id;
                        $data['pay_method'][$i]['parent_recharge_rebate_rate'] = bcsub($pm['rate'], $cmr->fee_rate, 9);
                        $allMids                                               = !empty($cmr->all_parent_method_config_id) ? json_decode($cmr->all_parent_method_config_id, true) : [];
                        array_push($allMids, $cmr->id);
                        $data['pay_method'][$i]['all_parent_method_config_id'] = $allMids;
                        $data['pay_method'][$i]['settlement_type'] = $cmr->settlement_type?$cmr->settlement_type:SiteConfig::cacheGetContent('default_settlement_type');
                    }
                }
            }
            $data['pay_method'][$i]['all_parent_method_config_id'] = json_encode($data['pay_method'][$i]['all_parent_method_config_id']);
        }

        $data['remit_fee_rebate'] = 0;
        if ($parentAccount) {
            $parentRemitFee = $parentAccount->paymentInfo->remit_fee;//
            if ($data['remit_fee'] < $parentRemitFee) {
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, "出款手续费不能低于上级:{$parentRemitFee}");
            }
            $data['remit_fee_rebate'] = bcsub($data['remit_fee'], $parentRemitFee, 9);
        }


        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {

            if ($id) {
                $user = User::findOne(['id' => Yii::$app->user->identity->getId()]);
                if (!$user) {
                    return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '编辑的账户不存在');
                }

                $userPayment = $user->paymentInfo;
            }else{

                $user = User::findOne(['username'=> $username]);
                if($user){
                    return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'账户已存在');
                }
                $user = new User();
                $user->username = $username;
                $user->email = $email;
                $user->setDefaultPassword();
                $userPayment = new UserPaymentInfo();
            }

            if($parentAccount){
                $user->parent_agent_id = $parentAccount->id;

                $parentIds = json_decode($parentAccount->all_parent_agent_id);
                $parentIds[] = $parentAccount->id;
                $user->all_parent_agent_id = json_encode($parentIds);
            }

            $user->group_id = $data['group_id'];
            $user->status = User::STATUS_INACTIVE;
            $user->save();

            if (!$id) {
                $userPayment->user_id     = $user->id;
                $userPayment->username = $user->username;
                $userPayment->app_key_md5 = Util::uuid('uuid');
            }
            $userPayment->app_id = $user->id;
            //开户时不指定收款及出款渠道
            if($channelAccountId){
                $userPayment->channel_account_id = $channel->id;
                $userPayment->channel_id = $channel->channel_id;
                $userPayment->channel_account_name = $channel->channel_name;
            }
            if($remitChannelAccountId){
                $userPayment->remit_channel_id = $remitChannel->channel_id;
                $userPayment->remit_channel_account_id = $remitChannel->id;
                $userPayment->remit_channel_account_name = $remitChannel->channel_name;
            }

            $userPayment->remit_fee_rebate = $data['remit_fee_rebate'];
            $userPayment->remit_fee = $data['remit_fee'];
            $userPayment->recharge_quota_pertime = $data['recharge_quota_pertime'];
            $userPayment->remit_quota_pertime = $data['remit_quota_pertime'];
            $userPayment->allow_api_recharge = 1;//$data['allow_api_recharge'];
            $userPayment->allow_manual_recharge = 1;//$data['allow_manual_recharge'];
            $userPayment->allow_api_remit = 1;//$data['allow_api_remit'];
            $userPayment->allow_manual_remit = 1;//$data['allow_manual_remit'];
            $userPayment->allow_api_fast_remit = SiteConfig::cacheGetContent('api_fast_remit_quota');
            $userPayment->allow_manual_fast_remit = SiteConfig::cacheGetContent('manual_fast_remit_quota');
            $userPayment->account_transfer_fee = SiteConfig::cacheGetContent('account_transfer_fee');
            $userPayment->remit_quota_pertime = $data['remit_quota_pertime'];
            $userPayment->save();

            //批量写入每种支付类型配置
            foreach ($data['pay_method'] as $i=>$pm){
                $methodConfig = new MerchantRechargeMethod();

                $methodConfig->app_id = $user->id;
                $methodConfig->merchant_id = $user->id;
                $methodConfig->merchant_account = $user->username;

                $methodConfig->payment_info_id = $userPayment->id;
                $methodConfig->method_id = $pm['id'];
                $methodConfig->method_name = Channel::getPayMethodsStr($pm['id']);
                $methodConfig->fee_rate = $pm['rate'];
                $methodConfig->parent_method_config_id = $pm['parent_method_config_id'];
                $methodConfig->parent_recharge_rebate_rate = $pm['parent_recharge_rebate_rate'];
                $methodConfig->all_parent_method_config_id = $pm['all_parent_method_config_id'];
                $methodConfig->status = ($pm['status']==MerchantRechargeMethod::STATUS_ACTIVE)?MerchantRechargeMethod::STATUS_ACTIVE:MerchantRechargeMethod::STATUS_INACTIVE;
                $methodConfig->settlement_type = !empty($pm['settlement_type'])?$pm['settlement_type']:SiteConfig::cacheGetContent('default_settlement_type');

                if($channelAccountId){
                    $methodConfig->channel_account_id = $channel->id;
                    $methodConfig->channel_id = $channel->channel_id;
                    $methodConfig->channel_account_name = $channel->channel_name;
                }
                $methodConfig->save();
            }

            //账户角色授权
            $user->setGroupRole();

            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollBack();
            throw $e;
        } catch(\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        return ResponseHelper::formatOutput(Macro::SUCCESS,'',['id'=>$user->id,'username'=>$user->username]);
    }

    /**
     * 设置子账户权限
     */
    public function actionUpdateUserPermission()
    {
        $uid = ControllerParameterValidator::getRequestParam($this->allParams, 'uid', null, Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE, '名称错误', [1, 64]);
        $roles = ControllerParameterValidator::getRequestParam($this->allParams, 'roles', null, Macro::CONST_PARAM_TYPE_ARRAY, '权限错误');
        $masterMerchantId = ControllerParameterValidator::getRequestParam($this->allParams, 'master_merchant_id', '', Macro::CONST_PARAM_TYPE_INT, '商户主账号ID错误');
        if(is_string($roles)) $roles = [$roles];
        $parent = Yii::$app->user->identity;
        if(!empty($masterMerchantId)){
            $parent = User::findOne(['id'=>$masterMerchantId]);
        }
        $merchant = User::findOne(['id'=>$uid,'parent_merchant_id'=>$parent->getId()]);
        if(!$merchant){
            throw new \Exception('子账户不存在');
        }

        $auth = Yii::$app->authManager;

        //父级账户所有权限
        $parentGroupName = User::getGroupEnStr($parent->group_id);
        $filter = " and name like 'r_{$parentGroupName}_%'";
        //代理组还拥有商户组基本权限
        if($merchant->isAgent()){
            $filter = " and (name like 'r_{$parentGroupName}_%' or name like 'r_merchant_%')";
        }
        $rawAllRoles = Yii::$app->db->createCommand("select name from p_auth_item where type=1 {$filter}")->queryAll();
        $allRoles = [];
        foreach ($rawAllRoles as $rar){
            $allRoles[] = $rar['name'];
        }

        //提交上来的权限必须是账户所在用户组拥有的权限子集
        $filteredPermissions = array_intersect($allRoles, $roles);

        $auth->revokeAll($merchant->id);
        //赋予用户基础角色
        $merchant->setBaseRole();
        Yii::info('$filteredPermissions '.json_encode($filteredPermissions));
        foreach ($filteredPermissions as $pName){
            $roleObj = $auth->getRole($pName);
            $auth->assign($roleObj, $merchant->id);
        }

        return ResponseHelper::formatOutput(Macro::SUCCESS, '更新成功');
    }

    /**
     * 查看子账户权限
     */
    public function actionUserPermissionList()
    {
        $uid = ControllerParameterValidator::getRequestParam($this->allParams, 'uid', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '商户ID错误');
        $masterMerchantId = ControllerParameterValidator::getRequestParam($this->allParams, 'master_merchant_id', '', Macro::CONST_PARAM_TYPE_INT, '商户主账号ID错误');
        $parent = Yii::$app->user->identity;
        if(!empty($masterMerchantId)){
            $parent = User::findOne(['id'=>$masterMerchantId]);
        }
        $parentGroupName = User::getGroupEnStr($parent->group_id);

//        $merchant = User::findOne(['id'=>$uid,'parent_merchant_id'=>Yii::$app->user->identity->getId()]);
        $merchant = User::findOne(['id'=>$uid,'parent_merchant_id'=>$parent->id]);
        if(!$merchant){
            throw new \Exception('子账户不存在');
        }
        $auth = \Yii::$app->authManager;

        $filter = " and item_name like 'r_{$parentGroupName}_%'";
        //代理组还拥有商户组赋予的权限
        if($merchant->isAgent()){
            $filter = " and (item_name like 'r_{$parentGroupName}_%' or item_name like 'r_merchant_%')";
        }
        //子账户目前权限
        $userAllPermssions = Yii::$app->db->createCommand("select item_name from p_auth_assignment where user_id={$uid} {$filter}")
            ->queryAll();
        $data['userRoles'] = [];
        foreach($userAllPermssions as $up){
            $data['userRoles'][] = $up['item_name'];
        }

        //账户所在用户组拥有的所有权限
        $filter = " and name like 'r_{$parentGroupName}_%'";
        //代理组还拥有商户组基本权限
        if($merchant->isAgent()){
            $filter = " and (name like 'r_{$parentGroupName}_%' or name like 'r_merchant_%')";
        }
        $data['allRoles'] = Yii::$app->db->createCommand("select name,description from p_auth_item where type=1 {$filter}")
            ->queryAll();

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 下级商户列表
     */
    public function actionList()
    {

        $userObj = Yii::$app->user->identity;
        $user = $userObj->getMainAccount();
        //允许的排序
        $sorts = [
            'created_at-'=>['created_at',SORT_DESC],
        ];
        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100000]);
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'userId', 0, Macro::CONST_PARAM_TYPE_INT, '商户id错误');
        $username = ControllerParameterValidator::getRequestParam($this->allParams, 'username', '', Macro::CONST_PARAM_TYPE_USERNAME, '商户账户错误');
        $merchantId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId', 0, Macro::CONST_PARAM_TYPE_INT, '商户id错误');
        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status','',Macro::CONST_PARAM_TYPE_INT,'状态错误',[0,100]);
        $remit = ControllerParameterValidator::getRequestParam($this->allParams, 'remit','',Macro::CONST_PARAM_TYPE_INT,'下发通道错误',[0,100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
        $account_open_fee_status = ControllerParameterValidator::getRequestParam($this->allParams, 'accountOpenFeeStatus','',Macro::CONST_PARAM_TYPE_INT,'开户缴费状态错误',[0,100]);
        $type = ControllerParameterValidator::getRequestParam($this->allParams, 'type', '', Macro::CONST_PARAM_TYPE_INT, '账户类型错误', [0, 100]);

        if($sort && !empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['created_at',SORT_DESC];
        }

        //生成查询参数
        $filter = $this->baseFilter;

        $subQuery = UserPaymentInfo::find()->alias('upi')
//            ->joinWith('accountOpenFee c1')
            ->joinWith('remitChannel c2');
        if($remit!=''){
            $subQuery->andwhere(['c2.id' => $remit]);
        }
        $query = User::find()->alias('u')
            ->rightJoin(['p' => $subQuery], 'u.id = p.user_id')
            ->where($filter);

        if($merchantId){
            $query->andWhere(['u.parent_agent_id'=>$merchantId]);
        }else{
            $query->andWhere(['u.parent_agent_id'=>$user->id]);
        }
        if($dateStart){
            $query->andFilterCompare('u.created_at', '>='.strtotime($dateStart));
        }
        if($dateEnd){
            $query->andFilterCompare('u.created_at', '<'.strtotime($dateEnd));
        }

        if($status!=''){
            $query->andwhere(['u.status' => $status]);
        }
        if($account_open_fee_status != ''){
            $query->andwhere(['u.account_open_fee_status' => $account_open_fee_status]);
        }
        if($userId){
            $query->andwhere(['u.id' => $userId]);
        }
        if($username){
            $query->andwhere(['u.username' => $username]);
        }
        if ($type != '') {
            $query->andwhere(['u.group_id' => $type]);
        }

        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page-1,
            ],
            'sort' => [
                'defaultOrder' => [
                    $sort[0] => $sort[1],
                ]
            ],
        ]);

        //格式化返回记录数据
        $records=[];
        foreach ($p->getModels() as $i=>$u){
            $records[$i]['id']              = $u->id;
            $records[$i]['username']        = $u->username;
            $records[$i]['status']          = $u->status;
            $records[$i]['status_str']      = User::getStatusStr($u['status']);
            $records[$i]['group_id']        = $u['group_id'];
            $records[$i]['group_str']       = User::getGroupStr($u['group_id']);
            $records[$i]['created_at']      = date('Y-m-d H:i:s');
            $records[$i]['remit_channel_id']      = $u->paymentInfo->remit_channel_id;
            $records[$i]['remit_channel_name']      = $u->paymentInfo->remit_channel_account_name;
            $records[$i]['pay_config'] = $u->paymentInfo->getPayMethodsArrByAppId($u->id) ;
            $records[$i]['remit_fee'] = $u->paymentInfo->remit_fee;
            $records[$i]['account_open_fee_status'] = $u->account_open_fee_status;
            $records[$i]['account_open_fee_status_str'] = User::ARR_ACCOUNT_OPEN_FEE_STATUS[$u->account_open_fee_status];
            $records[$i]['account_open_fee'] = $u->account_open_fee;
        }
        $level = [];
        if($merchantId){
            $parentQuery = User::find();
            $parentQuery->andWhere(['id'=>$merchantId]);
            $where = [
                'or',
                ['like','all_parent_agent_id',','.$user->id.','],
                ['like','all_parent_agent_id','['.$user->id.']'],
                ['like','all_parent_agent_id','['.$user->id.','],
                ['like','all_parent_agent_id',','.$user->id.']']
            ];
            $parentQuery->andWhere($where);
            $parentQuery->select('all_parent_agent_id');
            $parentQuery->limit(1);
            $parent_level = $parentQuery->asArray()->all();
            if ($parent_level) {
                $_level =implode(',',json_decode($parent_level[0]['all_parent_agent_id'],true)).','. $merchantId;
                $_level = array_reverse(explode(',', substr($_level, 0, strlen($_level))));
                foreach ($_level as $key => $val) {
                    if ($val == $user->id)
                        break;
                    $level[$key] = $val;
                }
                $level = array_reverse($level);
                $userLower = User::find()->where(['in', 'id',$level])->select('id,username')->asArray()->all();
                $level = [];
                foreach ($userLower as $key => $val){
                    $level[$val['id']] = $val['username'];
                }
            }
        }

        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;
        //格式化返回json结构
        $data = [
            'data'=>$records,
            "pagination"=>[
                "total" =>  $total,
                "per_page" =>  $perPage,
                "current_page" =>  $page,
                "last_page" =>  $lastPage,
                "from" =>  $from,
                "to" =>  $to
            ],
            'level' =>$level
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }
    /**
     * 下级收款订单
     */
    public function actionAccountOrder()
    {
        $userObj = Yii::$app->user->identity;
        $user = $userObj->getMainAccount();
        //允许的排序
        $sorts = [
            'created_at-'=>['created_at',SORT_DESC],
        ];

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100000]);

        $orderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'orderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'平台订单号错误',[0,32]);
        $merchantOrderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantOrderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户订单号错误',[0,32]);

        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status','',Macro::CONST_PARAM_TYPE_INT,'订单状态错误',[0,100]);

        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
        $merchantNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户编号错误',[0,32]);
        $merchantAccount = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantAccount', '',Macro::CONST_PARAM_TYPE_STRING,'商户账户错误',[0,32]);


        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['created_at',SORT_DESC];
        }
        $userQuery = User::find();
        $userQuery->select('id');
        $where = [
            'or',
            ['like','all_parent_agent_id',','.$user->id.','],
            ['like','all_parent_agent_id','['.$user->id.']'],
            ['like','all_parent_agent_id','['.$user->id.','],
            ['like','all_parent_agent_id',','.$user->id.']']
        ];
        $userQuery->andWhere($where);
        $userInfo = $userQuery->asArray()->all();
        if(!$userInfo){
            return ResponseHelper::formatOutput(Macro::ERR_ACCOUNT_ORDER,'没有下级可查');
        }
        $userIds = [];
        foreach ($userInfo as $key=>$val){
            $userIds[] = $val['id'];
        }
        //生成查询参数
        $filter = $this->baseFilter;
        $query = Order::find()->where($filter);
        $query->andWhere(['in','merchant_id',$userIds]);
        if($dateStart){
            $query->andFilterCompare('created_at', '>='.strtotime($dateStart));
        }
        if($dateEnd){
            $query->andFilterCompare('created_at', '<'.strtotime($dateEnd));
        }
        if($merchantNo){
            $query->andwhere(['merchant_id' => $merchantNo]);
        }
        if($merchantAccount){
            $query->andwhere(['merchant_account' => $merchantAccount]);
        }
        if($orderNo){
            $query->andwhere(['order_no' => $orderNo]);
        }
        if($merchantOrderNo){
            $query->andwhere(['merchant_order_no' => $merchantOrderNo]);
        }
        if(!empty($status)){
            $query->andwhere(['status' => $status]);
        }

        if(!empty($method)){
            $query->andwhere(['pay_method_code' => $method]);
        }
        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page-1,
            ],
            'sort' => [
                'defaultOrder' => [
                    $sort[0] => $sort[1],
                ]
            ],
        ]);

//        获取渠道号 为筛选和订单详情准备数据
        $channelAccountOptions = ArrayHelper::map(ChannelAccount::getALLChannelAccount(), 'id', 'channel_name');
        $channelAccountOptions[0] = '全部';
        //格式化返回记录数据

        $records=[];
        $parentIds=[];
        foreach ($p->getModels() as $i=>$d){
            $records[$i]['id'] = $d->id;
            $parentIds[] = $d->id;
            $records[$i]['order_no'] = $d->order_no;
            $records[$i]['merchant_order_no'] = $d->merchant_order_no;
            $records[$i]['merchant_account'] = $d->merchant_account;
            $records[$i]['merchant_id'] = $d->merchant_id;
            $records[$i]['amount'] = $d->amount;
            $records[$i]['status'] = $d->status;
            $records[$i]['bank_code'] = $d->bank_code;
            $records[$i]['pay_method_code_str'] = Channel::getPayMethodsStr($d->pay_method_code);
            $records[$i]['status_str'] = $d->getStatusStr();
            $records[$i]['notify_status'] = $d->notify_status;
            $records[$i]['notify_status_str'] = $d->getNotifyStatusStr();
            $records[$i]['created_at'] = date('Y-m-d H:i:s',$d->created_at);
            $records[$i]['notify_ret'] = '';
            if($d->notify_status === Order::NOTICE_STATUS_FAIL) $records[$i]['notify_ret'] = $d->notify_ret;
        }

        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;

        //格式化返回json结构

        $data = [
            'data'=>$records,
            'condition'=>array(
                'statusOptions'=> Order::ARR_STATUS,
                'notifyStatusOptions'=>Order::ARR_NOTICE_STATUS,
                'channelAccountOptions'=>$channelAccountOptions,
                'methodOptions'=> Channel::ARR_METHOD,
            ),
            "pagination"=>[
                "total" =>  $total,
                "per_page" =>  $perPage,
                "current_page" =>  $page,
                "last_page" =>  $lastPage,
                "from" =>  $from,
                "to" =>  $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }
    /**
     * 下级出款列表
     */
    public function actionAccountRemit()
    {
        $userObj = Yii::$app->user->identity;
        $user = $userObj->getMainAccount();
        //允许的排序
        $sorts = [
            'created_at-'=>['created_at',SORT_DESC],
        ];
        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100000]);

        $merchantNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户编号错误',[0,32]);
        $merchantAccount = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantAccount', '',Macro::CONST_PARAM_TYPE_USERNAME,'商户账号错误',[2,16]);
        $orderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'orderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'结算订单号错误',[0,32]);
        $merchantOrderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantOrderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'商户订单号错误',[0,32]);

        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status','',Macro::CONST_PARAM_TYPE_INT,'订单状态错误',[0,100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');
        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['created_at',SORT_DESC];
        }

        $userQuery = User::find();
        $userQuery->select('id');
        $where = [
            'or',
            ['like','all_parent_agent_id',','.$user->id.','],
            ['like','all_parent_agent_id','['.$user->id.']'],
            ['like','all_parent_agent_id','['.$user->id.','],
            ['like','all_parent_agent_id',','.$user->id.']']
        ];
        $userQuery->andWhere($where);
        $userInfo = $userQuery->asArray()->all();
        if(!$userInfo){
            return ResponseHelper::formatOutput(Macro::ERR_ACCOUNT_ORDER,'没有下级可查');
        }
        $userIds = [];
        foreach ($userInfo as $key=>$val){
            $userIds[] = $val['id'];
        }
        //生成查询参数
        $filter = $this->baseFilter;
        $query = Remit::find()->where($filter);
        $query->andWhere(['in','merchant_id',$userIds]);
        if($dateStart){
            $query->andFilterCompare('created_at', '>='.strtotime($dateStart));
        }
        if($dateEnd){
            $query->andFilterCompare('created_at', '<'.strtotime($dateEnd));
        }

        if(!empty($merchantNo)){
            $query->andwhere(['merchant_id' => $merchantNo]);
        }
        if(!empty($merchantAccount)){
            $query->andwhere(['merchant_account' => $merchantAccount]);
        }
        if($orderNo){
            $query->andwhere(['order_no' => $orderNo]);
        }
        if($merchantOrderNo){
            $query->andwhere(['merchant_order_no' => $merchantOrderNo]);
        }

        if($status!==''){
            $query->andwhere(['bank_status' => $status]);
        }

        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page-1,
            ],
            'sort' => [
                'defaultOrder' => [
                    $sort[0] => $sort[1],
                ]
            ],
        ]);
//        获取渠道号 为筛选和订单详情准备数据
        $channelAccountOptions = ArrayHelper::map(ChannelAccount::getALLChannelAccount(), 'id', 'channel_name');
        $channelAccountOptions[0] = '全部';
        //格式化返回记录数据
        $records=[];
        $parentIds = [];
        foreach ($p->getModels() as $i=>$d){
            $parentIds[$i] = $d->id;
            $records[$i]['id'] = $d->id;
            $records[$i]['order_no'] = $d->order_no;
            $records[$i]['merchant_id'] = $d->merchant_id;
            $records[$i]['merchant_account'] = $d->merchant_account;
            $records[$i]['merchant_order_no'] = $d->merchant_order_no;
            $records[$i]['channel_order_no'] = $d->channel_order_no;
            $records[$i]['amount'] = $d->amount;
            $records[$i]['remited_amount'] = $d->remited_amount;
            $records[$i]['status'] = $d->status;
            $records[$i]['status_str'] = $d->showStatusStr();
            $records[$i]['bank_no'] = $d->bank_no;
            $records[$i]['bank_account'] = $d->bank_account;
            $records[$i]['bank_name'] = $d->bank_name;
            $records[$i]['created_at'] = date('Y-m-d H:i:s',$d->created_at);
        }

        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;

        //格式化返回json结构
        $data = [
            'data'=>$records,
            'condition'=>array(
                'statusOptions'=> ArrayHelper::merge([Macro::SELECT_OPTION_ALL=>'全部'],Remit::ARR_BANK_STATUS),
            ),
            "pagination"=>[
                "total" =>  $total,
                "per_page" =>  $perPage,
                "current_page" =>  $page,
                "last_page" =>  $lastPage,
                "from" =>  $from,
                "to" =>  $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 下级收支明细
     */
    public function actionAccountFinancial()
    {
        $userObj = Yii::$app->user->identity;
        $user = $userObj->getMainAccount();
        //允许的排序
        $sorts = [
            'created_at-'=>['created_at'=>SORT_DESC],
        ];

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100000]);

        $orderNo = ControllerParameterValidator::getRequestParam($this->allParams, 'orderNo', '',Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE,'平台订单号错误',[0,32]);

        $eventType = ControllerParameterValidator::getRequestParam($this->allParams, 'eventType','',Macro::CONST_PARAM_TYPE_INT,'订单状态错误',[0,100]);
        $notifyStatus = ControllerParameterValidator::getRequestParam($this->allParams, 'notifyStatus','',Macro::CONST_PARAM_TYPE_INT,'通知状态错误',[0,100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        $uid = ControllerParameterValidator::getRequestParam($this->allParams, 'userId','',Macro::CONST_PARAM_TYPE_INT,'商户号错误',[0,100]);

        $username = ControllerParameterValidator::getRequestParam($this->allParams, 'username','',Macro::CONST_PARAM_TYPE_STRING,'商户账号号错误',[6,16]);
        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort = ['id'=>SORT_DESC];
        }
        $userQuery = User::find();
        $userQuery->select('id');
        $where = [
            'or',
            ['like','all_parent_agent_id',','.$user->id.','],
            ['like','all_parent_agent_id','['.$user->id.']'],
            ['like','all_parent_agent_id','['.$user->id.','],
            ['like','all_parent_agent_id',','.$user->id.']']
        ];
        $userQuery->andWhere($where);
//        $userQuery->andWhere(['like','all_parent_agent_id',','.$user->id.',']);
//        $userQuery->orWhere(['like','all_parent_agent_id','['.$user->id.']']);
//        $userQuery->orWhere(['like','all_parent_agent_id','['.$user->id.',']);
//        $userQuery->orWhere(['like','all_parent_agent_id',','.$user->id.']']);
        $userInfo = $userQuery->asArray()->all();
        if(!$userInfo){
            return ResponseHelper::formatOutput(Macro::ERR_ACCOUNT_ORDER,'没有下级可查');
        }
        $userIds = [];
        foreach ($userInfo as $key=>$val){
            $userIds[] = $val['id'];
        }

        //生成查询参数
        $filter = $this->baseFilter;
        $query = Financial::find()->where($filter);
        $query->andWhere(['in','uid',$userIds]);

        if($dateStart){
            $query->andFilterCompare('created_at', '>='.strtotime($dateStart));
        }
        if($dateEnd){
            $query->andFilterCompare('created_at', '<'.strtotime($dateEnd));
        }
        if($orderNo){
            $query->andwhere(['event_id' => $orderNo]);
        }

        if($uid){
            $query->andwhere(['uid' => $uid]);
        }
        if($username){
            $query->andwhere(['username' => $username]);
        }
        $summeryQuery = $query;
        if($eventType!==''){
            $query->andwhere(['event_type' => $eventType]);
        }

        if($notifyStatus!==''){
            $query->andwhere(['notify_status' => $notifyStatus]);
        }

        //生成分页数据
        $fields = ['id','uid','username','event_id','amount','event_id','event_type','created_at'];
        $paginationData = LogicElementPagination::getPagination($query,$fields,$page-1,$perPage,$sort);
        $records=[];
        foreach ($paginationData['data'] as $i=>$d){
            $records[$i] = $d;
            $records[$i]['event_type_str'] = Financial::getEventTypeStr($d['event_type']);
            $records[$i]['created_at'] = date('Y-m-d H:i:s',$d['created_at']);
        }

        //格式化返回json结构
        $data = [
            'data'=>$records,
            "pagination"=>$paginationData['pagination'],
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     *
     * 下级列表表单选项值列表
     */
    public function actionFormOptionList()
    {
        $all = ControllerParameterValidator::getRequestParam($this->allParams, 'all',1,Macro::CONST_PARAM_TYPE_INT,'all参数错误');

        $data = [
            'user_status' => empty($all)?User::ARR_STATUS:ArrayHelper::merge(User::ARR_STATUS,[Macro::SELECT_OPTION_ALL=>'全部']),
            'account_open_fee_status' => empty($all)?User::ARR_ACCOUNT_OPEN_FEE_STATUS:ArrayHelper::merge(User::ARR_ACCOUNT_OPEN_FEE_STATUS,[Macro::SELECT_OPTION_ALL=>'全部']),
            'user_type' => empty($all)?User::ARR_GROUP:ArrayHelper::merge([Macro::SELECT_OPTION_ALL=>'全部'],User::ARR_GROUP),
            'pay_method' => empty($all)?Channel::ARR_METHOD:ArrayHelper::merge([Macro::SELECT_OPTION_ALL=>'全部'],Channel::ARR_METHOD),
        ];
        $data['channel'] = Yii::$app->db->createCommand('SELECT id,channel_name,group_id,group_name FROM '.ChannelAccount::tableName())
            ->queryAll();

        $data['channel'] = empty($all)?$data['channel']:ArrayHelper::merge([['id'=>Macro::SELECT_OPTION_ALL,'channel_name'=>'全部']],$data['channel']);
        $data['user_type'] = array_filter($data['user_type'], function($v) {
            return $v!=User::ARR_GROUP[User::GROUP_ADMIN];
        });

        return ResponseHelper::formatOutput(Macro::SUCCESS,'', $data);
    }

    /**
     *
     * 我的支付方式列表
     *
     * @user_base
     */
    public function actionMyRechargeMethodList()
    {
        $type = ControllerParameterValidator::getRequestParam($this->allParams, 'type', 1,Macro::CONST_PARAM_TYPE_INT,'订单类型错误');

        $userObj = Yii::$app->user->identity;
        $mainAccount = $userObj->getMainAccount();

        //开户费充入系统账户,需要列出系统账户支持的充值方式
        if($type == 3){
            $merchantName = SiteConfig::cacheGetContent('account_open_fee_in_account');
            $mainAccount = User::findOne(['username'=>$merchantName]);
            if(empty($mainAccount)){
                Yii::error("系统商户开户费转入账户设置错误");
                Util::throwException(Macro::ERR_USER_NOT_FOUND,"商户开户费账户设置错误，请联系客服！");
            }
        }

        $methods = (new Query())->select(["id","method_id","method_name"])
            ->from(MerchantRechargeMethod::tableName())
            ->where(['app_id'=>$mainAccount->id])
            ->andWhere(['<>', 'channel_id', ''])
            ->all();

        return ResponseHelper::formatOutput(Macro::SUCCESS,'', $methods);
    }

    /**
     * 代理交易明细
     *
     * @roles agent
     */
    public function actionRechargeSumStatistic()
    {
        $userObj = Yii::$app->user->identity;
        $user = $userObj->getMainAccount();
        //允许的排序
        $sorts = [
            'created_at-'=>['created_at',SORT_DESC],
        ];
        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100000]);
        $merchantId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchant_id', '', Macro::CONST_PARAM_TYPE_INT, '商户id错误');
        $merchantName = ControllerParameterValidator::getRequestParam($this->allParams, 'merchant_name', '', Macro::CONST_PARAM_TYPE_USERNAME, '商户账户错误');
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        if($sort && !empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['created_at',SORT_DESC];
        }

        //生成查询参数
        $firstChildFieldStr = "";
        /**
         * 汇总统计商户直属下级(商户及代理)订单数据
         *
         * 利用substring_index可以截取订单表父级列表字段中本UID之后的所有字符串,然后再将]替换成',',最好截取掉第一个,字符之后的所有字符串,即可得到第一个直属下级
         * sql: select substring_index(REPLACE(substring_index('[2012234,3322,333]','2012234,',-1),']',','),',',1) AS first_child_id => 3322
         *
         * 问题,在只有订单为本人直属下级商户的时候,以上sql将无法运作: select substring_index('[2012234]','2012234,',-1) => [2012234]. group by first_child_id之后将会只有一条记录
         * 解决办法: 使用CASE判断是否订单是直属下级商户订单 CASE all_parent_agent_id WHEN '[2012234]' THEN merchant_id ELSE substring_index(REPLACE(substring_index(all_parent_agent_id,'2012234,',-1),']',','),',',1) END
         */
        //原始版,直属下级无法统计
//        $firstChildFieldStr = "substring_index(REPLACE(substring_index(all_parent_agent_id,'{$user->id},',-1),']',','),',',1) AS first_child_id";
        $merchantIdLen = strlen($user->id)+2;
        //优化如果是直属下级的订单
        $firstChildFieldStr = "(CASE WHEN all_parent_agent_id='[{$user->id}]' THEN merchant_id WHEN substring(all_parent_agent_id,-{$merchantIdLen})=',{$user->id}]' THEN merchant_id  ELSE substring_index(REPLACE(substring_index(all_parent_agent_id,'{$user->id},',-1),']',','),',',1) END) as first_child_id";
        $orderQuery = (new Query())->select(["merchant_id","merchant_account as oder_merchant_account","sum(amount) as amount","count(amount) as num",$firstChildFieldStr])
            ->from(Order::tableName())
            ->where(['status'=>[Order::STATUS_SETTLEMENT,Order::STATUS_PAID]])
            ->orderBy("merchant_id ASC");

        $where = [
            'or',
            ['all_parent_agent_id'=>'['.$user->id.']'],
            ['like','all_parent_agent_id',','.$user->id.','],
            ['like','all_parent_agent_id','['.$user->id.','],
            ['like','all_parent_agent_id',','.$user->id.']'],
        ];
        $orderQuery->andWhere($where);
        if ($merchantId != '') {
            $orderQuery->andwhere(['merchant_id' => $merchantId]);
        }
        if ($merchantName != '') {
            $orderQuery->andwhere(['merchant_account' => $merchantName]);
        }

        if(!$dateStart){
            $dateStart = '-30 days';
        }
        if($dateStart){
            $orderQuery->andFilterCompare('created_at', '>='.strtotime($dateStart));
        }
        if($dateEnd){
            $orderQuery->andFilterCompare('created_at', '<'.strtotime($dateEnd));
        }
        $orderQuery->groupBy('first_child_id');

        $query = (new Query())->select(['u.username as merchant_account', 'o.*']);
        $query->from(['o' => $orderQuery])
            ->leftJoin(User::tableName()." u", "first_child_id = u.id");

        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page-1,
            ],
        ]);

        $records=[];
        foreach ($p->getModels() as $i=>$d){
            $records[$i] = $d;
        }
        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;


        $summery['amount'] = $query->sum('amount');
        $summery['all_status_list'] = [
            [
                'status'=>"_all_",
                'status_str'=>'总计',
                'amount'=>$summery['amount']?$summery['amount']:0,
            ]
        ];

        $data = [
            'data'=>$records,
            'summery'=>$summery,
            "pagination"=>[
                "total" =>  $total,
                "per_page" =>  $perPage,
                "current_page" =>  $page,
                "last_page" =>  $lastPage,
                "from" =>  $from,
                "to" =>  $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 修改下级商户收款费率
     * @return array
     * @throws \power\yii2\exceptions\ParameterValidationExpandException
     */
    public function actionEditChildRate()
    {
        $userObj = Yii::$app->user->identity;
        $mainAccount = $userObj->getMainAccount();
        $merchantId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '商户ID错误');
        $rate = ControllerParameterValidator::getRequestParam($this->allParams, 'rate', null, Macro::CONST_PARAM_TYPE_DECIMAL, '收款费率有错');
        $method_id = ControllerParameterValidator::getRequestParam($this->allParams, 'method_id', null, Macro::CONST_PARAM_TYPE_STRING, '充值类型错误');
        $selfPayment = MerchantRechargeMethod::find()->where(['method_id'=>$method_id,'merchant_id'=>$mainAccount->id])->one();
        if($rate < 0 || $selfPayment->fee_rate == 0 || $rate <  $selfPayment->fee_rate){
            return ResponseHelper::formatOutput(Macro::ERR_ACCOUNT_PAYMENT_RATE, '下级商户收款费率不能低于自己');
        }
        $paymentObj = MerchantRechargeMethod::find()->where(['method_id'=>$method_id,'merchant_id'=>$merchantId])->one();
        if($paymentObj->fee_rate > 0){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '下级商户收款费率已设置');
        }
        $paymentObj->fee_rate = $rate;
        $paymentObj->save();

        //重新计算商户返点等信息
        $merchantPayIfno = UserPaymentInfo::findOne(['user_id'=>$merchantId]);
        $merchantPayIfno->updatePayMethods($mainAccount);

        return ResponseHelper::formatOutput(Macro::SUCCESS,'操作成功');
    }
}