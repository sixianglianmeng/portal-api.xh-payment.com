<?php

namespace app\commands;

use app\common\models\model\ChannelAccount;
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
}
