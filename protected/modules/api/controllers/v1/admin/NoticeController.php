<?php
/**
 * Created by PhpStorm.
 * User: kk
 * Date: 2018/5/18
 * Time: 下午6:08
 */
namespace app\modules\api\controllers\v1\admin;

use app\common\models\model\Notice;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use yii\data\ActiveDataProvider;

class NoticeController extends BaseController
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
     * 公告列表
     */
    public function actionList()
    {
        $title = ControllerParameterValidator::getRequestParam($this->allParams, 'title', 0, Macro::CONST_PARAM_TYPE_STRING, '公告标题错误');
        $sorts = [
            'created_at-'=>['created_at',SORT_DESC],
        ];

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100000]);
        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status', 0, Macro::CONST_PARAM_TYPE_INT, '显示状态错误',[1,1000]);
        $query = Notice::find();
        if($title){
            $query->andWhere(['like','title',$title]);
        }
        if($status){
            $query->andWhere(['status'=>$status]);
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
            $records[$i]['content'] = $d->content;
            $records[$i]['status'] = $d->status;
            $records[$i]['status_name'] = Notice::ARR_STATUS[$d->status];
            $records[$i]['created_at'] = date("Y-m-d H:i:s",$d->created_at);
        }

        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;
        $data = [
            'data'=>$records,
            'statusOptions' => Notice::ARR_STATUS,
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
     * 添加公告
     * @role admin
     */
    public function actionAdd()
    {
        $id = ControllerParameterValidator::getRequestParam($this->allParams, 'id', 0, Macro::CONST_PARAM_TYPE_INT, '公告编号错误');
        $title = ControllerParameterValidator::getRequestParam($this->allParams, 'title', '', Macro::CONST_PARAM_TYPE_STRING, '公告标题错误');
        $status = ControllerParameterValidator::getRequestParam($this->allParams, 'status', 0, Macro::CONST_PARAM_TYPE_INT, '显示状态错误');
        $content = ControllerParameterValidator::getRequestParam($this->allParams, 'content', '', Macro::CONST_PARAM_TYPE_STRING, '公告内容错误');
        if($id){
            $notice = Notice::findOne(['id'=>$id]);
        }else{
            $notice = new Notice();
        }
        $notice->title = $title;
        $notice->status = $status;
        $notice->content = $content;
        $notice->save();
        return ResponseHelper::formatOutput(Macro::SUCCESS);
    }
}