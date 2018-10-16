<?php
namespace app\modules\api\controllers\v1\admin;

use app\common\exceptions\OperationFailureException;
use app\common\models\model\AccountOpenFee;
use app\common\models\model\Channel;
use app\common\models\model\ChannelAccount;
use app\common\models\model\ChannelAccountRechargeMethod;
use app\common\models\model\Financial;
use app\common\models\model\MerchantRechargeMethod;
use app\common\models\model\SiteConfig;
use app\common\models\model\Tag;
use app\common\models\model\TagRelation;
use app\common\models\model\User;
use app\common\models\model\UserPaymentInfo;
use app\components\Macro;
use app\components\RpcPaymentGateway;
use app\components\Util;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use Overtrue\Pinyin\Pinyin;
use power\yii2\exceptions\ParameterValidationExpandException;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Query;
use yii\helpers\ArrayHelper;

class UserController extends BaseController
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
    public function beforeAction($action){
        $ret =  parent::beforeAction($action);

        return $ret;
    }

    /**
     * 新增商户
     */
    public function actionEdit()
    {
        $arrAllParams   = $this->getAllParams();

        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', 0, Macro::CONST_PARAM_TYPE_INT, '用户id错误');
        $username = ControllerParameterValidator::getRequestParam($arrAllParams, 'username',null,Macro::CONST_PARAM_TYPE_USERNAME,'登录账户错误');
//        $password = ControllerParameterValidator::getRequestParam($arrAllParams, 'password','',Macro::CONST_PARAM_TYPE_STRING,'密码必须在6位以上');

        $data['nickname'] = ControllerParameterValidator::getRequestParam($arrAllParams, 'nickname','',Macro::CONST_PARAM_TYPE_STRING,'昵称错误',[0,32]);
        $data['email'] = ControllerParameterValidator::getRequestParam($arrAllParams, 'email','',Macro::CONST_PARAM_TYPE_EMAIL,'email错误',
            User::ARR_STATUS);
        $data['status'] = ControllerParameterValidator::getRequestParam($arrAllParams, 'status',null,Macro::CONST_PARAM_TYPE_ARRAY_HAS_KEY,'状态错误',User::ARR_STATUS);
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
        $data['allow_api_fast_remit'] = ControllerParameterValidator::getRequestParam($this->allParams, 'allow_api_fast_remit',1,Macro::CONST_PARAM_TYPE_INT,'接口结算不需审核错误');
        $data['allow_manual_fast_remit'] = ControllerParameterValidator::getRequestParam($this->allParams, 'allow_manual_fast_remit','',Macro::CONST_PARAM_TYPE_INT,'手工结算不需审核错误');
        $data['account_transfer_fee'] = ControllerParameterValidator::getRequestParam($this->allParams, 'account_transfer_fee','',Macro::CONST_PARAM_TYPE_DECIMAL,'手工结算不需审核错误');
        $data['remit_fee_free_quota'] = ControllerParameterValidator::getRequestParam($this->allParams, 'remit_fee_free_quota','',Macro::CONST_PARAM_TYPE_INT,'手工结算不需审核错误');
        //管理员开的账户均为顶级账户
        $parentAccountName = ControllerParameterValidator::getRequestParam($arrAllParams, 'parentMerchantAccount','',Macro::CONST_PARAM_TYPE_USERNAME,'上级帐号错误');
        //收款和出款通道在通道切换处统一设置
        $channelAccountId = ControllerParameterValidator::getRequestParam($arrAllParams, 'channel',0,Macro::CONST_PARAM_TYPE_INT_GT_ZERO,'收款通道错误');
        $remitChannelAccountId = ControllerParameterValidator::getRequestParam($arrAllParams, 'remit_channel',0,Macro::CONST_PARAM_TYPE_INT_GT_ZERO,'出款通道错误');
        $accountOpenFee = ControllerParameterValidator::getRequestParam($arrAllParams, 'account_open_fee',0,Macro::CONST_PARAM_TYPE_DECIMAL,'开户费设置错误');

        $remitFeeCanBeZero = SiteConfig::cacheGetContent('remit_fee_can_be_zero');
        $rechargeFeeCanBeZero = SiteConfig::cacheGetContent('recharge_fee_can_be_zero');

        if($data['group_id']==User::GROUP_MERCHANT && empty($parentAccountName)){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'商户必须指定上级代理');
        }

        //校验上级商户帐号
        $parentAccount = null;
        if($parentAccountName){
//            $parentAccount = User::findOne(['username'=>$parentAccountName,'status'=>User::STATUS_ACTIVE]);
            $parentAccount = User::find()->where(['username'=>$parentAccountName,'status'=>User::STATUS_ACTIVE])->limit(1)->one();
            if(!$parentAccount){
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'上级帐号不存在或未激活');
            }
        }

        //校验渠道
        if($channelAccountId){
//            $channel = ChannelAccount::findOne(['id'=>$channelAccountId,'status'=>ChannelAccount::STATUS_ACTIVE]);
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
//            $remitChannel = ChannelAccount::findOne(['id'=>$remitChannelAccountId,'status'=>ChannelAccount::STATUS_ACTIVE]);
            $remitChannel = ChannelAccount::find()->where(['id'=>$remitChannelAccountId,'status'=>ChannelAccount::STATUS_ACTIVE])->limit(1)->one();
            if(!$remitChannel){
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'出款渠道不存在或未激活');
            }
        }

        $data['remit_fee_rebate'] = 0;
        $parentMinRate = $parentAccount ? $parentAccount->paymentInfo->payMethods:[];
        if(!$remitFeeCanBeZero && empty($data['remit_fee'])){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'出款费率不能为0');
        }
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
                    }
                }
            }
            $data['pay_method'][$i]['all_parent_method_config_id'] = json_encode($data['pay_method'][$i]['all_parent_method_config_id']);
        }

        $data['remit_fee_rebate'] = 0;
        if ($parentAccount) {
            $parentRemitFee = $parentAccount->paymentInfo->remit_fee;//
            if ($data['remit_fee'] < $parentRemitFee) {
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, "出款手续费不能低于上级");
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
                $user->nickname = $data['nickname'];
                $user->email = $data['email'];
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
            $user->status = $data['status'];
            $user->account_open_fee = $accountOpenFee;
            $user->account_open_fee_status = AccountOpenFee::STATUS_UNPAID;
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
                $userPayment->remit_fee_rebate = $data['remit_fee_rebate'];
            }

            $userPayment->remit_fee = $data['remit_fee'];
    //        $userPayment->pay_methods = json_encode($data['pay_method']);
            $userPayment->recharge_quota_pertime = $data['recharge_quota_pertime'];
            $userPayment->remit_quota_pertime = $data['remit_quota_pertime'];
            $userPayment->allow_api_recharge = $data['allow_api_recharge'];
            $userPayment->allow_manual_recharge = $data['allow_manual_recharge'];
            $userPayment->allow_api_remit = $data['allow_api_remit'];
            $userPayment->allow_manual_remit = $data['allow_manual_remit'];
            $userPayment->allow_api_fast_remit = $data['allow_api_fast_remit']==''?SiteConfig::cacheGetContent('api_fast_remit_quota'):$data['allow_api_fast_remit'];
            $userPayment->allow_manual_fast_remit = $data['allow_manual_fast_remit']==''?SiteConfig::cacheGetContent('manual_fast_remit_quota'):$data['allow_manual_fast_remit'];
            if($data['account_transfer_fee']<0) $data['account_transfer_fee'] = -1;
            $userPayment->account_transfer_fee = $data['account_transfer_fee']==''?SiteConfig::cacheGetContent('account_transfer_fee'):$data['account_transfer_fee'];
            $userPayment->remit_fee_free_quota = $data['remit_fee_free_quota']==''?SiteConfig::cacheGetContent('remit_fee_free_quota'):$data['remit_fee_free_quota'];
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
                $methodConfig->fee_rate = $pm['rate']??0;
                $methodConfig->parent_method_config_id = $pm['parent_method_config_id'];
                $methodConfig->parent_recharge_rebate_rate = $pm['parent_recharge_rebate_rate'];
                $methodConfig->all_parent_method_config_id = $pm['all_parent_method_config_id'];
                $methodConfig->status = ($pm['status']==MerchantRechargeMethod::STATUS_ACTIVE)?MerchantRechargeMethod::STATUS_ACTIVE:MerchantRechargeMethod::STATUS_INACTIVE;
                if(empty($methodConfig->fee_rate) && !$rechargeFeeCanBeZero){
                    $methodConfig->status = MerchantRechargeMethod::STATUS_INACTIVE;
                }
                $methodConfig->settlement_type = $pm['settlement_type']?$pm['settlement_type']:SiteConfig::cacheGetContent('default_settlement_type');

                if($channelAccountId){
                    $methodConfig->channel_account_id = $channel->id;
                    $methodConfig->channel_id = $channel->channel_id;
                    $methodConfig->channel_account_name = $channel->channel_name;
                }
                $methodConfig->save();
            }

            if($accountOpenFee){
                $accountOpenInfo = new AccountOpenFee();
                $accountOpenInfo->user_id = $user->id;
                $accountOpenInfo->username = $user->username;
                $accountOpenInfo->fee = $accountOpenFee;
                $accountOpenInfo->save();
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
     * 商户列表
     */
    public function actionList()
    {

        $user = Yii::$app->user->identity;
        //允许的排序
        $sorts = [
            'created_at-'=>['created_at'=>SORT_DESC],
            'balance-'=>['u.balance'=>SORT_DESC],
            'balance+'=>['u.balance'=>SORT_ASC],
        ];

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15,
            Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE,
            Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1,
            Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100000]);

        if($sort && !empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['u.created_at'=>SORT_DESC];
        }

        //生成查询参数
        $subField = "up.user_id,up.remit_fee,up.channel_account_name,up.channel_account_id,up.remit_channel_account_id,up.remit_channel_account_name";//,m.method_id,m.method_name
        $field = "p.*,u.id,u.username,u.status,u.parent_agent_id,u.group_id,u.frozen_balance,u.balance,u.created_at,u.financial_password_hash,u.key_2fa,u.account_open_fee,u.account_open_fee_status";

        $searchFilter = $this->getSearchFilter($field,$subField);
        $query = $searchFilter['query'];
        $query->orderBy($sort);

        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page-1,
            ],
            'sort' => [
                'defaultOrder' => [
//                    $sort[0] => $sort[1],
//                    'created_at'=>'DESC'
                ]
            ],
        ]);
        $payMethods = Channel::ARR_METHOD;
        //格式化返回记录数据
        $records=[];
        $parentIds = [];
        foreach ($p->getModels() as $i => $u) {
            $records[$i]['id']                 = $u['id'];
            $parentIds[]                       = $u['parent_agent_id'];
            $records[$i]['username']           = $u['username'];
            $records[$i]['parent_agent_id']    = $u['parent_agent_id'];
            $records[$i]['balance']            = $u['balance'] ?? '0';
            $records[$i]['frozen_balance']     = $u['frozen_balance'] ?? '0';
            $records[$i]['status']             = $u['status'];
            $records[$i]['status_str']         = User::getStatusStr($u['status']);
            $records[$i]['account_open_fee_status']         = $u['account_open_fee_status'];
            $records[$i]['account_open_fee_status_str']    = User::ARR_ACCOUNT_OPEN_FEE_STATUS[$u['account_open_fee_status']];
            $records[$i]['account_open_fee']   = $u['account_open_fee'];
            $records[$i]['group_id']           = $u['group_id'];
            $records[$i]['group_str']          = User::getGroupStr($u['group_id']);
            $records[$i]['created_at']         = date('Y-m-d H:i:s', $u['created_at']);
            $records[$i]['pay_channel_id']     = $u['channel_account_id'];
            $records[$i]['pay_channel_name']   = $u['channel_account_name'];
            $records[$i]['remit_channel_id']   = $u['remit_channel_account_id'];
            $records[$i]['remit_channel_name'] = $u['remit_channel_account_name'];
            $merchantPayMethods                = UserPaymentInfo::getPayMethodsArrByAppId($u['id']);// $u->paymentInfo->getPayMethodsArr();
            foreach ($merchantPayMethods as $mpm) {
                $records[$i]['pay_config'][$mpm['id']] = $mpm;
            }

            $records[$i]['remit_fee']              = $u['remit_fee'];
            $records[$i]['financial_password_len'] = strlen($u['financial_password_hash']) > 0 ? 1 : 0;
            $records[$i]['key_2fa_len']            = strlen($u['key_2fa']) > 0 ? 1 : 0;
            $records[$i]['tags']                   = User::getTagsArr($u['id']);
        }
        $parentUserNameOptions = ArrayHelper::map(User::getParentUserName($parentIds), 'id', 'username');

        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;

        //格式化返回json结构
        $data = [
            'data'=>$records,
            'parentUserNameOptions'=>$parentUserNameOptions,
            "pagination"=>[
                "total" =>  $total,
                "per_page" =>  $perPage,
                "current_page" =>  $page,
                "last_page" =>  $lastPage,
                "from" =>  $from,
                "to" =>  $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS,'', $data);
    }

    /**
     * 切换商户收款通道
     *
     */
    public function actionSwitchRechargeChannel()
    {
        $switchPayChannelForm = ControllerParameterValidator::getRequestParam($this->allParams, 'switchPayChannelForm', null,
            Macro::CONST_PARAM_TYPE_ARRAY,'收款通道参数错误');
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'userId', '',
            Macro::CONST_PARAM_TYPE_INT_GT_ZERO,'商户ID错误');
        $username = ControllerParameterValidator::getRequestParam($this->allParams, 'username', '',
            Macro::CONST_PARAM_TYPE_USERNAME,'商户名错误',[0,32]);
        $parentUsername = ControllerParameterValidator::getRequestParam($this->allParams, 'parentUsername', '',
            Macro::CONST_PARAM_TYPE_USERNAME,'商户父帐号错误',[0,32]);

        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status','',
            Macro::CONST_PARAM_TYPE_INT,'状态错误',[0,100]);
        $type = ControllerParameterValidator::getRequestParam($this->allParams, 'type','',
            Macro::CONST_PARAM_TYPE_INT,'账户类型错误',[0,100]);
        $payType = ControllerParameterValidator::getRequestParam($this->allParams, 'payMedhod','',
            Macro::CONST_PARAM_TYPE_INT,'支付类型错误',[0,100]);
        $remit = ControllerParameterValidator::getRequestParam($this->allParams, 'remit','',
            Macro::CONST_PARAM_TYPE_INT,'下发通道错误',[0,100]);
        $channel = ControllerParameterValidator::getRequestParam($this->allParams, 'payChannel','',
            Macro::CONST_PARAM_TYPE_INT,'充值通道错误',[0,100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        if(empty($switchPayChannelForm['payMethodId'])
            || empty($switchPayChannelForm['rechargeChannelId'])
        ){
            throw new OperationFailureException('请选择正确的收款方式和收款通道');
        }
        foreach ($switchPayChannelForm['payMethodId'] as $pmi){
            if(empty(Channel::ARR_METHOD[$pmi])){
                throw new OperationFailureException("请选择正确的收款方式,{$pmi}不存在");
            }
        }

//        || empty(Channel::ARR_METHOD[$switchPayChannelForm['payMethodId']])
        $channelAccount = ChannelAccount::findOne(['id'=>$switchPayChannelForm['rechargeChannelId']]);
        if(!$channelAccount){
            throw new OperationFailureException('选择的收款通道不存在');
        }

//        var_dump($switchPayChannelForm['payMethodId']);
//        exit;
//        $channelAccountMethod = $channelAccount->getPayMethodById($switchPayChannelForm['payMethodId']);
//        if(!$channelAccountMethod){
//            throw new OperationFailureException('选择的收款方式和收款通道不匹配');
//        }

        //生成查询参数
        $searchFilter = $this->getSearchFilter();
        //查询条件不能为空
        if(
            count($searchFilter['subUpdateFilter'])<=1 //默认 1=1
         && count($searchFilter['updateFilter'])<=2 //默认 u.group_id!=10 AND parent_merchant_id=0
        ){
            //Yii::error("切换收款通道条件错误: ".json_encode($this->allParams)." ".json_encode($searchFilter));
            throw new OperationFailureException('商户筛选条件不能为空，请刷新页面重试');
        }
        //添加payMethodId条件是因为一个up.app_id对应多个method_id及一个商户有多个支付方式。
        //为了筛选出只有payMethodId的p_merchant_recharge_methods表记录，防止GROUP BY up.app_id的时候获取到的通道ID不是要切换的，最终导致切换错误。
        $channelStrWillSwitch = implode("','",$switchPayChannelForm['payMethodId']);
        $searchFilter['subUpdateFilter'][] = "m.method_id IN ('{$channelStrWillSwitch}')";
        $updateFilterStr = implode(" AND ",$searchFilter['updateFilter'] );
        $subUpdateFilterStr = implode(" AND ",$searchFilter['subUpdateFilter']);

        $updateSql = "
        UPDATE 
p_merchant_recharge_methods rm,
(
	 SELECT `p`.*, `u`.`id` FROM 
	`p_users` `u` 
	RIGHT JOIN 
	(
	    SELECT m.id as m_id,`up`.`user_id`,`up`.`channel_account_id`, `up`.`remit_channel_account_id`,`m`.`method_id`,m.app_id FROM `p_user_payment_info` `up` 
		LEFT JOIN `p_users` `u` ON u.id = up.user_id 
		LEFT JOIN `p_merchant_recharge_methods` `m` ON up.id = m.payment_info_id 
		LEFT JOIN `p_tag_relations` `t` ON up.app_id = t.object_id 
		WHERE {$subUpdateFilterStr} AND m.payment_info_id IS NOT null GROUP BY up.app_id,m.method_id
	) `p` 
	ON u.id = p.user_id 
	WHERE {$updateFilterStr}
) c 
SET rm.channel_id={$channelAccount->channel_id},rm.channel_account_id={$channelAccount->id},rm.channel_account_name='{$channelAccount->channel_name}' 
WHERE rm.method_id=c.method_id and rm.app_id=c.app_id";

        Yii::$app->db->createCommand($updateSql)->execute();

        return ResponseHelper::formatOutput(Macro::SUCCESS,'');
    }

    /**
     * 生成商户列表页面通用查询条件
     * 某些特殊的
     */
    private function getSearchFilter($queryFileds='',$subQueryFields='')
    {
        $appIds = ControllerParameterValidator::getRequestParam($this->allParams, 'appIds', '',Macro::CONST_PARAM_TYPE_ARRAY, '选择的商户ID错误');
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'userId', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '商户ID错误');
        $username = ControllerParameterValidator::getRequestParam($this->allParams, 'username', '', Macro::CONST_PARAM_TYPE_USERNAME, '商户名错误', [0, 32]);
        $parentUsername = ControllerParameterValidator::getRequestParam($this->allParams, 'parentUsername', '', Macro::CONST_PARAM_TYPE_USERNAME, '商户父帐号错误', [0, 32]);
        $child = ControllerParameterValidator::getRequestParam($this->allParams,'child','',Macro::CONST_PARAM_TYPE_INT,'下级类型错误');
        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status', '', Macro::CONST_PARAM_TYPE_INT, '状态错误', [0, 100]);
        $account_open_fee_status = ControllerParameterValidator::getRequestParam($this->allParams, 'accountOpenFeeStatus', '', Macro::CONST_PARAM_TYPE_INT, '开户费缴纳状态错误', [0, 100]);
        $type = ControllerParameterValidator::getRequestParam($this->allParams, 'type', '',
            Macro::CONST_PARAM_TYPE_INT, '账户类型错误', [0, 100]);
//        $payType = ControllerParameterValidator::getRequestParam($this->allParams, 'payMedhod', '',
//            Macro::CONST_PARAM_TYPE_INT, '支付类型错误', [0, 100]);
        $remit = ControllerParameterValidator::getRequestParam($this->allParams, 'remit', '',
            Macro::CONST_PARAM_TYPE_INT, '下发通道错误', [0, 100]);
        $channel = ControllerParameterValidator::getRequestParam($this->allParams, 'payChannel', '',
            Macro::CONST_PARAM_TYPE_INT, '充值通道错误', [0, 100]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE, '开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE, '结束日期错误');
        $tagId = ControllerParameterValidator::getRequestParam($this->allParams, 'tagId', 0, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '商户分组标签错误');

        //生成查询参数
        $filter = [];

        $field = $subQueryFields?$subQueryFields:"up.user_id,up.channel_account_id,m.method_id";
        $updateFilter = ['u.group_id!='.User::GROUP_ADMIN,'parent_merchant_id=0'];
        $subUpdateFilter = ['1=1'];
        $subQuery = (new \yii\db\Query())
            ->select($field)
            ->from(UserPaymentInfo::tableName() . ' AS up')
            ->leftJoin(MerchantRechargeMethod::tableName() . ' AS m', 'up.id = m.payment_info_id')
            ->leftJoin(TagRelation::tableName().' AS t', 'up.app_id = t.object_id');

        if ($remit != '') {
            $subQuery->andwhere(['up.remit_channel_account_id' => $remit]);
            $subUpdateFilter[] = "up.remit_channel_account_id={$remit}";
        }
        if ($channel != '') {
            $subQuery->andwhere(['m.channel_account_id' => $channel]);
            $subUpdateFilter[] = "m.channel_account_id={$channel}";
        }

//        if ($payType != '') {
//            $subQuery->andwhere(['m.method_id' => $payType]);
//            $subUpdateFilter[] = "m.method_id={$payType}";
//            $updateFilter[] = "p.app_id is not null";
//        }

        if ($tagId) {
            $tagId = intval($tagId);

            $subQuery->andwhere(['t.object_type' => 1]);
            $subQuery->andwhere(['t.tag_id' => $tagId]);
            $subQuery->andwhere(['not', ['t.object_id' => null]]);
            $subUpdateFilter[] = "t.tag_id={$tagId}";
            $subUpdateFilter[] = "t.object_type=1";
            $subUpdateFilter[] = "t.object_id IS NOT NULL";
        }

        $field = $queryFileds?$queryFileds:"p.*,u.id,u.username,u.status,u.parent_agent_id,u.group_id,u.created_at";
        $query = (new \yii\db\Query())
            ->select($field)
            ->from(User::tableName() . ' AS u')
            ->rightJoin(['p' => $subQuery], 'u.id = p.user_id')
            ->where(['not', ['u.group_id' => User::GROUP_ADMIN]]);

        if ($dateStart) {
            $query->andFilterCompare('u.created_at', '>=' . strtotime($dateStart));
            $updateFilter[] = "u.created_at>=" . strtotime($dateStart);
        }
        if ($dateEnd) {
            $query->andFilterCompare('u.created_at', '<' . strtotime($dateEnd));
            $updateFilter[] = "u.created_at<" . strtotime($dateEnd);
        }

        if ($status != '') {
            $query->andwhere(['u.status' => $status]);
            $updateFilter[] = "u.status={$status}";
        }
        if ($account_open_fee_status != '') {
            $query->andwhere(['u.account_open_fee_status' => $account_open_fee_status]);
//            $updateFilter[] = "u.account_open_fee_status={$account_open_fee_status}";
        }

        if ($userId != '') {
            $query->andwhere(['u.id' => $userId]);
            $updateFilter[] = "u.id={$userId}";
            $subUpdateFilter[] = "u.id={$userId}";
        }
        if (!empty($appIds) && is_array($appIds)) {
            foreach ($appIds as $k => $spa) {
                $appIds[$k] = intval($spa);
            }
            $updateFilter[] = "u.id IN (" . implode(',', $appIds) . ")";
            $subUpdateFilter[] = "u.id IN (" . implode(',', $appIds) . ")";
        }
        if ($username != '') {
            $query->andwhere(['u.username' => $username]);
            $updateFilter[] = "u.username='{$username}'";
            $subUpdateFilter[] = "u.username='{$username}'";
        }
        if ($parentUsername != '') {
//            $parent = User::findOne(['username' => $parentUsername]);
            $parent = User::find()->where(['username' => $parentUsername])->limit(1)->one();
            if ($parent) {
                if($child == 0 || empty($child)){
                    $query->andwhere(['u.parent_agent_id' => $parent->id]);
                }elseif ($child == 1){
                    $query->andWhere(['like','u.all_parent_agent_id',$parent->id]);
                }

                $updateFilter[] = "u.parent_agent_id={$parent->id}";
                $subUpdateFilter[] = "u.parent_agent_id={$parent->id}";
            } else {
                $query->andwhere(['u.parent_agent_id' => 0]);
                $updateFilter[] = "u.parent_agent_id=0";
                $subUpdateFilter[] = "u.parent_agent_id=0";
            }
        }

        if ($type != '') {
            $query->andwhere(['u.group_id' => $type]);
            $updateFilter[] = "u.group_id={$type}";
        }
        $query->groupBy('u.id');

        return [
            'query'=>$query,
            'updateFilter'=>$updateFilter,
            'subUpdateFilter'=>$subUpdateFilter,
        ];
    }

    /**
     *
     * 商户表单下拉选项值列表
     */
    public function actionFormOptionList()
    {
        $all = ControllerParameterValidator::getRequestParam($this->allParams, 'all',1,Macro::CONST_PARAM_TYPE_INT,'all参数错误');
        $user = Yii::$app->user->identity;

        $data = [
            'user_status' => empty($all)?User::ARR_STATUS:ArrayHelper::merge(User::ARR_STATUS,[Macro::SELECT_OPTION_ALL=>'全部']),
            'account_open_fee_status' => empty($all)?User::ARR_ACCOUNT_OPEN_FEE_STATUS:ArrayHelper::merge(User::ARR_ACCOUNT_OPEN_FEE_STATUS,[Macro::SELECT_OPTION_ALL=>'全部']),
            'user_type' => empty($all)?User::ARR_GROUP:ArrayHelper::merge([Macro::SELECT_OPTION_ALL=>'全部'],User::ARR_GROUP),
            'pay_method' => empty($all)?Channel::ARR_METHOD:ArrayHelper::merge([Macro::SELECT_OPTION_ALL=>'全部'],Channel::ARR_METHOD),
        ];
        $channelSql = 'SELECT id,channel_name,group_id,group_name FROM '.ChannelAccount::tableName()." WHERE visible=".ChannelAccount::VISIBLE_SHOW;
        $data['all_channel'] = Yii::$app->db->createCommand($channelSql)
        ->queryAll();
        $data['all_channel'] = empty($all)?$data['all_channel']:ArrayHelper::merge([['id'=>Macro::SELECT_OPTION_ALL,'channel_name'=>'全部']],$data['all_channel']);

        $data['channel_can_remit'] = Yii::$app->db->createCommand("{$channelSql} AND status IN(".ChannelAccount::STATUS_ACTIVE.",".ChannelAccount::STATUS_RECHARGE_BANED.")")
            ->queryAll();
        $data['channel_can_remit'] = empty($all)?$data['channel_can_remit']:ArrayHelper::merge([['id'=>Macro::SELECT_OPTION_ALL,'channel_name'=>'全部']],$data['channel_can_remit']);

        $data['channel_can_recharge'] = Yii::$app->db->createCommand("{$channelSql} AND status IN(".ChannelAccount::STATUS_ACTIVE.",".ChannelAccount::STATUS_REMIT_BANED.")")
            ->queryAll();
        $data['channel_can_recharge'] = empty($all)?$data['channel_can_recharge']:ArrayHelper::merge([['id'=>Macro::SELECT_OPTION_ALL,'channel_name'=>'全部']],
            $data['channel_can_recharge']);

        $data['user_type'] = array_filter($data['user_type'], function($v) {
            return $v!=User::ARR_GROUP[User::GROUP_ADMIN];
        });
//        $data['user_type'] = Util::ArrayKeyValToDimetric($data['user_type'],'id','name');
        $data['tags'] =(new Query())->select('id,name,pinyin')->from(Tag::tableName())->all();// Tag::find()->field()->all();
        $data['tags'] = ArrayHelper::merge([['id'=>Macro::SELECT_OPTION_ALL,'name'=>'全部','pinyin'=>'qb']],$data['tags']);

        $data['remitMaxFee'] =  SiteConfig::cacheGetContent('remit_max_fee');
        $data['rechargeMaxRate'] =  SiteConfig::cacheGetContent('recharge_max_rate');
        $data['settlementType'] =  MerchantRechargeMethod::getAllSettlementType();
        $data['remitFeeCanBeZero'] =  SiteConfig::cacheGetContent('remit_fee_can_be_zero');
        $data['rechargeFeeCanBeZero'] =  SiteConfig::cacheGetContent('recharge_fee_can_be_zero');

        return ResponseHelper::formatOutput(Macro::SUCCESS,'', $data);
    }


    /**
     * 支付渠道账户选择列表
     */
    public function actionChannelAccountList()
    {
        $methodId = ControllerParameterValidator::getRequestParam($this->allParams, 'methodId', '', Macro::CONST_PARAM_TYPE_ARRAY, '收款方式错误');

        $accountsQuery = (new \yii\db\Query())->from(ChannelAccountRechargeMethod::tableName().' m')
            ->select(['m.channel_account_id', 'm.channel_account_name'])
            ->leftJoin(ChannelAccount::tableName().' a', 'm.channel_account_id=a.id');

        $remitFeeCanBeZero = SiteConfig::cacheGetContent('remit_fee_can_be_zero');
        $accountsQuery->andWhere(['a.visible'=>ChannelAccount::VISIBLE_SHOW]);
        //为0表示只允许费率大于0的通道
        if($remitFeeCanBeZero=='0'){
            $accountsQuery->andWhere(['>','m.fee_rate','0']);
        }

        if($methodId){
            $accountsQuery->andWhere(['m.method_id'=>$methodId]);
        }
        //多个通道进行筛选
        if(count($methodId)>1){
            $accountsQuery->groupBy('m.channel_account_id');
            $accountsQuery->having(['COUNT(*)' => count($methodId)]);
        }

        $accounts = $accountsQuery->all();
        $data = [];
        foreach($accounts as $ac){
            $data[] = [
                'id'=>$ac['channel_account_id'],
                'name'=>$ac['channel_account_name'],
            ];
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS,'', $data);
    }

    /**
     * 切换商户出款通道
     */
    public function actionSwitchRemitChannel()
    {
        $remitId = ControllerParameterValidator::getRequestParam($this->allParams, 'remitIdSwitchTo', null, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '出款通道错误');

//        $channelAccount = ChannelAccount::findOne(['id'=>$remitId]);
        $channelAccount = ChannelAccount::find()->where(['id'=>$remitId])->limit(1)->one();
        if(!$channelAccount){
            throw new OperationFailureException('选择的收款通道不存在');
        }
        $searchFilter = $this->getSearchFilter();

        $updateFilterStr = implode(" AND ",$searchFilter['updateFilter'] );
        $subUpdateFilterStr = implode(" AND ",$searchFilter['subUpdateFilter']);

        $updateSql = "
        UPDATE 
p_user_payment_info rm,
(
	 SELECT `p`.* FROM 
	`p_users` `u` 
	RIGHT JOIN 
	(
	    SELECT up.id,`up`.`user_id`,`up`.`channel_account_id`, `up`.`remit_channel_account_id`,up.app_id FROM `p_user_payment_info` `up` 
	    LEFT JOIN `p_users` `u` ON u.id = up.user_id 
		LEFT JOIN `p_merchant_recharge_methods` `m` ON up.id = m.payment_info_id 
		LEFT JOIN `p_tag_relations` `t` ON up.app_id = t.object_id 
		WHERE {$subUpdateFilterStr}  GROUP BY up.app_id
	) `p` 
	ON u.id = p.user_id 
	WHERE {$updateFilterStr}
) c 
SET rm.remit_channel_id={$channelAccount->channel_id},rm.remit_channel_account_id={$channelAccount->id},rm.remit_channel_account_name='{$channelAccount->channel_name}' 
WHERE rm.id=c.id";

        Yii::$app->db->createCommand($updateSql)->execute();


        return ResponseHelper::formatOutput(Macro::SUCCESS, '出款通道切换成功');
    }

    /**
     * 切换商户标签
     */
    public function actionSwitchTag()
    {
        $tagIdSwitchTo = ControllerParameterValidator::getRequestParam($this->allParams, 'tagIdSwitchTo', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, 'TAG错误');
        //搜索tagId,旧的tagId
        $oldTagId = ControllerParameterValidator::getRequestParam($this->allParams, 'tagId', 0, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '商户分组标签错误');

//        $tag = Tag::findOne(['id'=>$tagIdSwitchTo]);
        $tag = Tag::find()->where(['id'=>$tagIdSwitchTo])->limit(1)->one();
        if(!$tag){
            throw new OperationFailureException('分组标签不存不在');
        }

        $searchFilter = $this->getSearchFilter();

        $updateFilterStr = implode(" AND ",$searchFilter['updateFilter'] );
        $subUpdateFilterStr = implode(" AND ",$searchFilter['subUpdateFilter']);

        //先删除旧分组
        //有标签选择的情况下先不删除,防止删除之后根据查询条件找不到用户来写新标签
        if(!$oldTagId){
            $deleteSql = "
DELETE FROM p_tag_relations where object_type=1 AND object_id IN
(
	 SELECT `u`.`id` FROM
	`p_users` `u`
	RIGHT JOIN
	(
	    SELECT m.id as m_id,`up`.`user_id`,`up`.`channel_account_id`, `up`.`remit_channel_account_id`,`m`.`method_id`,m.app_id FROM `p_user_payment_info` `up`
		LEFT JOIN `p_merchant_recharge_methods` `m` ON up.id = m.payment_info_id
		LEFT JOIN `p_tag_relations` `t` ON up.app_id = t.object_id 
		LEFT JOIN `p_users` `u` ON u.id = up.user_id 
		WHERE {$subUpdateFilterStr}  GROUP BY up.app_id
	) `p`
	ON u.id = p.user_id
	WHERE {$updateFilterStr}
)";
            Yii::$app->db->createCommand($deleteSql)->execute();
        }


        //再统一添加
        $insertSql = "
INSERT IGNORE p_tag_relations(`tag_id`, `tag_name`, `object_id`, `object_type`) 
	 SELECT {$tag->id},'{$tag->name}',`u`.`id`,1 FROM 
	`p_users` `u` 
	RIGHT JOIN 
	(
	    SELECT m.id as m_id,`up`.`user_id`,`up`.`channel_account_id`, `up`.`remit_channel_account_id`,`m`.`method_id`,m.app_id FROM `p_user_payment_info` `up` 
		LEFT JOIN `p_merchant_recharge_methods` `m` ON up.id = m.payment_info_id 
		LEFT JOIN `p_tag_relations` `t` ON up.app_id = t.object_id 
		LEFT JOIN `p_users` `u` ON u.id = up.user_id 
		WHERE {$subUpdateFilterStr}  GROUP BY up.app_id
	) `p` 
	ON u.id = p.user_id 
	WHERE {$updateFilterStr} 
";
        Yii::$app->db->createCommand($insertSql)->execute();

        if($oldTagId){
            $deleteSql = "DELETE FROM p_tag_relations where object_type=1 AND tag_id={$oldTagId}";
            Yii::$app->db->createCommand($deleteSql)->execute();
        }

        return ResponseHelper::formatOutput(Macro::SUCCESS, '分组切换成功');
    }

    /**
     * 添加商户标签
     */
    public function actionAddTag()
    {
        $tagName = ControllerParameterValidator::getRequestParam($this->allParams, 'tag', '', Macro::CONST_PARAM_TYPE_STRING, 'TAG错误');

        if(preg_match("/^[0-9A-Aa-z\-_\x{4e00}-\x{9fa5}]+$/u",$tagName) === false){
            throw new ParameterValidationExpandException('分组名只能包含数字中英文及_-');
        }

        $tag = Tag::findOne(['name'=>$tagName]);
        if($tag) {
            throw new OperationFailureException('标签已经存在!');
        }

        $tag = new Tag();
        $tag->name = $tagName;
        $pinyin = new Pinyin();
        $pinyinArr = $pinyin->convert($tagName);
        $pinyinStr = '';
        foreach ($pinyinArr as $pn){
            $pinyinStr.=substr($pn,0,1);
        }
        $tag->pinyin = $pinyinStr;
        $tag->save();

        return ResponseHelper::formatOutput(Macro::SUCCESS,'添加成功', ['id'=>$tag->id,"name"=>$tag->name]);
    }

    /**
     * 商户标签列表
     */
    public function actionTagList()
    {
        $tag = ControllerParameterValidator::getRequestParam($this->allParams, 'tag', '', Macro::CONST_PARAM_TYPE_STRING, 'TAG错误');

        if($tag && preg_match("/^[0-9A-Aa-z\-_\x{4e00}-\x{9fa5}]+$/u",$tag) === false){
            throw new ParameterValidationExpandException('分组名只能包含数字中英文及_-');
        }

        $filter = [];
        if($tag){
            $filter[] =['like', 'name', $tag];
        }
        $tags = Tag::find($filter)->all();
        $data = [[Macro::SELECT_OPTION_ALL=>'全部']];
        foreach($tags as $t){
            $data[] = [
                'id'=>$t->id,
                'name'=>$t->name,
            ];
        }

        return ResponseHelper::formatOutput(Macro::SUCCESS,'', $data);
    }

    /**
     * 用户详情
     */
    public function actionDetail()
    {
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId', 0, Macro::CONST_PARAM_TYPE_INT, '商户id错误');
        $username = ControllerParameterValidator::getRequestParam($this->allParams, 'merchant_username', '', Macro::CONST_PARAM_TYPE_USERNAME, '商户名错误', [0, 32]);
//        $user = User::findOne(['id'=>$userId]);
        $query = User::find();
        if($userId != 0 && !empty($userId)){
            $query->andWhere(['id'=>$userId]);
        }
        if(!empty($username)){
            $query->andWhere(['username'=>$username]);
        }
        $user = $query->limit(1)->one();
        if(!$user){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'查看的的商户不存在');
        }
        $filter['user_id'] = $user->id;
//        $paymentInfo = UserPaymentInfo::findOne($filter);
        $paymentInfo = UserPaymentInfo::find()->where($filter)->limit(1)->one();
        $methodOptions = Channel::ARR_METHOD;
        $methods = [];
        foreach ($methodOptions as $key=>$val){
            $methods['name'][$key] = $val;
            $methods['rate'][$key] = 0;
            $methods['status'][$key] = 0;
            $methods['status_name'][$key] = '停用';
        }
        $userInfo['id'] = $user->id;
        $userInfo['username'] = $user->username;
        $userInfo['email'] = $user->email;
        $userInfo['bind_login_ip'] = $user->bind_login_ip;
        $userInfo['created_at'] = date('Y-m-d H:i:s',$user->created_at);
        $rate = UserPaymentInfo::getPayMethodsArrByAppId($user->id);
        $lowerUser = User::find()->where(['parent_agent_id'=>$user->id])->select('id')->asArray()->all();
        $lower_level= [];
        if($lowerUser){
            foreach ($lowerUser as $key => $val){
                $lower_level[$key] = $val['id'];
            }
        }
        $rateSection = [];
        if($lower_level || $user->parent_agent_id ){
            $rateSection = MerchantRechargeMethod::getPayMethodsRateSectionAppId($user->parent_agent_id,$lower_level);
        }
        $methods['min_rate'] = $methods['max_rate'] = [];
//        = $rateSection['parent_rate'] = $rateSection['lower_rate'] = [];
        foreach ($rate as $key => $val){
            $methods['rate'][$val['id']] = $val['rate'];
            $methods['status'][$val['id']] = $val['status'];
            $methods['settlement_type'][$val['id']] = $val['settlement_type'];
            $methods['min_rate'][$val['id']] = 0;
            if($rateSection['parent_rate']){
                $methods['min_rate'][$val['id']] = $rateSection['parent_rate'][$val['id']]??0;
            }
            $methods['max_rate'][$val['id']] = 0;
            if(!empty($rateSection['lower_rate'][$val['id']])){
                $methods['max_rate'][$val['id']] = $rateSection['lower_rate'][$val['id']];
            }
            if($val['status'] == 1){
                $methods['status_name'][$val['id']] = '启用';
            }
        }
        $userInfo['app_server_ips'] = '';
        $userInfo['app_server_domains'] = '';
        if($paymentInfo->app_server_ips){
            $userInfo['app_server_ips'] = implode(';',json_decode($paymentInfo->app_server_ips,true));
        }
        if($paymentInfo->app_server_domains){
            $userInfo['app_server_domains'] = implode(';',json_decode($paymentInfo->app_server_domains,true));
        }
        $userInfo['parent_remit_fee'] = 0;
        $parentRemitFee = UserPaymentInfo::find()->where(['user_id'=>$user->parent_agent_id])->limit(1)->one();
        if($parentRemitFee){
            $userInfo['parent_remit_fee'] = $parentRemitFee->remit_fee;
        }
        $userInfo['lower_remit_fee'] = 0;
        if($lower_level){
            $lowerRemitFee = UserPaymentInfo::find()->where(['in','user_id',$lower_level])->select('min(remit_fee) as remit_fee')->limit(1)->one();
            if($lowerRemitFee){
                $userInfo['lower_remit_fee'] = $lowerRemitFee->remit_fee;
            }
        }
        $userInfo['remit_fee']                   = $paymentInfo->remit_fee;
        $userInfo['channel_account_id']          = $paymentInfo->channel_account_id;
        $userInfo['remit_channel_account_id']    = $paymentInfo->remit_channel_account_id;
        $userInfo['remit_fee']                   = $paymentInfo->remit_fee;
        $userInfo['remit_quota_perday']          = $paymentInfo->remit_quota_perday;
        $userInfo['recharge_quota_perday']       = $paymentInfo->recharge_quota_perday;
        $userInfo['remit_quota_pertime']         = $paymentInfo->remit_quota_pertime;
        $userInfo['recharge_quota_pertime']      = $paymentInfo->recharge_quota_pertime;
        $userInfo['merchant_key']                = $paymentInfo->app_key_md5;
        $userInfo['group_name']                  = User::ARR_GROUP[$user->group_id];
        $userInfo['status_name']                 = User::ARR_STATUS[$user->status];
        $userInfo['status']                      = $user->status;
        $userInfo['is_financial']                = $user->financial_password_hash ? '是' : '否';
        $userInfo['financial_password_hash_len'] = strlen($user->financial_password_hash) > 0 ? 1 : 0;
        $userInfo['is_key_2fa']                  = $user->key_2fa ? '是' : '否';
        $userInfo['key_2fa_len']                 = strlen($user->key_2fa) > 0 ? 1 : 0;
        $userInfo['balance']                     = $user->balance;
        $userInfo['frozen_balance']              = $user->frozen_balance;
        $userInfo['asset']                       = $user->balance + $user->frozen_balance;
        $userInfo['email']                       = $user->email;
        $userInfo['parent_agent_id']             = $user->parent_agent_id;
        $userInfo['agent']                       = '';
        $userInfo['allow_api_fast_remit']        = $paymentInfo->allow_api_fast_remit;
        $userInfo['allow_manual_fast_remit']     = $paymentInfo->allow_manual_fast_remit;
        $userInfo['allow_api_recharge']          = $paymentInfo->allow_api_recharge;
        $userInfo['allow_api_remit']             = $paymentInfo->allow_api_remit;
        $userInfo['allow_manual_recharge']       = $paymentInfo->allow_manual_recharge;
        $userInfo['allow_manual_remit']          = $paymentInfo->allow_manual_remit;
        $userInfo['can_check_remit_status']      = $paymentInfo->can_check_remit_status;
        $userInfo['api_response_rule']           = $paymentInfo->api_response_rule;
        $userInfo['account_transfer_fee']        = $paymentInfo->account_transfer_fee;
        $userInfo['remit_fee_free_quota']        = $paymentInfo->remit_fee_free_quota;
        $userInfo['allow_api_remit_str']         = $paymentInfo->allow_api_remit == 0 ? '否' : '是';
        $userInfo['allow_manual_remit_str']      = $paymentInfo->allow_manual_remit == 0 ? '否' : '是';
        $userInfo['sys_account_transfer_fee']    =  SiteConfig::cacheGetContent('account_transfer_fee');

        //处理代理
        if($user->all_parent_agent_id){
            $parentIds = json_decode($user->all_parent_agent_id,true);
            $query = User::find();
            $query->andWhere(['in','id',$parentIds]);
            $query->orderBy('id asc');
            $agentArr = $query->select('username')->asArray()->all();
            $tmp = [];
            foreach ($agentArr as $key=>$val){
                $tmp[$key] = $val['username'];
            }
            $userInfo['agent'] = implode('->',$tmp);
        }
        //$data['user'] = $user;
        $channelOptions = ArrayHelper::map(ChannelAccount::getALLChannelAccount(), 'id', 'channel_name');
        $agentWhere = [$user->id,$user->parent_agent_id];
        $userLower = User::find();
        $where = [
            'or',
            ['like','all_parent_agent_id',','.$user->id.','],
            ['like','all_parent_agent_id','['.$user->id.']'],
            ['like','all_parent_agent_id','['.$user->id.','],
            ['like','all_parent_agent_id',','.$user->id.']']
        ];
        $userLower->andWhere($where);
        $userLowerInfo = $userLower->asArray()->all();
        if($userLowerInfo){
            foreach ($userLowerInfo as $key => $val){
                array_push($agentWhere,$val['id']);
            }
        }
        $agentOptions = ArrayHelper::map(User::getAgentAll($agentWhere,$methods['rate'],$userInfo['remit_fee']), 'id', 'username');
        $data['channelOptions'] = $channelOptions;
        $data['userStatusOptions'] = User::ARR_STATUS;
        $data['userInfo'] = $userInfo;
        $data['methods'] = $methods;
        $data['payMethodsOptions'] = Channel::ARR_METHOD;
        $data['agentOptions'] = $agentOptions ?? [];
        $data['parentMethods'] = $rateSection['parent_rate'] ?? [];

        $data['remitMaxFee'] =  SiteConfig::cacheGetContent('remit_max_fee');
        $data['rechargeMaxRate'] =  SiteConfig::cacheGetContent('recharge_max_rate');
        $data['settlementType'] =  MerchantRechargeMethod::getAllSettlementType();
        $data['remitFeeCanBeZero'] =  SiteConfig::cacheGetContent('remit_fee_can_be_zero');
        $data['rechargeFeeCanBeZero'] =  SiteConfig::cacheGetContent('recharge_fee_can_be_zero');

        $data['accountOpenAmount'] = $user->accountOpenFeeInfo?$user->accountOpenFeeInfo->fee:0;
        $data['accountOpenPaid'] = $user->accountOpenFeeInfo?$user->accountOpenFeeInfo->fee_paid:0;
        $data['accountOpenInfo'] = $user->accountOpenFeeInfo?$user->accountOpenFeeInfo->toArray():[];
        $data['accountOpenInfo']['status_str'] = $user->accountOpenFeeInfo?AccountOpenFee::getStatusStr($user->accountOpenFeeInfo->status):'-';

        return ResponseHelper::formatOutput(Macro::SUCCESS,'', $data);
    }

    /**
     * 修改商户安全资料
     * 1-清除资金密码 2- 解绑安全令牌 3-修改商户状态 4-修改商户邮箱 4-重置密码
     */
    public function actionClearUnbindUpdate()
    {
        $userObj = Yii::$app->user->identity;
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId', 0, Macro::CONST_PARAM_TYPE_INT, '用户id错误');
        $type = ControllerParameterValidator::getRequestParam($this->allParams,'type',null,Macro::CONST_PARAM_TYPE_INT,'类型错误');
        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status',1,Macro::CONST_PARAM_TYPE_INT,'状态码错误');
        $email = ControllerParameterValidator::getRequestParam($this->allParams, 'email',1,Macro::CONST_PARAM_TYPE_EMAIL,'状态码错误');
        $code = ControllerParameterValidator::getRequestParam($this->allParams, 'googleCode','',Macro::CONST_PARAM_TYPE_INT,'安全令牌错误');
        $user = User::findOne(['id'=>$userId]);
        if(!$user){
            ResponseHelper::formatOutput(Macro::ERR_USER_NOT_FOUND,'用户不存在');
        }
        if($type == 1){
            $user->financial_password_hash = '';
        }elseif ($type ==2){
            if(!$userObj->validateKey2fa($code)){
                throw new OperationFailureException('安全令牌错误',Macro::FAIL);
            }
            $user->key_2fa = '';
            $user->key_2fa_token = '';
        }elseif ($type == 3){
            $user->status = $status;
        }elseif ($type == 4){
            $user->email = $email;
        }elseif($type == 5){
            $user->setDefaultPassword();
        }
        $user->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 修改用户额度
     */
    public function actionUpdateQuota()
    {
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId', 0, Macro::CONST_PARAM_TYPE_INT, '商户id错误');
        $channel_account_id =  ControllerParameterValidator::getRequestParam($this->allParams, 'channel_account_id', 0, Macro::CONST_PARAM_TYPE_INT, '渠道id错误');
        $remit_quota_perday = ControllerParameterValidator::getRequestParam($this->allParams,'remit_quota_perday',0,Macro::CONST_PARAM_TYPE_DECIMAL,'单日提款额度错误');
        $recharge_quota_perday = ControllerParameterValidator::getRequestParam($this->allParams,'recharge_quota_perday',0,Macro::CONST_PARAM_TYPE_DECIMAL,'单日充值额度错误');
        $remit_quota_pertime = ControllerParameterValidator::getRequestParam($this->allParams,'remit_quota_pertime',0,Macro::CONST_PARAM_TYPE_DECIMAL,'单次提款额度错误');
        $recharge_quota_pertime = ControllerParameterValidator::getRequestParam($this->allParams,'recharge_quota_pertime',0,Macro::CONST_PARAM_TYPE_DECIMAL,'单次充值额度错误');

        $paymentInfo = UserPaymentInfo::getUserDefaultPaymentInfo($userId);
        if($paymentInfo->channel_account_id != $channel_account_id){
            return ResponseHelper::formatOutput(Macro::ERR_USER_PAYMENT_INFO_CHANNLE_ACCOUNT_ID,'渠道号有误');
        }

        $paymentInfo->remit_quota_perday = $remit_quota_perday;
        $paymentInfo->recharge_quota_perday = $recharge_quota_perday;
        $paymentInfo->remit_quota_pertime = $remit_quota_pertime;
        $paymentInfo->recharge_quota_pertime = $recharge_quota_pertime;

        $paymentInfo->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 修改用户费率
     */
    public function actionUpdateRate()
    {
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId',0,Macro::CONST_PARAM_TYPE_INT,'商户ID错误');
        $parent_agent_id = ControllerParameterValidator::getRequestParam($this->allParams, 'parent_agent_id',0,Macro::CONST_PARAM_TYPE_INT,'上级用户ID错误');
        $channelId = ControllerParameterValidator::getRequestParam($this->allParams, 'channelId','',Macro::CONST_PARAM_TYPE_STRING,'收款渠道编号错误');
        $remitChannelId = ControllerParameterValidator::getRequestParam($this->allParams, 'remitChannelId','',Macro::CONST_PARAM_TYPE_STRING,'出款渠道编号错误');
        $remit_fee = ControllerParameterValidator::getRequestParam($this->allParams, 'remit_fee','',Macro::CONST_PARAM_TYPE_DECIMAL,'出款手续费错误');
        $pay_methods = ControllerParameterValidator::getRequestParam($this->allParams, 'pay_methods','',Macro::CONST_PARAM_TYPE_ARRAY,'收款续费错误');
        $transferFee = ControllerParameterValidator::getRequestParam($this->allParams, 'account_transfer_fee','',Macro::CONST_PARAM_TYPE_DECIMAL,'转账费错误');

        $user = User::find()->where(['id'=>$userId])->limit(1)->one();
        if(!$user){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'编辑的商户不存在');
        }
        //校验上级商户帐号
        $parentAccount = null;
        if($user->parent_agent_id){
            $parentAccount = User::find()->where(['id'=>$user->parent_agent_id,'status'=>User::STATUS_ACTIVE])->limit(1)->one();
            if(!$parentAccount){
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'上级帐号不存在或未激活');
            }
        }
        //校验渠道
        $channel = null;
        if($channelId){
            $channel = ChannelAccount::find()->where(['id'=>$channelId,'status'=>ChannelAccount::STATUS_ACTIVE])->limit(1)->one();
            if(!$channel){
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'收款渠道帐号不存在或未激活');
            }
            //收款费率不能低于通道最低费率
            $channelMinRate = $channel->getPayMethodsArr();
            foreach ($pay_methods as $i=>$pm){
                if(empty($pm['rate'])){
                    unset($pay_methods[$i]);
                    continue;
                }
                foreach ($channelMinRate as $cmr){
                    if($pm['id']==$cmr->id && $pm['status'] === "1"){
                        if($pm['rate']<$cmr->fee_rate){
                            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,"收款渠道费率不能低于渠道最低费率(".Channel::ARR_METHOD[$pm['id']].":{$cmr->fee_rate})");
                        }
                    }
                }
            }
        }
        //校验出款渠道
        if($remitChannelId){
            $remitChannel = ChannelAccount::find()->where(['id'=>$remitChannelId,'status'=>ChannelAccount::STATUS_ACTIVE])->limit(1)->one();
            if(!$remitChannel){
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'出款渠道不存在或未激活');
            }
        }

        $userPaymentInfo = UserPaymentInfo::find()->where(['user_id'=>$userId,'channel_account_id'=>$channelId])->limit(1)->one();
        if($remit_fee){
            $userPaymentInfo->remit_fee = $remit_fee;
            if ($parentAccount) {
                $userPaymentInfo->remit_fee_rebate = 0;
                $parentRemitFee = $parentAccount->paymentInfo->remit_fee;//
                if ($remit_fee < $parentRemitFee) {
                    return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, "出款手续费不能低于上级");
                }
                $userPaymentInfo->remit_fee_rebate = bcsub($remit_fee, $parentRemitFee, 9);
            }
        }
        if($transferFee!=''){
            if($transferFee<0) $transferFee = -1;
            $userPaymentInfo->account_transfer_fee = $transferFee;
        }
        $userPaymentInfo->save();
        $userPaymentInfo->updatePayMethods($parentAccount,$pay_methods);

        //编辑的为代理,且为主账号时,同步更新直属下级账户费率
        if($user->group_id == User::GROUP_AGENT && $user->isMainAccount()){
//            $children = User::getAllAgentChildren($user->id);
            $children = User::findAll(['parent_agent_id'=>$user->id,'parent_merchant_id'=>0]);
            foreach ($children as $child) {
                if(empty($child->paymentInfo)){
//                    Yii::error("error child paymentInfo:{$child->username}");
                    //bug,没有user payment 信息,重新进行初始化
                    $child->addUserPaymentInfo(['remit_fee'=>$userPaymentInfo->remit_fee]);
                }

                try{
                    $child->paymentInfo->updatePayMethods($user);

                    //更新下级出款费率
                    if ($remit_fee > $child->paymentInfo->remit_fee) {
                        $child->paymentInfo->remit_fee = $remit_fee;
                    }
                    $child->paymentInfo->remit_fee_rebate = bcsub($child->paymentInfo->remit_fee, $remit_fee, 9);
                    $child->paymentInfo->save();
                }catch (\Exception $e){
                    Yii::error("修改用户费率时循环更新下级费率出错:".$e->getMessage());
                }

            }
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }
    /**
     * 绑定商户api接口ip白名单
     */
    public function actionBindIps()
    {
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId',0,Macro::CONST_PARAM_TYPE_INT,'商户ID错误');
        $channelId = ControllerParameterValidator::getRequestParam($this->allParams, 'channelId','',Macro::CONST_PARAM_TYPE_STRING,'渠道编号错误');
        $app_server_ips = ControllerParameterValidator::getRequestParam($this->allParams, 'app_server_ips',null,Macro::CONST_PARAM_TYPE_ARRAY,'API接口IP地址错误');
        $app_server_domains = ControllerParameterValidator::getRequestParam($this->allParams, 'app_server_domains',null,Macro::CONST_PARAM_TYPE_ARRAY,'域名错误');
        $userPaymentInfo = UserPaymentInfo::findOne(['user_id'=>$userId,'channel_account_id'=>$channelId]);

        $userPaymentInfo->app_server_ips = json_encode($app_server_ips);
        $userPaymentInfo->app_server_domains = json_encode($app_server_domains);
        $userPaymentInfo->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 更新商户api接口开关
     */
    public function actionUpdateApi()
    {
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'user_id',0,Macro::CONST_PARAM_TYPE_INT,'商户ID错误');
        $fields = ['allow_api_recharge','allow_api_remit','allow_manual_recharge','allow_manual_remit'];
        $data = [];
        foreach ($fields as $f){
            $data[$f] = ControllerParameterValidator::getRequestParam($this->allParams, $f,null,
                Macro::CONST_PARAM_TYPE_ENUM,'api开关参数错误',[0,1]);
        }
        $data['allow_api_fast_remit'] = ControllerParameterValidator::getRequestParam($this->allParams, 'allow_api_fast_remit','',Macro::CONST_PARAM_TYPE_INT,'接口结算不需审核错误');
        $data['allow_manual_fast_remit'] = ControllerParameterValidator::getRequestParam($this->allParams, 'allow_manual_fast_remit','',Macro::CONST_PARAM_TYPE_INT,'手工结算不需审核错误');
        $data['remit_fee_free_quota'] = ControllerParameterValidator::getRequestParam($this->allParams, 'remit_fee_free_quota','',Macro::CONST_PARAM_TYPE_INT,'免出款费额度错误');

        $userPaymentInfo = UserPaymentInfo::getUserDefaultPaymentInfo($userId);
        if(!$userPaymentInfo){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'商户支付配置不存在');
        }
        $userPaymentInfo->setAttributes($data,false);
        $userPaymentInfo->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 切换上级代理
     */
    public function actionChangeAgent()
    {
        $merchantId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId',0,Macro::CONST_PARAM_TYPE_INT,'商户ID错误');
        $agentId = ControllerParameterValidator::getRequestParam($this->allParams, 'agentId',0,Macro::CONST_PARAM_TYPE_INT,'代理ID错误');
        $pay_methods = ControllerParameterValidator::getRequestParam($this->allParams, 'pay_methods','',Macro::CONST_PARAM_TYPE_ARRAY,'收款续费错误');
        $remit_fee = ControllerParameterValidator::getRequestParam($this->allParams, 'remit_fee','',Macro::CONST_PARAM_TYPE_DECIMAL,'出款手续费错误');
        $channelId = ControllerParameterValidator::getRequestParam($this->allParams, 'channelId','',Macro::CONST_PARAM_TYPE_STRING,'收款渠道编号错误');
        $merchant = User::findOne(['id'=>$merchantId]);
        if(!$merchant){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'切换代理的商户不存在');
        }
        $agent = User::findOne(['id'=>$agentId]);
        if(!$agent){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'代理不存在');
        }
        $merchant->parent_agent_id = $agent->id;
        if(empty($agent->all_parent_agent_id)){
            $merchant->all_parent_agent_id = json_encode([$agent->id]);
        }else{
            $all_parent_agent_id = json_decode($agent->all_parent_agent_id,true);
            array_push($all_parent_agent_id,$agent->id);
            $merchant->all_parent_agent_id = json_encode($all_parent_agent_id);
        }
        $merchant->save();
        $userPaymentInfo = $merchant->paymentInfo;
        if($remit_fee && $agent){
            $userPaymentInfo->remit_fee_rebate = 0;
            $parentRemitFee = $agent->paymentInfo->remit_fee;//
            if ($remit_fee < $parentRemitFee) {
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, "出款手续费不能低于上级");
            }
            $userPaymentInfo->remit_fee_rebate = bcsub($remit_fee, $parentRemitFee, 9);
            $userPaymentInfo->save();
        }
        $parentAccount = User::find()->where(['id'=>$merchant->parent_agent_id])->limit(1)->one();
        $parentMinRate = $parentAccount?$parentAccount->paymentInfo->payMethods:[];
        foreach ($pay_methods as $i => $pm) {
            $pay_methods[$i]['parent_method_config_id']     = 0;
            $pay_methods[$i]['parent_recharge_rebate_rate'] = 0;
            $pay_methods[$i]['all_parent_method_config_id'] = [];
            if($parentMinRate){
                foreach ($parentMinRate as $k => $cmr) {
                    if ($pm['id'] == $cmr->method_id && $pm['status'] == '1') {
                        if ($pm['rate'] < $cmr->fee_rate) {
                            throw new OperationFailureException("收款渠道费率不能低于上级费率(" . Channel::ARR_METHOD[$pm['id']] . ":{$cmr->fee_rate})");
                        }
                        //提前计算好需要给上级的分润比例
                        $allMids = [];
                        $pay_methods[$i]['parent_method_config_id']     = $cmr->id;
                        $pay_methods[$i]['parent_recharge_rebate_rate'] = bcsub($pm['rate'], $cmr->fee_rate, 9);
                        if($cmr->all_parent_method_config_id && $cmr->all_parent_method_config_id != 'null'){
                            $allMids = json_decode($cmr->all_parent_method_config_id, true);
                        }
                        array_push($allMids, $cmr->id);
                        $pay_methods[$i]['all_parent_method_config_id'] = $allMids;
                    }
                }
            }
            $pay_methods[$i]['all_parent_method_config_id'] = json_encode($pay_methods[$i]['all_parent_method_config_id']);
        }

        //批量写入每种支付类型配置
        foreach ($pay_methods as $i=>$pm){
            $methodConfig = MerchantRechargeMethod::find()->where(['method_id'=>$pm['id'],'app_id'=>$merchant->id])->limit(1)->one();
            $methodConfig->payment_info_id = $userPaymentInfo->id;
            $methodConfig->parent_method_config_id = $pm['parent_method_config_id'];
            $methodConfig->all_parent_method_config_id = $pm['all_parent_method_config_id'];
            $methodConfig->parent_recharge_rebate_rate = $pm['parent_recharge_rebate_rate'];
            $methodConfig->status = ($pm['status']==MerchantRechargeMethod::STATUS_ACTIVE)?MerchantRechargeMethod::STATUS_ACTIVE:MerchantRechargeMethod::STATUS_INACTIVE;
            $methodConfig->fee_rate = $pm['rate'];
            $methodConfig->save();
        }
        $userQuery = User::find();
        $where = [
            'or',
            ['like','all_parent_agent_id',','.$merchant->id.','],
            ['like','all_parent_agent_id','['.$merchant->id.']'],
            ['like','all_parent_agent_id','['.$merchant->id.','],
            ['like','all_parent_agent_id',','.$merchant->id.']']
        ];
        $userQuery->andWhere($where);
//        $userQuery->andWhere(['like','all_parent_agent_id',','.$merchant->id.',']);
//        $userQuery->orWhere(['like','all_parent_agent_id','['.$merchant->id.']']);
//        $userQuery->orWhere(['like','all_parent_agent_id','['.$merchant->id.',']);
//        $userQuery->orWhere(['like','all_parent_agent_id',','.$merchant->id.']']);
        $userInfo = $userQuery->asArray()->all();
        if($userInfo){
            $merchant_all_parent_agent_id = explode(']',$merchant->all_parent_agent_id);
            foreach ($userInfo as $key => $val){
                $tmp = explode($merchant->id,$val['all_parent_agent_id']);
                $user = User::findOne(['id'=>$val['id']]);
                $user->all_parent_agent_id = str_replace($tmp[0],$merchant_all_parent_agent_id[0].',',$val['all_parent_agent_id']);
                $user->save();
            }
        }
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 修改用户余额
     * @role admin
     */
    public function actionChangeBalance()
    {
        $userObj = Yii::$app->user->identity;
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId', null, Macro::CONST_PARAM_TYPE_INT, '用户id错误');
        $amount = ControllerParameterValidator::getRequestParam($this->allParams, 'amount',null,Macro::CONST_PARAM_TYPE_DECIMAL,'金额错误');
        $bak = ControllerParameterValidator::getRequestParam($this->allParams, 'bak',null,Macro::CONST_PARAM_TYPE_STRING,'调整原因错误',[1]);
        $balanceType = ControllerParameterValidator::getRequestParam($this->allParams, 'type',null,Macro::CONST_PARAM_TYPE_ENUM,'金额类型错误',[1,2]);
        $code = ControllerParameterValidator::getRequestParam($this->allParams, 'googleCode','',Macro::CONST_PARAM_TYPE_INT,'安全令牌错误');

        if(!$userObj->validateKey2fa($code)){
            throw new OperationFailureException('安全令牌错误',Macro::FAIL);
        }
        $user = User::findOne(['id'=>$userId]);
        if(!$user){
            return ResponseHelper::formatOutput(Macro::ERR_USER_NOT_FOUND,'用户不存在');
        }
        //接口日志埋点
        Yii::$app->params['operationLogFields'] = [
            'table'=>'p_users',
            'pk'=>$user->id,
            'order_no'=>$user->id,
        ];

        $data = [
            'user_id'=>$user->id,
            'amount'=>$amount,
            'bak'=>$bak,
            'type'=>$balanceType,
        ];

        $ret = RpcPaymentGateway::call('/account/change-balance', $data);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '解冻成功');
    }

    /**
     * 设置商户开户费
     * @role admin
     */
    public function actionSetAccountOpenFee()
    {
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId', null, Macro::CONST_PARAM_TYPE_INT, '用户id错误');
        $accountOpenFee = ControllerParameterValidator::getRequestParam($this->allParams, 'amount',null,Macro::CONST_PARAM_TYPE_DECIMAL,'开户费设置错误');
        $user = User::findOne(['id'=>$userId]);
        if(!$user){
            return ResponseHelper::formatOutput(Macro::ERR_USER_NOT_FOUND,'用户不存在');
        }
        if($user->account_open_fee_status == AccountOpenFee::STATUS_PAID){
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN,'开户费已缴纳，不能再编辑');
        }
        $user->status = User::STATUS_ACTIVE;
        $user->account_open_fee = $accountOpenFee;
        $user->account_open_fee_status = AccountOpenFee::STATUS_UNPAID;
        $user->save();
        $accountOpenInfo = AccountOpenFee::findOne(['user_id'=>$userId]);
        if(!$accountOpenInfo){
            $accountOpenInfo = new AccountOpenFee();
        }
        $accountOpenInfo->user_id = $user->id;
        $accountOpenInfo->username = $user->username;
        $accountOpenInfo->fee = $accountOpenFee;
        $accountOpenInfo->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS, '设置成功');
    }

    /**
     * 绑定商户登录ip
     *
     * @role admin
     */
    public function actionBindLoginIp()
    {
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId',0,Macro::CONST_PARAM_TYPE_INT,'商户ID错误');
        $ip = ControllerParameterValidator::getRequestParam($this->allParams, 'ip',[],Macro::CONST_PARAM_TYPE_ARRAY,'API接口IP地址错误');
        $user = User::findOne(['id'=>$userId]);
        if(!$user){
            return ResponseHelper::formatOutput(Macro::ERR_USER_NOT_FOUND,'用户不存在');
        }
        if(!empty($ip)) $ip = json_encode($ip);
        $user->bind_login_ip = $ip;
        $user->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 设置是否支持商户审核出款
     *
     * @role admin
     */
    public function actionSetMerchantRemitCheck()
    {
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId',0,Macro::CONST_PARAM_TYPE_INT,'商户ID错误');
        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status',null,Macro::CONST_PARAM_TYPE_ENUM,'商户审核出款状态错误',[0,1]);
        $user = User::findOne(['id'=>$userId]);
        if(!$user){
            return ResponseHelper::formatOutput(Macro::ERR_USER_NOT_FOUND,'用户不存在');
        }

        $user->paymentInfo->can_check_remit_status = $status;
        $user->paymentInfo->save();

        $auth       = Yii::$app->authManager;
        $role = $auth->getRole('merchant_check_remit');
        if($status == 1){
            try{
                $auth->assign($role, $user->id);
            }catch (\Exception $e){
//                Yii::error("error assign merchant_check_remit: ".$e->getMessage());
            }
        }else{
            $auth->revoke($role, $this->id);
        }
        User::delPermissionCache($user->id);

        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 设置商户API响应格式
     *
     * @role admin
     */
    public function actionSetApiResponseFormat()
    {
        $userId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchantId',0,Macro::CONST_PARAM_TYPE_INT,'商户ID错误');
        $rule = ControllerParameterValidator::getRequestParam($this->allParams, 'rule',null,Macro::CONST_PARAM_TYPE_STRING,'响应格式错误',[10]);
        $user = User::findOne(['id'=>$userId]);
        if(!$user){
            return ResponseHelper::formatOutput(Macro::ERR_USER_NOT_FOUND,'用户不存在');
        }
        $ruleArr = json_decode($rule,true);
        if(empty($ruleArr['method']) || empty($ruleArr['format'])){
            return ResponseHelper::formatOutput(Macro::ERR_USER_NOT_FOUND,'响应格式数据配置错误');
        }

        $user->paymentInfo->api_response_rule = $rule;
        $user->paymentInfo->save();

        Yii::$app->cache->set('api_response_rule:'.$user->id,$rule);

        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 代理列表
     * @return array
     */
    public function actionGetAgentList()
    {
        $list = User::find()->select('id,username')->where(['group_id'=>User::GROUP_AGENT])->all();
        return ResponseHelper::formatOutput(Macro::SUCCESS,'',$list);
    }
}
