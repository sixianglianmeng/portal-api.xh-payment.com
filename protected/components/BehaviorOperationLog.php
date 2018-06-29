<?php


namespace app\components;


class BehaviorOperationLog extends Behavior  #继承行为父类
{
    //重写events方法，给行为添加事件
    public function events()
    {
        return [
            //绑定控制器的after action事件。每次请求后都会触发
            Controller::EVENT_AFTER_ACTION=>'beforeAction',
        ];
    }
    //事件
    public function beforeAction($event){
        if(!\Yii::$app->getUser()->getIsGuest()){
            $userId = \Yii::$app->getUser()->identity->getId();//获取用户ID
            $route = $event->sender->controller->getRoute(); //获取当前访问ROUTE
            $method = \Yii::$app->request->getMethod(); //请求方法
            $getParams = \Yii::$app->request->get();//get 参数
            $postParams =  \Yii::$app->request->post(); //post 参数
            $this->setLog($userId, $route, $method, $getParams, $postParams); //写入日志
        }
    }
    private function setLog($userId,$route, $method, $getParams,$postParams=null){
//        var_dump([$userId,$route, $method]);
    }
}