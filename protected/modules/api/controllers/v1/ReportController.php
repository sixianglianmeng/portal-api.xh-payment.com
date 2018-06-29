<?php
namespace app\modules\api\controllers\v1;

use app\common\models\model\ReportFinancialDaily;
use app\components\Macro;
use app\lib\helpers\ControllerParameterValidator;
use app\lib\helpers\ResponseHelper;
use app\modules\api\controllers\BaseController;
use app\modules\gateway\models\logic\LogicOrder;
use Yii;
use yii\data\ActiveDataProvider;

class ReportController extends BaseController
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
     * 日收支统计管理
     *
     * @roles admin
     */
    public function actionDailyFinancial()
    {
        $user = Yii::$app->user->identity;

        $userId= ControllerParameterValidator::getRequestParam($this->allParams, 'user_id', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '商户ID错误',[5]);
        $username = ControllerParameterValidator::getRequestParam($this->allParams, 'username', '',
            Macro::CONST_PARAM_TYPE_USERNAME,'商户名错误',[0,32]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE,'开始日期错误');
        $dateEnd = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE,'结束日期错误');

        $sort = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', 15, Macro::CONST_PARAM_TYPE_SORT, '分页参数错误',[1,100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,100]);
        $page = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误',[1,1000]);

        $query = ReportFinancialDaily::find();
        if($user->isAdmin()){

        }elseif($user->isAgent()){
            $agentWhere = [
                'or',
                ['like','all_parent_agent_id',','.$user->id.','],
                ['like','all_parent_agent_id','['.$user->id.']'],
                ['like','all_parent_agent_id','['.$user->id.','],
                ['like','all_parent_agent_id',','.$user->id.']'],
                ['user_id',$user->id]
            ];
            $query->andWhere($agentWhere);
        }else{
            $query->andWhere(['user_id'=>$user->id]);
        }

        if($username){
            $query->andWhere(['like','username',$username]);
        }
        if($userId){
            $query->andWhere(['user_id'=>$userId]);
        }

        if ($dateStart) {
            $query->andFilterCompare('created_at', '>=' . date('Ymd',strtotime($dateStart)));
        }
        if ($dateEnd) {
            $query->andFilterCompare('created_at', '<' . date('Ymd',strtotime($dateEnd)+86400));
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
