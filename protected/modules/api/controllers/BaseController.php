<?php
namespace app\modules\api\controllers;

use app\common\models\model\SiteConfig;
use app\common\models\model\User;
use app\components\Util;
use Yii;

class BaseController extends \app\components\WebAppController
{
    //基础查询参数，例如查询订单只能查询自己的
    protected $baseFilter = [];

    /**
     * 前置action
     *
     * @author bootmall@gmail.com
     */
    public function beforeAction($action){
        $this->user = Yii::$app->user->identity;
        $parentBeforeAction =  parent::beforeAction($action);
        $remoteIp = Util::getClientIp();

        //所有角色都可以拥有的基础权限
        $baseActions = [
            'v1_user_profile','v1_user_login','v1_user_logout','v1_user_verify-key','v1_user_reset-password',
            'edit-pass','get-google-code','set-google-cod','get-auth-key','edit-auth-key',
            'check-financial'
        ];

        $auth              = \Yii::$app->authManager;
        //登录后校验action权限
        if(!Yii::$app->user->isGuest){
            if(!Yii::$app->user->identity->validLoginIp()){
                Util::throwException(403,'非法IP');
            }
            
            //google令牌是否校验通过
            if($this->action->id != 'verify-key' && !Yii::$app->user->identity->is2faChecked() ){
                Util::throwException(403,'需要进行安全令牌校验，请重新登录并进行校验。');
            }

            if(Yii::$app->user->identity->isAdmin()){
                //校验管理员IP白名单
                $adminIpList = SiteConfig::getAdminIps();
                if($adminIpList && $remoteIp && !in_array($remoteIp, $adminIpList)){
                    Util::throwException(401,'您的IP不在白名单范围');
                }
            }

            //超级管理员角色拥有所有权限
            if(Yii::$app->user->identity->isSuperAdmin()){
                return $parentBeforeAction;
            }

            $controllerID = str_replace('/','_',$this->id);
            $actionID = $this->action->id;


            if(substr($controllerID,0,9)=='v1_admin_'){
                //admin空间下controller仅限管理员用户组访问
                if(Yii::$app->user->identity->group_id !== User::GROUP_ADMIN){
                    Util::throwException(403);
                }
            }

            $permissions = Yii::$app->user->identity->getPermissions(false);
            $permissionName = "{$controllerID}_{$actionID}";
            $permissionRet = in_array($permissionName,$baseActions) || in_array($permissionName,$permissions);
            Yii::info("check permission: ".Yii::$app->user->identity->username." ,{$permissionName}, ". intval($permissionRet));
            if(!$permissionRet){
                Util::throwException(403);
            }
        }

        return $parentBeforeAction;
    }
}