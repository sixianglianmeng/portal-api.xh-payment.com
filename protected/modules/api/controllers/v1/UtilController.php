<?php
namespace app\modules\api\controllers\v1;

use app\common\models\model\BankCodes;
use app\common\models\model\SiteConfig;
use Yii;
use app\lib\helpers\ResponseHelper;
use app\components\Macro;
use app\modules\api\controllers\BaseController;
use yii\db\Query;

class UtilController extends BaseController
{
    public function behaviors()
    {
        $parentBehaviors = parent::behaviors();
        //验证码不需要token验证
        $behaviors['authenticator']['optional'] = ['site-info'];
        $behaviors =  \yii\helpers\ArrayHelper::merge($parentBehaviors, $behaviors);

        return $behaviors;
    }

    /**
     * 前置action
     *
     * @author bootmall@gmail.com
     */
    public function beforeAction($action){
        $parentBeforeAction =  parent::beforeAction($action);

        //生成查询参数
        if(Yii::$app->user->identity && !Yii::$app->user->identity->isAdmin()){
            $this->baseFilter['merchant_id'] = Yii::$app->user->identity->id;
        }

        return $parentBeforeAction;
    }

    /**
     * 银行限额列表
     *
     * @role user_base
     * @author bootmall@gmail.com
     */
    public function actionBankQuota(){
        //select bank_name,max(single_quota),max(everyday_quota) from p_bank_codes group by bank_name
        $query = (new Query())
            ->select(['bank_name','platform_bank_code','MAX(single_quota) AS single_quota','MAX(everyday_quota) AS everyday_quota'])
            ->from(BankCodes::tableName())
            ->groupBy('platform_bank_code');
        $data = $query->cache(300)->all();

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }

    /**
     * 获取站点信息
     *
     * @role user_base
     * @author bootmall@gmail.com
     */
    public function actionSiteInfo(){
        $data = [
            'siteName' => SiteConfig::cacheGetContent('site_name'),
        ];

        return ResponseHelper::formatOutput(Macro::SUCCESS, '操作成功', $data);
    }
}
