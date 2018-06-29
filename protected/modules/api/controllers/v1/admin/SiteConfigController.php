<?php
namespace app\modules\api\controllers\v1\admin;

use app\common\models\model\SiteConfig;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use app\modules\gateway\models\logic\LogicRemit;
use Yii;
use yii\data\ActiveDataProvider;

class SiteConfigController extends BaseController
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
        $parentBeforeAction =  parent::beforeAction($action);

        return $parentBeforeAction;
    }

    /**
     * 站点配置列表
     */
    public function actionList()
    {
        $desc = ControllerParameterValidator::getRequestParam($this->allParams, 'desc', 0, Macro::CONST_PARAM_TYPE_STRING, '配置标题错误');
        $sorts = [
            'created_at-'=>['created_at',SORT_DESC],
        ];

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);
        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status', 0, Macro::CONST_PARAM_TYPE_INT, '显示状态错误',[1,1000]);
        $query = SiteConfig::find();
        if($desc){
            $query->andWhere(['like','desc',$desc]);
        }

        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['created_at',SORT_DESC];
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
        $records=[];
        foreach ($p->getModels() as $i=>$d){
            $records[$i]['id'] = $d->id;
            $records[$i]['title'] = $d->title;
            $records[$i]['desc'] = $d->desc;
            $records[$i]['content'] = "$d->content";
            $records[$i]['data_type'] = json_decode($d->data_type, true);
        }

        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;
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
     * 添加编辑配置
     */
    public function actionAdd()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', 0, Macro::CONST_PARAM_TYPE_INT, '配置编号错误');
        $title = ControllerParameterValidator::getRequestParam($this->allParams, 'title', null, Macro::CONST_PARAM_TYPE_STRING, '配置标题错误');
        $desc = ControllerParameterValidator::getRequestParam($this->allParams, 'desc', null, Macro::CONST_PARAM_TYPE_STRING, '配置描述错误');
        $content = ControllerParameterValidator::getRequestParam($this->allParams, 'content', null, Macro::CONST_PARAM_TYPE_STRING, '配置内容错误');
        
//        "text","string",[0,64]
//        "input","int"[0,100000]
//        "ui":"radio","type":"enum","ruleExt":[0,1]

        if($id){
            $config = SiteConfig::findOne(['id'=>$id]);
        }else{
            $config = new SiteConfig();
            $config->data_type = json_encode(["ui"=>"text","type"=>"string","ruleExt"=>[0,64]]);
        }
        $config->title = $title;
        $config->desc = $desc;
        $config->setContent($content);
        $config->save();

        SiteConfig::delAllCache();

        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }

    /**
     * 刷新站点缓存
     */
    public function actionFlushCache()
    {
        Yii::$app->cache->flush();
    }
}
