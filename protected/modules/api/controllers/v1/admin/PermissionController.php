<?php
namespace app\modules\api\controllers\v1\admin;

use app\common\models\model\AuthItem;
use app\common\models\model\User;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;

class PermissionController extends BaseController
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
     * 权限列表
     */
    public function actionList()
    {

        $user = Yii::$app->user->identity;
        //允许的排序
        $sorts = [
            'created_at-'=>['created_at',SORT_DESC],
        ];

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 20, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100000]);

        $name = ControllerParameterValidator::getRequestParam($this->allParams, 'name', '',Macro::CONST_PARAM_TYPE_STRING,'名称错误',[1,32]);
        $desc = ControllerParameterValidator::getRequestParam($this->allParams, 'description', '',Macro::CONST_PARAM_TYPE_STRING,'描述错误',[1,32]);
        $type = ControllerParameterValidator::getRequestParam($this->allParams, 'type', '',Macro::CONST_PARAM_TYPE_INT,'类型错误',[1,32]);

        if($sort && !empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['created_at',SORT_DESC];
        }

        //生成查询参数
        $filter = $this->baseFilter;
        //select i.*,count(*) as children_num from p_auth_item i left join `p_auth_item_child` c on i.name=c.parent where i.type=2 group by i.name having count(*)>1
        $query = $query = (new \yii\db\Query())
            ->select("i.*,count(*) as children_num")
            ->from('p_auth_item AS i')
            ->leftJoin('p_auth_item_child AS c',"i.name=c.parent")
            ->where(['i.type'=>2])
            ->orderBy('i.name ASC')
            ->groupBy('i.name');

        if($name!=''){
            $query->andwhere(['like', 'i.name', "%{$name}%",false]);//['p.pay_methods' => $payType]);
        }
        if($desc!=''){
            $query->andwhere(['like', 'i.description', "%{$desc}%",false]);//['p.pay_methods' => $payType]);
        }
        if($type==1){
            $query->andwhere(['NOT like', 'i.name', "vue_action_%",false]);//['p.pay_methods' => $payType]);
        }
        if($type==2){
            $query->andwhere(['like', 'i.name', 'vue_action_%',false]);//['p.pay_methods' => $payType]);
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
//                    $sort[0] => $sort[1],
                ]
            ],
        ]);

        //格式化返回记录数据
        $records=[];
        foreach ($p->getModels() as $i=>$u){

            if($u['data']){
                $data = unserialize($u['data']);
                unset($u['data']);
                $u = ArrayHelper::merge($data,$u);
            }
            $records[$i] = $u;
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
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 扫描webapp中菜单并生成权限
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
            $urlName=AuthItem::VUE_MENU_PREFIX.$urlName;
            $vuebtn = $auth->getPermission($urlName);
            if (empty($vuebtn)) {
                $vuebtn              = $auth->createPermission($urlName);
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
                        if (!$auth->hasChild($roles[$v5], $vuebtn)) {
                            $auth->addChild($roles[$v5], $vuebtn);
                            Yii::info( "{$vuebtn->name} asign to {$roles[$v5]->name}");
                        }

                    }
                }

                //有父级，添加到父级子权限

            }

            $apis = [];
            if (!empty($v[3]) && $v[3] != 'layout/empty') {
                $viewPath = Yii::getAlias("@app/../webapp/src/views/" . $v[3] . ".vue");
//                echo $viewPath.PHP_EOL;
                if (file_exists($viewPath)) {
                    $viewStr = file_get_contents($viewPath);

                    $pattern = "/axios\.post\(('|\")(.*)('|\")/i";
                    preg_match_all($pattern, $viewStr, $match);
                    Yii::info( "{$vuebtn->name} {$vuebtn->description}");

//                    var_dump($match);
                    if (!empty($match[2])) {

                        foreach ($match[2] as $api) {
                            //filter bug: v1_admin_track_add',{parentId:self.trackForm.id,parentType:'remit
                            if (!preg_match("/^[a-z0-9\-_\/]+$/i", $api)) {
                                continue;
                            }

                            $apis[] = $api;

                        }
                        Yii::info( ' api backend:' . json_encode($apis) );
                    }

                } else {
                    Yii::info( ' err cannot find view file:' . $viewPath . "");
                }
            }elseif (!empty($v[4])) {
                $apis = $v[4];
            }

            if($apis){
//                $vuebtnModel = AuthItem::findOne(['name'=>$vuebtn->name]);
                $vuebtnModel = AuthItem::find()->where(['name'=>$vuebtn->name])->limit(1)->one();
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
                    Yii::info( "empty api: {$api}");
                } else {
                    $auth->removeChild($vuebtn, $apiPer);
                    $auth->addChild($vuebtn, $apiPer);

                    $allActions[$apiPer->name] = $apiPer;

                    Yii::info( "{$apiPer->name} " . json_encode($v[5]) );
                    if (!empty($v[5])) {
                        foreach ($v[5] as $v5) {
                            if (!$auth->hasChild($roles[$v5], $apiPer)) {
                                $auth->addChild($roles[$v5], $apiPer);
                                Yii::info( "{$apiPer->name} asign to {$apiPer->name}");
                            }

                        }
                    }

                }
            }
        }

//        $userAllPermssions = $auth->getChildRoles('admin');
    }

    /**
     * 刷新权限列表
     */
    public function actionRefresh()
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
                unset($data[$i]);
                continue;
            }

            $cls         = 'app\modules\api\controllers\v1' . str_replace(Yii::getAlias("@app/modules/api/controllers/v1"), '', $data[$i]);
            $cls         = str_replace('/', '\\', $cls);
            $cls         = str_replace('.php', '', $cls);
            $classList[] = $cls;
        }
        $actions = [];
        $auth    = Yii::$app->authManager;

        $sysRoles = [];
        foreach ($classList as $cls) {
//            echo $cls . PHP_EOL;
            $reflection = new \ReflectionClass ($cls);
            $methods    = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $m) {
                if ($m->name == 'actions' || substr($m->name, 0, 6) !== 'action') {
                    continue;
                }

                //获取action注释
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

//                $resouce = $auth->getPermission("{$controller}_{$action}");
                $resouce              = $auth->createPermission("{$controller}_{$action}");
                $resouce->description = $label;
                if($auth->getPermission("{$controller}_{$action}")){
                    $auth->update("{$controller}_{$action}", $resouce);
                }else{
                    $auth->add($resouce);
                }

                //创建与resource同名角色并绑定resource,达到用户可以直接绑定resource的目的
//                $roleObj = $auth->getRole("r_".$resouce->name);
//                if(!$roleObj){
//                    $resouceRole = $auth->createRole("r_".$resouce->name);
//                    $resouceRole->description = $label;
//                    $auth->add($resouceRole);
//                    $auth->addChild($resouceRole, $resouce);
//                }

                if($rolesInAnnotation){
                    foreach ($rolesInAnnotation as $roleName){
                        if(empty($sysRoles[$roleName])){
                            $roleObj = $auth->getRole($roleName);
                        }else{
                            $roleObj = $sysRoles[$roleName];
                        }
                        if (!$auth->hasChild($roleObj, $resouce)) {
                            $auth->addChild($roleObj, $resouce);
                            Yii::info("{$controller}_{$action} asign to $roleName");
                        }
                    }
                }

                $actions["{$controller}_{$action}"] = $resouce;
                Yii::info( "{$controller}_{$action}: {$label}");
            }

        }

        //清除用户的权限缓存列表
        User::delPermissionCache(0);

        return ResponseHelper::formatOutput(Macro::SUCCESS, '刷新成功');
    }


    private function humpToLine($str)
    {
        $str = preg_replace_callback('/([A-Z]{1})/', function ($matches) {
            return '-' . strtolower($matches[0]);
        }, $str);
        return $str;
    }

    private function searchDir($path, &$data)
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
