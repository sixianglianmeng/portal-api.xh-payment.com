<?php

    namespace app\common\models\model;

    use app\components\Util;
    use Yii;

    class LogOperation extends BaseModel
    {
        /**
         * @inheritdoc
         */
        public static function tableName()
        {
            return '{{%log_operation}}';
        }

        public function attributeLabels()
        {
            return [
                'id'=>'自增ID',
                'user_id'=>'用户uid',
                'username'=>'姓名',
                'title'=>'操作名称',
                'type'=>'操作类型,目前为请求action',
                'request_url'=>'请求地址',
                'request_method'=>'请求类型：1get 2post',
                'post_data'=>'post参数',
                'response_data'=>'请求对方时的响应内容，或者我方响应给对方的内容',
                'response_status'=>'http请求状态',
                'referer'=>'请求我方接口来源页面地址',
                'useragent'=>'请求我方接口客户端信息',
                'content_before'=>'操作前json',
                'content_after'=>'操作前后json',
                'ip'=>'操作IP',
                'app_version'=>'客户端软件版本',
                'device_info'=>'客户端信息：容器(浏览器/微信/APP)名称，容器版本，操作系统名称，操作系统版本号，硬件名称，分辨率',
                'device_id'=>'客户端硬件ID',
                'created_at'=>'记录生成时间',
                'updated_at'=>'记录更新时间',
                'deleted_at'=>'记录软删除时间',
                'table'=>'操作表名称',
                'tbpk'=>'操作表主键',
                'desc'=>'操作描述',
                'cost_time'=>'程序运行毫秒数',
                'op_status'=>'操作结果,-1未知,0成功,其他为失败',
                'order_no'=>'操作收款或出款订单号',
            ];
        }

        /**
         * 记录日志
         *
         * @param array $logResponse 响应数据
         */
        public static function inLog($logResponse, $desc = '', $contentBefore = '', $contentAfter = '', $table = '', $pk = '')
        {
            if(!Yii::$app->user->identity){
//                return true;
            }
            //非法请求找不到路径
            if(empty(Yii::$app->controller->action)){
                return true;
            }

            $controllerID = Yii::$app->controller->id;

            $controllerID = str_replace('/', '_', $controllerID);
            $actionID     = Yii::$app->controller->action->id;
            $type         = "{$controllerID}_{$actionID}";
            $actionsDoNotNeedLog = ['v1_admin_remit_remind','v1_user_user-check'];
            $actionsNeedLog = ['v1_account_edit','v1_account_update-user-permission','v1_admin_channel_account-edit','v1_admin_channel_account-status','v1_admin_notice_add','v1_admin_order_frozen','v1_admin_order_set-success','v1_admin_order_un-frozen','v1_admin_permission_scan-menu-in-page','v1_admin_remit_set-checked','v1_admin_remit_set-fail','v1_admin_remit_switch-channel-account','v1_admin_remit_set-success','v1_admin_role_update-permissions','v1_admin_siteconfig_add','v1_admin_siteconfig_flush-cache','v1_admin_track_add','v1_admin_track_edit','v1_admin_user_add-tag','v1_admin_user_bind-ips','v1_admin_user_change-agent','v1_admin_user_clear-unbind-update','v1_admin_user_edit','v1_admin_user_switch-recharge-channel','v1_admin_user_switch-remit-channel','v1_admin_user_switch-tag','v1_admin_user_update-quota','v1_admin_user_update-rate','v1_agents_send-notify','v1_order_add','v1_order_send-notify','v1_remit_batch-remit','v1_remit_single-batch-remit','v1_remit_sync-status','v1_upload_base64-upload','v1_upload_upload','v1_upload_upload-result-excel-data','v1_user_add-child','v1_user_clear-child-pass-key','v1_user_edit-auth-key','v1_user_edit-child-status','v1_user_edit-financial-pass','v1_user_edit-pass','v1_user_get-auth-key','v1_user_get-google-code','v1_user_login','v1_user_logout','v1_user_reset-password','v1_user_set-google-code','v1_user_verify-key'];
            //跳过一些不必要的的action日志,例如列表
            if(
                strpos($actionID,'list')!==false
                || strpos($actionID,'option')!==false
                || in_array($type, $actionsDoNotNeedLog)
//                || !in_array($type, $actionsNeedLog)
            ){
                return true;
            }
            //获取原始action名称
            $separator = '-';
            $actionID  = $separator . str_replace($separator, " ", strtolower($actionID));
            $actionID  = ucfirst(ltrim(str_replace(" ", "", ucwords($actionID)), $separator));

            //读取action注释
            $cls        = get_class(Yii::$app->controller);
            $reflection = new \ReflectionClass ($cls);
            $method     = $reflection->getMethod("action{$actionID}");
            $doc        = $method->getDocComment();
            preg_match("/ \* (.+)\n/", $doc, $comment);
            $label = $comment[1] ?? $type;

            $postData = self::clearSensitiveData(Yii::$app->getRequest()->getBodyParams());
            $orderNo = '';
            if(!empty(Yii::$app->params['operationLogFields'])){
                if(!$table && !empty(Yii::$app->params['operationLogFields']['table'])){
                    $table = Yii::$app->params['operationLogFields']['table'];
                }

                if(!$pk && !empty(Yii::$app->params['operationLogFields']['pk'])){
                    $pk = Yii::$app->params['operationLogFields']['pk'];
                }

                if(!$orderNo && !empty(Yii::$app->params['operationLogFields']['order_no'])){
                    $orderNo = Yii::$app->params['operationLogFields']['order_no'];
                }
                if(!$desc && !empty(Yii::$app->params['operationLogFields']['desc'])){
                    $desc = Yii::$app->params['operationLogFields']['desc'];
                }
            }
            $logData                   = [];
            $logData['type']           = $type;
            $logData['title']          = $label;
            $logData['user_id']        = Yii::$app->user->identity ? Yii::$app->user->identity->id : 0;
            $logData['username']       = Yii::$app->user->identity ? Yii::$app->user->identity->username : '';
            $logData['ip']             = Util::getClientIp();
            $logData['table']          = $table;
            $logData['desc']           = $desc;
            $logData['content_before'] = $contentBefore;
            $logData['content_after']  = $contentAfter;
            $logData['tbpk']           = $pk;
            $logData['order_no']       = $orderNo;
            $logData['request_method'] = Yii::$app->request->method=='GET'?1:2;
            $logData['request_url']    = Yii::$app->request->hostInfo . Yii::$app->request->getUrl();
            $logData['request_method'] = Yii::$app->request->method == 'GET' ? 1 : 2;
            $logData['post_data']      = json_encode($postData, JSON_UNESCAPED_UNICODE);
            $logData['response_data']  = json_encode($logResponse, JSON_UNESCAPED_UNICODE);
            $logData['http_status']    = Yii::$app->response->statusCode;
            $logData['referer']        = Yii::$app->request->referrer ?? '';
            $logData['useragent']      = Yii::$app->request->userAgent ?? '';
            $logData['device_id']      = Yii::$app->request->headers->get('x-client-id','');
            $logData['cost_time']      = ceil(Yii::getLogger()->getElapsedTime()*1000);
            $logData['op_status']      = $logResponse['code']??-1;

            $apiRequestLog = new LogOperation();
            $apiRequestLog->setAttributes($logData, false);
            $apiRequestLog->save();

        }

        /**
         * 清除数据中敏感项
         *
         * @param array $data
         */
        public static function clearSensitiveData($data)
        {
            $sensitiveItemKey = [
                'password','password_hash','financial_password_hash','key_2fa','app_secrets',
                'md5_key','app_key_md5','app_key_rsa_private','app_key_rsa_public'
            ];

            foreach ($data as $k=>$d){
                if(in_array($k,$sensitiveItemKey)){
                    $data[$k] = '';
                }
                if(is_array($d)){
                    $data[$k] = self::clearSensitiveData($d);
                }

            }

            return $data;
        }

    }
