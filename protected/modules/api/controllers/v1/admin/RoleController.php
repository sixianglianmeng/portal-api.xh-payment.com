<?php
namespace app\modules\api\controllers\v1\admin;

use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;

class RoleController extends BaseController
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
     * 权限列表
     */
    public function actionList()
    {

        $user = Yii::$app->user->identity;
        //允许的排序
        $sorts = [
            'created_at-' => ['created_at', SORT_DESC],
        ];

        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $name = ControllerParameterValidator::getRequestParam($this->allParams, 'name', '', Macro::CONST_PARAM_TYPE_STRING, '名称错误', [1, 32]);
        $desc = ControllerParameterValidator::getRequestParam($this->allParams, 'description', '', Macro::CONST_PARAM_TYPE_STRING, '描述错误', [1, 32]);

        if ($sort && !empty($sorts[$sort])) {
            $sort = $sorts[$sort];
        } else {
            $sort = ['created_at', SORT_DESC];
        }

        //生成查询参数
        $filter = $this->baseFilter;
        //select i.*,count(*) as children_num from p_auth_item i left join `p_auth_item_child` c on i.name=c.parent where i.type=2 group by i.name having count(*)>1
        $query = $query = (new \yii\db\Query())
            ->select("i.*,count(*) as children_num")
            ->from('p_auth_item AS i')
            ->leftJoin('p_auth_item_child AS c', "i.name=c.parent")
            ->where(['i.type' => 1])
            ->orderBy('i.name ASC')
            ->groupBy('i.name');

        if ($name != '') {
            $query->andwhere(['like', 'i.name', "%{$name}%"]);//['p.pay_methods' => $payType]);
        }
        if ($desc != '') {
            $query->andwhere(['like', 'i.description', "%{$desc}%"]);//['p.pay_methods' => $payType]);
        }
        //生成分页数据
        $p = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page'     => $page - 1,
            ],
            'sort'       => [
                'defaultOrder' => [
//                    $sort[0] => $sort[1],
                ]
            ],
        ]);

        //格式化返回记录数据
        $records = [];
        foreach ($p->getModels() as $i => $u) {

            if ($u['data']) {
                $data = unserialize($u['data']);
                unset($u['data']);
                $u = ArrayHelper::merge($data, $u);
            }
            $records[$i] = $u;
        }

        //分页数据
        $pagination = $p->getPagination();
        $total      = $pagination->totalCount;
        $lastPage   = ceil($pagination->totalCount / $perPage);
        $from       = ($page - 1) * $perPage;
        $to         = $page * $perPage;

        //格式化返回json结构
        $data = [
            'data'       => $records,
            "pagination" => [
                "total"        => $total,
                "per_page"     => $perPage,
                "current_page" => $page,
                "last_page"    => $lastPage,
                "from"         => $from,
                "to"           => $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }


    /**
    * 角色权限列表
    */
    public function actionPermissions()
    {

        $user = Yii::$app->user->identity;
        //允许的排序
        $sorts = [
            'created_at-' => ['created_at', SORT_DESC],
        ];

        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $name = ControllerParameterValidator::getRequestParam($this->allParams, 'name', null, Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE, '名称错误', [1, 64]);

        $auth = \Yii::$app->authManager;

        $role = $auth->getRole($name);
        if(!$role){
            throw new \Exception('角色不存在');
        }
        $data = [];
        $data['role'] = [
            'name'=>$role->name,
            'description'=>$role->description,
        ];
        $userAllPermssions = $auth->getPermissionsByRole($name);
        $data['rolePermissions'] = [];
        foreach($userAllPermssions as $up){
            $data['rolePermissions'][] = $up->name;
        }

        $allPermssions = $query = (new \yii\db\Query())
            ->select("i.name,i.description,c.`parent`")
            ->from('p_auth_item AS i')
            ->leftJoin('p_auth_item_child AS c', "i.name=c.parent")
            ->where(['i.type' =>2])
            ->all();

        $treePermissions = [];
        $newPermissions = [];
        foreach ($allPermssions as $i=>$p){
            $parentPos = strpos($p['name'],'|');
            $p['parent_pos'] = $parentPos;
            $p['parent_menu'] = '';
            if($parentPos!==false){
                $p['parent_menu'] = substr($p['name'],0,$parentPos);
//                $p['name'] = AuthItem::VUE_MENU_PREFIX.substr($p['name'],$parentPos+1);
            }else{
                $treePermissions[$p['name']] = $p;
            }

            $newPermissions[$p['name']] = $p;
        }

        foreach ($newPermissions as $i=>$p){
            $p['children_num'] = 0;
            if(!empty($p['parent_menu'])){
                $treePermissions[$p['parent_menu']]['children'][] = $p;
                $treePermissions[$p['parent_menu']]['children_num']+=1;
            }else{

                $treePermissions[$p['name']] = $p;
            }
        }

        $singlePermissions = $data['allPermissions'] = [];
        foreach ($treePermissions as $i=>$p){
            if(empty($p['children'])){
                $singlePermissions[] = $p;
                unset($treePermissions[$i]);
            }else{
                $data['allPermissions'][] = $p;
            }
        }
        $data['allPermissions'][] = [
            'name'=>'other_single',
            'description'=>'其它',
            'children'=>$singlePermissions,
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    function parseTree($tree, $root = null) {
        $return = array();
        # Traverse the tree and search for direct children of the root
        foreach($tree as $child => $parent) {
            # A direct child is found
            if($parent == $root) {
                # Remove item from tree (we don't need to traverse this again)
                unset($tree[$child]);
                # Append the child into result array and parse its children
                $return[] = array(
                    'name' => $child,
                    'children' => parseTree($tree, $child)
                );
            }
        }
        return empty($return) ? null : $return;
    }

    /**
     * 更新角色权限
     */
    public function actionUpdatePermissions()
    {

        $name = ControllerParameterValidator::getRequestParam($this->allParams, 'name', null, Macro::CONST_PARAM_TYPE_ALNUM_DASH_UNDERLINE, '名称错误', [1, 64]);
        $permissions = ControllerParameterValidator::getRequestParam($this->allParams, 'permissions', null, Macro::CONST_PARAM_TYPE_ARRAY, '权限错误');

        $auth = \Yii::$app->authManager;
        $role = $auth->getRole($name);
        if(!$role){
            throw new \Exception('角色不存在');
        }
        $auth->removeChildren($role);
        foreach ($permissions as $pName){
            $per = $auth->createPermission($pName);
            //分级菜单必须有上级菜单权限
            if(strpos($pName,'|')!==false){
                var_dump($pName);
                $pNameArr = explode('|',$pName);
                $parentPer = $auth->createPermission($pNameArr[0]);
                if (!$auth->hasChild($role, $parentPer)) {
                    $auth->addChild($role, $parentPer);
                }
            }
            if (!$auth->hasChild($role, $per)) {
                $auth->addChild($role, $per);
            }

        }


        return ResponseHelper::formatOutput(Macro::SUCCESS, '更新成功');
    }
}
