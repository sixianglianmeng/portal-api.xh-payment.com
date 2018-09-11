<?php

namespace app\commands;

use app\common\models\model\ChannelAccount;
use app\common\models\model\MerchantRechargeMethod;
use app\common\models\model\User;
use app\common\models\model\UserPaymentInfo;
use app\components\Util;
use Yii;

class AccountController extends BaseConsoleCommand
{
    public function init()
    {
        parent::init();
    }

    public function beforeAction($event)
    {
        Yii::info('console process: ' . implode(' ', $_SERVER['argv']));
        return parent::beforeAction($event);
    }

    /*
     * 新增超级管理员
     *
     * ./protected/yii account/add-admin admin_username pwd
     */
    public function actionAddAdmin($username,$pwd)
    {
        $user           = new User();
        $user->username = $username;
        $user->nickname = $username;
        $user->email    = '';
        $user->setPassword($pwd);


        $user->parent_agent_id = 0;
        $user->group_id        = User::GROUP_ADMIN;
        $user->status          = User::STATUS_ACTIVE;
        $user->save();

        $userPayment              = new UserPaymentInfo();
        $userPayment->user_id     = $user->id;
        $userPayment->username    = $user->username;
        $userPayment->app_key_md5 = Util::uuid('uuid');
        $userPayment->app_id = $user->id;
        $userPayment->save(false);

        //账户角色授权
        $user->setGroupRole();
    }

    /*
     * 更新超级管理员密码
     *
     * ./protected/yii account/reset-admin-pass admin_username pwd
     */
    public function actionResetAdminPass($username,$pwd)
    {
        $user = User::findOne(['username'=>$username]);
        $user->setPassword($pwd);
        $user->save();
    }

    /*
     * 重置用户组
     *
     * ./protected/yii account/reset-group user 20
     */
    public function actionResetGroup($username,$group)
    {
        $user = User::findOne(['username'=>$username]);
        if(!$user){
            exit('user not find');
        }
        $user->group_id = $group;
        $user->save();

        $user->setGroupRole();
    }

    /**
     * 更新每个用户新增的网银内冲支付方式配置
     */
    public function actionUpdateUserWync(){

        $sql = "select u.parent_agent_id,m.* from p_merchant_recharge_methods m, p_users u where m.merchant_id=u.id and m.method_id='WY' and u.parent_agent_id>0 and u.group_id=30 and m.fee_rate>0";
        //$sql .=" AND u.id=3012186";
        $merchantMethods = Yii::$app->db->createCommand($sql)->queryAll();
        foreach ($merchantMethods as $mm) {
            $_SERVER['LOG_ID'] = strval(uniqid());
            Yii::info($_SERVER['LOG_ID'].' updateUserWync '.$mm['merchant_account'].' '.$mm['fee_rate']);
            $userPaymentInfo = UserPaymentInfo::findOne(['user_id' => $mm['merchant_id']]);
            $parent = User::findOne(['id' => $mm['parent_agent_id']]);

            $mm['method_id'] = 'WYNC';
            $mm['method_name'] = '网银内充';
            $mm['created_at'] = time();
            $mm['updated_at'] =0;

            $methodConfig = MerchantRechargeMethod::find()->where(['method_id'=>$mm['method_id'],'app_id'=>$mm['merchant_id']])->limit(1)->one();
            if($methodConfig){
                continue;
            }
            $methodConfig = new MerchantRechargeMethod();

            unset($mm['id']);
            unset($mm['parent_recharge_rebate_rate']);
            unset($mm['parent_method_config_id']);
            unset($mm['all_parent_method_config_id']);
            $methodConfig->setAttributes($mm,false);
            $methodConfig->save();

            $pm['id'] = 'WYNC';
            $pm['status'] = '1';
            $pm['rate'] = $mm['fee_rate'];
            $payMethods = [$pm];

            $userPaymentInfo->updatePayMethods($parent,$payMethods);
        }
    }
}
