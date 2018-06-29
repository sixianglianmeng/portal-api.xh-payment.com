<?php

namespace app\commands;

use app\common\models\model\AuthItem;
use app\common\models\model\User;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use Yii;
use yii\helpers\ArrayHelper;

/*
 * 权限扫描处理角色授权
 *
 * ./protected/yii scan-all-actions/init-sys-role && ./protected/yii scan-all-actions/scan && ./protected/yii scan-all-actions/scan-menu-in-page &&
  ./protected/yii scan-all-actions/load-role-permissions-from-xls && ./protected/yii scan-all-actions/assign-system-role-to-all-user-by-groupid
 */
class ScanAllActionsController extends \yii\console\Controller
{
    const SYS_ROLES = [
            //角色全名=>[角色描述,xls表中缩写名]
            'admin'=>['超级管理员','ad'],
            'admin_operator'=>['系统财务','ado'],
            'admin_service_lv1'=>['系统普通客服','adc1'],
            'admin_service_lv2'=>['系统高级客服','adc2'],
            'merchant'=>['商户主号','m'],
            'merchant_financial'=>['商户客服','mf'],
            'merchant_service'=>['商户财务','mc'],
            'agent'=>['代理主号','a'],
            'agent_service'=>['代理客服','ac'],
            'agent_financial'=>['代理财务','af'],
            'user_base'=>['用户基本权限','all'],
        ];

    public function init()
    {
        parent::init();

        ini_set("display_errors", 1);
        ini_set('memory_limit', '2048M');
    }

    public function beforeAction($event)
    {
        Yii::info('console process: ' . implode(' ', $_SERVER['argv']));
        return parent::beforeAction($event);
    }

    /**
     * 扫描所有action并写入权限库
     * ./protected/yii scan-all-actions/scan
     */
    public function actionScan()
    {
        $data = array();
        $path = Yii::getAlias("@app/modules/api/controllers/");

        //扫描api action并入库
        $this->searchDir($path, $data);
        $classList = [];
        foreach ($data as $i => $d) {
            $data[$i] = str_replace('//', '/', $data[$i]);
            if (
                substr($d, -14) !== 'Controller.php'
                || substr($d, 0, 4) == 'Base'
                || strpos($d, 'BaseController') !== false
                || strpos($d, 'Base') !== false
            ) {
                echo substr($d, -14) . PHP_EOL;
                unset($data[$i]);
                continue;
            }

            $cls         = 'app\modules\api\controllers\v1' . str_replace(Yii::getAlias("@app/modules/api/controllers/v1"), '', $data[$i]);
            $cls         = str_replace('/', '\\', $cls);
            $cls         = str_replace('.php', '', $cls);
            $classList[] = $cls;
//            echo $cls.PHP_EOL;
        }
        $actions = [];
        $auth    = Yii::$app->authManager;

//        $auth->removeAll();

        $sysRoles = [];
        foreach ($classList as $cls) {
            echo $cls . PHP_EOL;
            $reflection = new \ReflectionClass ($cls);
            $methods    = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $m) {
                if ($m->name == 'actions' || substr($m->name, 0, 6) !== 'action') {
                    continue;
                }

                $doc = $m->getDocComment();
                preg_match("/ \* (.+)\n/", $doc, $comment);

                //获取action的角色权限指派注解
                preg_match('/@roles\s*([^\s]*)/i', $doc, $findRolesInAnnotation);
                $rolesInAnnotation = [];
                if($findRolesInAnnotation){
                    $rolesInAnnotation = explode(',',$findRolesInAnnotation[1]);
                }

                $label  = $comment[1] ?? '-';
                $subCls = str_replace('app\\modules\\api\\controllers\\', '', $m->class);
                $subCls = str_replace('\\', '_', $subCls);

                $controller = strtolower(str_replace('Controller', '', $subCls));
                $action     = strtolower(str_replace('action-', '', $this->humpToLine($m->name)));

                $resouce              = $auth->createPermission("{$controller}_{$action}");
                $resouce->description = $label;
                $auth->remove($resouce);
                $auth->add($resouce);

                if($rolesInAnnotation){
                    foreach ($rolesInAnnotation as $roleName){
                        if(empty($sysRoles[$roleName])){
                            $roleObj = $auth->getRole($roleName);
                        }else{
                            $roleObj = $sysRoles[$roleName];
                        }
                        if (!$auth->hasChild($roleObj, $resouce)) {
                            $auth->addChild($roleObj, $resouce);
                            echo "{$controller}_{$action} asign to $roleName\n";
                        }
                    }
                }

                $actions["{$controller}_{$action}"] = $resouce;

                echo "{$controller}_{$action}: {$label}\n";
            }

        }
    }

    /**
     * 根据用户的group_id赋予对应的权限
     *
     * ./protected/yii scan-all-actions/assign-system-role-to-all-user-by-groupid
     */
    public function actionAssignSystemRoleToAllUserByGroupid()
    {
        $auth              = \Yii::$app->authManager;
        $roles['admin']    = $auth->getRole('admin');
        $roles['agent']    = $auth->getRole('agent');
        $roles['merchant'] = $auth->getRole('merchant');

        $users = User::findAll(['parent_merchant_id'=>0]);
        foreach ($users as $u) {
            echo $u->username.PHP_EOL;
            $roleName = User::getGroupEnStr($u->group_id);
            //必须赋予一级菜单权限，否则下面菜单无法显示
            //            $authorRole = $auth->getRole($roleName);
            $u->setBaseRole();
            $auth->revoke($roles[$roleName], $u->id);
            $auth->assign($roles[$roleName], $u->id);

        }

        $users = User::find()->all();
        foreach ($users as $u) {
            $u->setBaseRole();
        }
        User::delPermissionCache(0);
    }

    /**
     * 初始化系统角色
     *
     * ./protected/yii scan-all-actions/init-sys-role
     */
    public function actionInitSysRole()
    {
        $auth       = Yii::$app->authManager;
//        $auth->removeAll();
        foreach (self::SYS_ROLES as $rk=>$ra){
            $sysRole = $auth->getRole($rk);
            if(!$sysRole){
                $sysRole = $auth->createRole($rk);
                $sysRole->description = $ra[0];
                $auth->add($sysRole);
            }
        }
    }
    /**
     * 根据xls角色权限表载入权限
     *
     * ./protected/yii scan-all-actions/load-role-permissions-from-xls
     */
    public function actionLoadRolePermissionsFromXls()
    {

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(Yii::getAlias("@app/../docs/角色权限对照表.xls"));
        $worksheet = $spreadsheet->getActiveSheet();
        $sysRolePermissions = [];
        foreach ($worksheet->getRowIterator() AS $i=>$row) {
            if($i<4) continue;

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(FALSE); // This loops through all cells,
            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = $cell->getValue();
            }

            if(!empty($cells[1]) && !empty($cells[2]) && !empty($cells[0]))
            {

                $pers = explode(',',strtolower($cells[2]));
                foreach ($pers as $p){
                    empty($sysRolePermissions[$p]) && $sysRolePermissions[$p]=[];
                    $sysRolePermissions[$p][] = $cells[0];
                }
            }
        }

        $auth       = Yii::$app->authManager;
        foreach (self::SYS_ROLES as $rk=>$ra){
            $sysRolePermissions[$rk] =  $sysRolePermissions[$ra[1]];
            unset($sysRolePermissions[$ra[1]]);
        }

//var_dump(array_keys($sysRolePermissions));exit;
        foreach ($sysRolePermissions as $r=>$sr){
            echo $r.' '.json_encode($sr,JSON_UNESCAPED_UNICODE).PHP_EOL;
//            continue;
            $authorRole = $auth->getRole($r);
            if(!$authorRole){
                $authorRole = $auth->createRole($r);
                var_dump($r);
                $authorRole->description = $sysRoles[$r][0];
                $auth->add($authorRole);
            }
            foreach ($sr as $rp){
                $rpObj = $auth->getPermission($rp);
                if(empty($rpObj)){
                    var_dump(['no role '.$rp,$rp]);exit;
                }
                if (!$auth->hasChild($authorRole, $rpObj)) {
                    echo "{$authorRole->name} asign to {$rpObj->name}\n";
                    $auth->addChild($authorRole, $rpObj);
                }else{
                    echo "{$authorRole->name} already has {$rpObj->name}\n";
                }
            }
        }
    }

    /**
     * 根据前端菜单生成权限列表
     *
     * ./protected/yii scan-all-actions/scan-menu-in-page
     *
     * @author bootmall@gmail.com
     */
    public function actionScanMenuInPage()
    {
        ////sublime replace "component": _import\("(.+)"\) -> "component": _import\("$1"\),"view": "$1"
        ///
        $auth       = Yii::$app->authManager;
        $vueActions = $this->getPageMenuJson();

        $roles['admin']    = $auth->getRole('admin');
        $roles['agent']    = $auth->getRole('agent');
        $roles['merchant'] = $auth->getRole('merchant');

        $allActions = [];
        foreach ($vueActions as $v) {
            if(empty($v[0]) || empty($v[2])){
                continue;
            }
            $urlName = $v[0];
            //有父级，添加到父级子权限
            if(!empty($v[6])) {
                $urlName = $v[6].'|'.$v[0];
            }
            if(substr($urlName,0,strlen(AuthItem::VUE_MENU_PREFIX))!=AuthItem::VUE_MENU_PREFIX){
                $urlName=AuthItem::VUE_MENU_PREFIX.$urlName;
            }

            $vuebtn = $auth->getPermission($urlName);
            if (empty($vuebtn)) {
                $vuebtn              = $auth->createPermission($urlName);
                $v[2] = str_replace(['-未完成'],'',$v[2]);
                $vuebtn->description = $v[2];
                // ["my_order", "/order/my_order", "收款订单", "order/list", [], ["admin"]],
                $vuebtn->data        = [
                    'url_name'=>$v[0],
                    'url_path'=>$v[1],
                    'label'=>$v[2],
                    'view_path'=>$v[3],
                    'auth_actions'=>$v[4],
                    'auth_sys_role'=>$v[5],
                ];
                $auth->add($vuebtn);
                $allActions[$urlName] = $vuebtn;

                //权限配置了角色，赋予对应角色此权限
                if (!empty($v[5])) {
                    foreach ($v[5] as $v5) {
                        var_dump($v5);
                        if (!$auth->hasChild($roles[$v5], $vuebtn)) {
                            $auth->addChild($roles[$v5], $vuebtn);
                            echo "{$vuebtn->name} asign to {$roles[$v5]->name}\n";
                        }

                    }
                }

                //有父级，添加到父级子权限

            }

            //不指定页面内权限,直接指定: ./protected/yii scan-all-actions/load-role-permissions-from-xls
            continue;

            $apis = [];
            if (!empty($v[3]) && $v[3] != 'layout/empty') {
                $viewPath = Yii::getAlias("@app/../webapp/src/views/" . $v[3] . ".vue");
//                echo $viewPath.PHP_EOL;
                if (file_exists($viewPath)) {
                    $viewStr = file_get_contents($viewPath);

                    $pattern = "/axios\.post\(('|\")(.*)('|\")/i";
                    preg_match_all($pattern, $viewStr, $match);
                    echo "{$vuebtn->name} {$vuebtn->description}\n";

//                    var_dump($match);
                    if (!empty($match[2])) {

                        foreach ($match[2] as $api) {
                            //filter bug: v1_admin_track_add',{parentId:self.trackForm.id,parentType:'remit
                            if (!preg_match("/^[a-z0-9\-_\/]+$/i", $api)) {
                                continue;
                            }

                            $apis[] = $api;

                        }
                        echo ' api backend:' . json_encode($apis) . "\n";
                    }

                } else {
                    echo ' err cannot find view file:' . $viewPath . "\n";
                }
            }elseif (!empty($v[4])) {
                $apis = $v[4];
            }

            if($apis){
                $vuebtnModel = AuthItem::findOne(['name'=>$vuebtn->name]);
                $data = $vuebtnModel->getData();
                $data['auth_actions']=$data['auth_actions']??[];
                $data['auth_actions'] = ArrayHelper::merge($data['auth_actions'],$apis);
                $vuebtnModel->setData($data);
                $vuebtnModel->save();
            }

            foreach ($apis as $api) {
                $api    = 'v1_' . str_replace('/', '_', substr($api, 1));
                $apiPer = $auth->getPermission($api);
                if (empty($apiPer)) {
                    echo "empty api: {$api}\n";
                } else {
                    $auth->removeChild($vuebtn, $apiPer);
                    $auth->addChild($vuebtn, $apiPer);

                    $allActions[$apiPer->name] = $apiPer;

                    echo "{$apiPer->name} " . json_encode($v[5]) . PHP_EOL;
                    if (!empty($v[5])) {
                        foreach ($v[5] as $v5) {
                            if (!$auth->hasChild($roles[$v5], $apiPer)) {
                                $auth->addChild($roles[$v5], $apiPer);
                                echo "{$apiPer->name} asign to {$apiPer->name}\n";
                            }

                        }
                    }

                }
            }
        }

//        $userAllPermssions = $auth->getChildRoles('admin');
//        var_dump(count($userAllPermssions));
    }

    private function getPageMenuJson()
    {
        //菜单/权限key，访问路径路径，权限名称,路由对应的view页面，路由对应的api地址[],父级菜单
        //如果配置了api地址，则不再扫描view页面中的api地址
        $vueActions = [
            ["vue_login","/login",null,"login/index",[],null],
            [null,"/authredirect",null,"login/authredirect",[],null],
            [null,"/404",null,"errorPage/404",[],null],
            [null,"/empty",null,"layout/empty",[],null],
            ["vue_dashboard","","首页",null,[],null],
            ["vue_dashboard_index","/index","首页","dashboard/index",[],null,"vue_dashboard"],
            ["vue_order","/order","订单管理",null,[],["merchant","agent","admin"]],
            ["vue_my_order","/order/myorder","我的收款","order/mylist",[],["merchant","agent"],"vue_order"],
            ["vue_my_remit","/order/my_remit","我的结算","remit/mylist",[],["merchant","agent"],"vue_order"],
            ["vue_admin_order_list","/order/order","收款订单-部分完成","order/list",[],["admin"],"vue_order"],
            ["vue_admin_remit_list","/order/remit","结算订单-部分完成","remit/list",[],["admin"],"vue_order"],
            ["vue_financial","/financial","我的订单",null,[],["merchant","agent"]],
            ["vue_financial_list","/financial/list","收支明细","financial/list",[],["merchant","agent","admin"],"vue_financial"],
            ["vue_channel","/channel","渠道管理",null,[],["admin"]],
            ["vue_channel_list","/channel/list","渠道管理","admin/channel/list",[],["admin"],"vue_channel"],
            ["vue_channel_account","/channel/account_list","渠道号管理","admin/channel/account",[],["admin"],"vue_channel"],
            ["vue_channel_balance","/channel/balance","渠道号余额-未完成","layout/empty",[],["admin"],"vue_channel"],
            ["vue_single","/add_remit","充提款",null,[],["merchant","agent","admin"]],
            ["vue_add_remit_single","/add_remit/single","逐笔提款","remit/singleremit",[],["merchant","agent"],"vue_single"],
            ["vue_add_remit_batch","/add_remit/batch","批量提款","remit/batchremit",[],["merchant","agent"],"vue_single"],
            ["vue_add_remit_recharge","/add_remit/recharge","在线充值-暂不开发","layout/empty",[],["merchant","agent"],"vue_single"],
            ["vue_trade_statistic","/trade_statistic","统计报表",null,[],["merchant","agent","admin"]],
            ["vue_trade_statistic_index","/trade_statistic/index","代理交易(收款)明细-未完成","layout/empty",[],["admin"],"vue_trade_statistic"],
            ["vue_trade_statistic_finacial","/trade_statistic/finacial","收支统计管理-未完成","layout/empty",[],["admin"],"vue_trade_statistic"],
            ["vue_agent_trade_statistic","/trade_statistic/agent","代理交易明细-未完成","layout/empty",[],["admin"],"vue_trade_statistic"],
            ["vue_channel_account_trade_statistic","/trade_statistic/channel_account","渠道号交易统计-未完成","layout/empty",[],["admin"],"vue_trade_statistic"],
            ["vue_channel_account_trade_profit","/trade_statistic/profit","渠道号利润-未完成","layout/empty",[],["admin"],"vue_trade_statistic"],
            ["vue_account","/account","账户管理",null,[],["merchant","agent","admin"]],
            ["vue_account_index","/account/index","子账号管理","account/child/childlist",[],["merchant","agent","admin"],"vue_account"],
            ["vue_bind_bank","/account/bind_bank","绑定银行卡-暂不开发","layout/empty",[],["merchant","agent"],"vue_account"],
            ["vue_merchant_key","/account/merchant_key","商户Key值","layout/empty",[],["merchant","agent"],"vue_account"],
            ["vue_th2fa","/account/th2fa","安全令牌","layout/empty",[],["merchant","agent","admin"],"vue_account"],
            ["vue_merchant","/merchant","商户管理",null,[],["admin"]],
            ["vue_merchant_add","/merchant/merchant_add","新增商户","admin/user/edit",[],["admin"],"vue_merchant"],
            ["vue_merchant_list","/merchant/list","商户管理","admin/user/list",[],["admin"],"vue_merchant"],
            ["vue_merchant_detail","/merchant/merchant_detail","商户详情","admin/user/detail",[],["admin"],"vue_merchant"],
            ["vue_risk","/admin","风控管理",null,[],["admin"]],
            ["vue_track_add","/admin/track","调单记录","admin/track/list",[],["admin"],"vue_risk"],
            ["vue_operation","/admin/operation","操作记录-未完成","layout/empty",[],["admin"],"vue_risk"],
            ["vue_agent","/agent","下级管理",null,[],["agent"]],
            ["vue_sub_account_add","/agent/sub_account_add","新增商户","account/merchant/account_add",[],["agent"],"vue_agent"],
            ["vue_sub_account_list","/agent/sub_account_list","下级管理","account/merchant/account_list",[],["agent"],"vue_agent"],
            ["vue_sub_account_orders","/agent/sub_account_orders","下级收款订单","account/merchant/account_order",[],["agent"],"vue_agent"],
            ["vue_sub_account_remits","/agent/sub_account_remits","下级结算订单","account/merchant/account_remit",[],["agent"],"vue_agent"],
            ["vue_sub_account_financial","/agent/sub_account_financial","下级收支明细","account/merchant/account_financial",[],["agent"],"vue_agent"],
            ["vue_system","/system","系统管理",null,[],["merchant","agent","admin"]],
            ["vue_auth_permissions","/system/permissions","资源管理","admin/permission/list",[],["admin"],"vue_system"],
            ["vue_auth_roles","/system/roles","角色管理","admin/role/list",[],["admin"],"vue_system"],
            ["vue_auth_assign","/system/assign","角色授权","admin/assign/assign",[],["admin"],"vue_system"],
            ["vue_setting","/system/setting","系统配置","admin/siteConfig/list",[],["admin"],"vue_system"],
            ["vue_notice","/system/notice","系统公告","system/notice",[],["admin"],"vue_system"],
            ["vue_api_log","/system/api_log","接口日志","system/apiLog",[],["admin"],"vue_system"],
            ["vue_user_log","/system/user_log","操作日志","system/userLog",[],["admin"],"vue_system"],
            ["vue_amount_limitation","/amount_limitation","其他",null,[],["merchant","agent"]],
            ["vue_amount_limitation_index","/amount_limitation/limit","限额对照表-未完成","layout/empty",[],["merchant","agent"],"vue_amount_limitation"],
            ["vue_document","/amount_limitation/index","接口文档-未完成","layout/empty",[],["merchant","agent"],"vue_amount_limitation"],
        ];

        return $vueActions;
    }

    private function humpToLine($str)
    {
        $str = preg_replace_callback('/([A-Z]{1})/', function ($matches) {
            return '-' . strtolower($matches[0]);
        }, $str);
        return $str;
    }

    function searchDir($path, &$data)
    {
        if (is_dir($path)) {
            $dp = dir($path);
            while ($file = $dp->read()) {
                if ($file != '.' && $file != '..') {
                    $this->searchDir($path . '/' . $file, $data);
                }
            }
            $dp->close();
        }
        if (is_file($path)) {
            $data[] = $path;
        }
    }
}
