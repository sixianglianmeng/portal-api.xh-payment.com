<?php

namespace app\modules\api\controllers\v1\admin;

use app\common\models\model\Channel;
use app\common\models\model\ChannelAccount;
use app\common\models\model\Order;
use app\common\models\model\ReportChannelProfitDaily;
use app\common\models\model\ReportFinancialDaily;
use app\common\models\model\ReportRechargeDaily;
use app\common\models\model\User;
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
    public function beforeAction($action)
    {
        $ret = parent::beforeAction($action);

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

        $userId    = ControllerParameterValidator::getRequestParam($this->allParams, 'user_id', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '商户ID错误', [5]);
        $username  = ControllerParameterValidator::getRequestParam($this->allParams, 'username', '',
            Macro::CONST_PARAM_TYPE_USERNAME, '商户名错误', [0, 32]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE, '开始日期错误');
        $dateEnd   = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE, '结束日期错误');

        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', '', Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $query = ReportFinancialDaily::find();
        if ($user->isAdmin()) {

        } elseif ($user->isAgent()) {
            $agentWhere = [
                'or',
                ['like', 'all_parent_agent_id', ',' . $user->id . ','],
                ['like', 'all_parent_agent_id', '[' . $user->id . ']'],
                ['like', 'all_parent_agent_id', '[' . $user->id . ','],
                ['like', 'all_parent_agent_id', ',' . $user->id . ']'],
                ['user_id', $user->id]
            ];
            $query->andWhere($agentWhere);
        } else {
            $query->andWhere(['user_id' => $user->id]);
        }

        if ($username) {
            $query->andWhere(['like', 'username', $username]);
        }
        if ($userId) {
            $query->andWhere(['user_id' => $userId]);
        }

        if ($dateStart) {
            $query->andFilterCompare('date', '>=' . date('Ymd', strtotime($dateStart)));
        }
        if ($dateEnd) {
            $query->andFilterCompare('date', '<' . date('Ymd', strtotime($dateEnd) + 86400));
        }

        $sorts = [
            'created_at-' => ['created_at', SORT_DESC],
        ];
        if (!empty($sorts[$sort])) {
            $sort = $sorts[$sort];
        } else {
            $sort = ['', SORT_DESC];
        }
        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page - 1,
            ],
            'sort' => [
                'defaultOrder' => $sort
            ],
        ]);

        $records = [];
        foreach ($p->getModels() as $i => $d) {
            $records[$i]               = $d->toArray();
            $records[$i]['created_at'] = date('Ymd H:i:s', $d->created_at);
        }
        //分页数据
        $pagination = $p->getPagination();
        $total      = $pagination->totalCount;
        $lastPage   = ceil($pagination->totalCount / $perPage);
        $from       = ($page - 1) * $perPage;
        $to         = $page * $perPage;
        $data       = [
            'data' => $records,
            "pagination" => [
                "total" => $total,
                "per_page" => $perPage,
                "current_page" => $page,
                "last_page" => $lastPage,
                "from" => $from,
                "to" => $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 代理交易(收款)明细
     *
     * @roles admin
     */
    public function actionAgentDailyRecharge()
    {
        $user = Yii::$app->user->identity;

        $userId    = ControllerParameterValidator::getRequestParam($this->allParams, 'user_id', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '商户ID错误', [5]);
        $username  = ControllerParameterValidator::getRequestParam($this->allParams, 'username', '',
            Macro::CONST_PARAM_TYPE_USERNAME, '商户名错误', [0, 32]);
        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE, '开始日期错误');
        $dateEnd   = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE, '结束日期错误');

        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', '', Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $query = ReportRechargeDaily::find();
        $query->andWhere(['user_group_id' => User::GROUP_AGENT]);

        if ($username) {
            $query->andWhere(['like', 'username', $username]);
        }
        if ($userId) {
            $query->andWhere(['user_id' => $userId]);
        }

        if ($dateStart) {
            $query->andFilterCompare('date', '>=' . date('Ymd', strtotime($dateStart)));
        }
        if ($dateEnd) {
            $query->andFilterCompare('date', '<' . date('Ymd', strtotime($dateEnd) + 86400));
        }

        $sorts = [
            'date-' => ['date', SORT_DESC],
        ];
        if (!empty($sorts[$sort])) {
            $sort = $sorts[$sort];
        } else {
            $sort = ['created_at', SORT_DESC];
        }
        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page - 1,
            ],
            'sort' => [
                'defaultOrder' => [
                    $sort[0] => $sort[1],
                ]
            ],
        ]);

        $records = [];
        foreach ($p->getModels() as $i => $d) {
            $records[$i]               = $d->toArray();
            $records[$i]['created_at'] = date('Ymd H:i:s', $d->created_at);
        }
        //分页数据
        $pagination = $p->getPagination();
        $total      = $pagination->totalCount;
        $lastPage   = ceil($pagination->totalCount / $perPage);
        $from       = ($page - 1) * $perPage;
        $to         = $page * $perPage;
        $data       = [
            'data' => $records,
            "pagination" => [
                "total" => $total,
                "per_page" => $perPage,
                "current_page" => $page,
                "last_page" => $lastPage,
                "from" => $from,
                "to" => $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }


    /**
     * 通道交易量
     *
     * @roles admin
     */
    public function actionChannelRecharge()
    {
        $user = Yii::$app->user->identity;

        $dateStart = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE, '开始日期错误');
        $dateEnd   = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE, '结束日期错误');

        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', '', Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $subQuery = (new \yii\db\Query())
            ->from(Order::tableName())
            ->andWhere(['status' => [Order::STATUS_SETTLEMENT, Order::STATUS_PAID]]);

        if ($dateStart) {
            $subQuery->andFilterCompare('paid_at', '>=' . date('Ymd', strtotime($dateStart)));
        }
        if ($dateEnd) {
            $subQuery->andFilterCompare('paid_at', '<' . date('Ymd', strtotime($dateEnd) + 86400));
        }

        $subQuery->select(['channel_account_id,pay_method_code,SUM(amount) AS amount'])
                ->groupBy('channel_account_id,pay_method_code');

        $query = (new \yii\db\Query())
            ->select(['o.channel_account_id', 'c.channel_name', 'o.amount', 'o.pay_method_code'])
            ->from(['o' => $subQuery])
            ->leftJoin(ChannelAccount::tableName() . ' AS c', 'c.id = o.channel_account_id');

        $records = [];
        foreach ($query->all() as $i => $d) {
            if (empty($records[$d['channel_account_id']]['methods'][$d['pay_method_code']])) {
                $records[$d['channel_account_id']]['methods'][$d['pay_method_code']] = [];
            }
            $records[$d['channel_account_id']]['methods'][$d['pay_method_code']]['amount'] = $d['amount'];
            $records[$d['channel_account_id']]['channel_name']                             = $d['channel_name'];
            $records[$d['channel_account_id']]['channel_account_id']                       = $d['channel_account_id'];
        }
        //分页数据
//        $pagination = $p->getPagination();
        $total    = 1;//$pagination->totalCount;
        $lastPage = 1;//ceil($pagination->totalCount/$perPage);
        $from     = ($page - 1) * $perPage;
        $to       = $page * $perPage;
        $data     = [
            'data' => array_values($records),
            'pay_methods' => empty($all) ? Channel::ARR_METHOD : ArrayHelper::merge([Macro::SELECT_OPTION_ALL => '全部'], Channel::ARR_METHOD),
            "pagination" => [
                "total" => $total,
                "per_page" => $perPage,
                "current_page" => $page,
                "last_page" => $lastPage,
                "from" => $from,
                "to" => $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }


    /**
     * 通道利润
     *
     * @roles admin
     */
    public function actionChannelDailyProfit()
    {
        $user = Yii::$app->user->identity;

        $channelAccountId = ControllerParameterValidator::getRequestParam($this->allParams, 'channelAccountId', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '通道账户ID错误', [5]);
        $dateStart        = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE, '开始日期错误');
        $dateEnd          = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE, '结束日期错误');

        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', '', Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $query = ReportChannelProfitDaily::find();

        if ($channelAccountId) {
            $query->andWhere(['channel_account_id' => $channelAccountId]);
        }

        if ($dateStart) {
            $query->andFilterCompare('date', '>=' . date('Ymd', strtotime($dateStart)));
        }
        if ($dateEnd) {
            $query->andFilterCompare('date', '<' . date('Ymd', strtotime($dateEnd) + 86400));
        }

        $sorts = [
            'date-' => ['date', SORT_DESC],
        ];

        if (!empty($sorts[$sort])) {
            $sort = $sorts[$sort];
        } else {
            $sort = ['date'=>SORT_DESC];
        }
        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page - 1,
            ],
            'sort' => [
                'defaultOrder' => $sort
            ],
        ]);

        $records = [];
        foreach ($p->getModels() as $i => $d) {
            $records[$i]               = $d->toArray();
            $records[$i]['created_at'] = date('Ymd H:i:s', $d->created_at);
        }
        //分页数据
        $pagination = $p->getPagination();
        $total      = $pagination->totalCount;
        $lastPage   = ceil($pagination->totalCount / $perPage);
        $from       = ($page - 1) * $perPage;
        $to         = $page * $perPage;
        $data       = [
            'data' => $records,
            "pagination" => [
                "total" => $total,
                "per_page" => $perPage,
                "current_page" => $page,
                "last_page" => $lastPage,
                "from" => $from,
                "to" => $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 通道日对账
     *
     * @roles admin
     */
    public function actionChannelDailyReconciliations()
    {
        $user = Yii::$app->user->identity;

        $channelAccountId = ControllerParameterValidator::getRequestParam($this->allParams, 'channelAccountId', '', Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '通道账户ID错误', [5]);
        $dateStart        = ControllerParameterValidator::getRequestParam($this->allParams, 'dateStart', '',
            Macro::CONST_PARAM_TYPE_DATE, '开始日期错误');
        $dateEnd          = ControllerParameterValidator::getRequestParam($this->allParams, 'dateEnd', '',
            Macro::CONST_PARAM_TYPE_DATE, '结束日期错误');

        $sort    = ControllerParameterValidator::getRequestParam($this->allParams, 'sort', '', Macro::CONST_PARAM_TYPE_SORT, '分页参数错误', [1, 100]);
        $perPage = ControllerParameterValidator::getRequestParam($this->allParams, 'limit', Macro::PAGINATION_DEFAULT_PAGE_SIZE, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 100]);
        $page    = ControllerParameterValidator::getRequestParam($this->allParams, 'page', 1, Macro::CONST_PARAM_TYPE_INT_GT_ZERO, '分页参数错误', [1, 1000]);

        $query = ReportChannelProfitDaily::find();
        if ($channelAccountId) {
            $query->andWhere(['channel_account_id' => $channelAccountId]);
        }

        if ($dateStart) {
            $query->andFilterCompare('date', '>=' . date('Ymd', strtotime($dateStart)));
        }
        if ($dateEnd) {
            $query->andFilterCompare('date', '<' . date('Ymd', strtotime($dateEnd) + 86400));
        }

        $sorts = [
            'created_at-' => ['created_at', SORT_DESC],
        ];
        if (!empty($sorts[$sort])) {
            $sort = $sorts[$sort];
        } else {
            $sort = ['created_at', SORT_DESC];
        }
        //生成分页数据
        $p = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => $perPage,
                'page' => $page - 1,
            ],
            'sort' => [
                'defaultOrder' => [
                    $sort[0] => $sort[1],
                ]
            ],
        ]);

        $records = [];
        foreach ($p->getModels() as $i => $d) {
            $records[$i]               = $d->toArray();
            $records[$i]['created_at'] = date('Ymd H:i:s', $d->created_at);
        }
        //分页数据
        $pagination = $p->getPagination();
        $total      = $pagination->totalCount;
        $lastPage   = ceil($pagination->totalCount / $perPage);
        $from       = ($page - 1) * $perPage;
        $to         = $page * $perPage;
        $data       = [
            'data' => $records,
            "pagination" => [
                "total" => $total,
                "per_page" => $perPage,
                "current_page" => $page,
                "last_page" => $lastPage,
                "from" => $from,
                "to" => $to
            ]
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }


}
