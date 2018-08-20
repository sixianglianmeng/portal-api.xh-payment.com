<?php
/**
 * Created by PhpStorm.
 * User: kk
 * Date: 2018/5/5
 * Time: 下午10:25
 */

namespace app\modules\api\controllers\v1\admin;
use app\common\models\model\LogApiRequest;
use app\common\models\model\LogOperation;
use app\common\models\model\LogSystem;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;


class LogController extends BaseController
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
     * 接口请求日志
     * @roles admin
     */
    public function actionApiLogList()
    {
        $user = Yii::$app->user->identity;

        $eventType= ControllerParameterValidator::getRequestParam($this->allParams, 'event_type', 0, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '事件类型错误');
        $eventId= ControllerParameterValidator::getRequestParam($this->allParams, 'event_id', '', Macro::CONST_PARAM_TYPE_STRING, '事件ID错误',[5]);
        $merchantName = ControllerParameterValidator::getRequestParam($this->allParams, 'merchant_name', '',Macro::CONST_PARAM_TYPE_USERNAME,'商户名错误',[0,32]);
        $merchantId = ControllerParameterValidator::getRequestParam($this->allParams, 'merchant_id', '',
            Macro::CONST_PARAM_TYPE_INT,'商户ID错误');
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);

        $query = LogApiRequest::find();
        if($merchantName){
            $query->andWhere(['like','merchant_name',$merchantName]);
        }
        if($eventId){
            $query->andWhere(['event_id'=>$eventId]);
        }
        if($eventType){
            $query->andWhere(['event_type'=>$eventType]);
        }
        if ($dateStart) {
            $query->andFilterCompare('created_at', '>=' . strtotime($dateStart));
        }
        if ($dateEnd) {
            $query->andFilterCompare('created_at', '<' . strtotime($dateEnd));
        }
        if($merchantName){
            $query->andWhere(['like','merchant_name',$merchantName]);
        }
        if($merchantId){
            $query->andWhere(['merchant_id'=>$merchantId]);
        }

        $sorts = [
            'created_at-'=>['created_at',SORT_DESC],
        ];
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
            $records[$i] = $d->toArray();
            $records[$i]['created_at'] = date('Ymd H:i:s',$d->created_at);
            $records[$i]['event_type_str'] = LogApiRequest::getEventTypeStr($d->event_type);
        }
        $form['options']['event_type'] = ArrayHelper::merge([Macro::SELECT_OPTION_ALL=>'全部'],LogApiRequest::ARR_EVENT_TYPE);
        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;
        $data = [
            'data'=>$records,
            'form'=>$form,
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
     * 用户操作请求日志
     * @roles admin
     */
    public function actionUserLogList()
    {
        $user = Yii::$app->user->identity;

        $title = ControllerParameterValidator::getRequestParam($this->allParams, 'title', 0, Macro::CONST_PARAM_TYPE_STRING, '操作名称错误');
        $username = ControllerParameterValidator::getRequestParam($this->allParams, 'username', '',
            Macro::CONST_PARAM_TYPE_USERNAME,'用户名错误',[0,32]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);

        $query = LogOperation::find();
        if($title){
            $query->andWhere(['like','title',$title]);
        }
        if ($dateStart) {
            $query->andFilterCompare('created_at', '>=' . strtotime($dateStart));
            $updateFilter[] = "created_at>=" . strtotime($dateStart);
        }
        if ($dateEnd) {
            $query->andFilterCompare('created_at', '<' . strtotime($dateEnd));
            $updateFilter[] = "created_at<" . strtotime($dateEnd);
        }
        if($username){
            $query->andWhere(['like','username',$username]);
        }
        $sorts = [
            'created_at-'=>['created_at',SORT_DESC],
        ];
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
            $records[$i] = $d->toArray();
            $records[$i]['created_at'] = date('Ymd H:i:s',$d->created_at);
        }

        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;
        $data = [
            'fieldLabels'=>(new LogOperation())->attributeLabels(),
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
     * 系统错误日志
     * @roles admin
     */
    public function actionSystemLogList()
    {
        $user = Yii::$app->user->identity;

        $title = ControllerParameterValidator::getRequestParam($this->allParams, 'title', 0, Macro::CONST_PARAM_TYPE_STRING, '搜索关键字错误');
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);

        $query = LogSystem::find();
        if($title){
            $query->andWhere(['like','message',$title]);
        }
        if ($dateStart) {
            $query->andFilterCompare('log_time', '>=' . strtotime($dateStart));
            $updateFilter[] = "log_time>=" . strtotime($dateStart);
        }
        if ($dateEnd) {
            $query->andFilterCompare('log_time', '<' . strtotime($dateEnd));
            $updateFilter[] = "log_time<" . strtotime($dateEnd);
        }

        $sorts = [
            'created_at-'=>['log_time',SORT_DESC],
        ];
        if(!empty($sorts[$sort])){
            $sort = $sorts[$sort];
        }else{
            $sort =['log_time',SORT_DESC];
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
            $records[$i] = $d->toArray();
            $records[$i]['log_time'] = date('Ymd H:i:s',$d->log_time);
            $records[$i]['message'] = str_replace("\n","<br />",$records[$i]['message']);
        }

        //分页数据
        $pagination = $p->getPagination();
        $total = $pagination->totalCount;
        $lastPage = ceil($pagination->totalCount/$perPage);
        $from = ($page-1)*$perPage;
        $to = $page*$perPage;
        $data = [
            'fieldLabels'=>(new LogSystem())->attributeLabels(),
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
}