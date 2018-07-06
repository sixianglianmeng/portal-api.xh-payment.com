<?php
namespace app\common\models\model;

use app\components\Macro;
use Yii;

class SiteConfig extends BaseModel
{
    static private $_vals = [];
    public static function getDb()
    {
        return \Yii::$app->db;
    }

    public static function tableName()
    {
        return '{{%site_config}}';
    }

    /**
     * 获取完成的缓存key
     *
     * @param $key
     *
     * @return string
     */
    protected static function getCacheKey($key)
    {
        return 'site_config.'.$key;
    }

    /**
     *
     * 从缓存中获取站点配置
     * 获取优先级为类静态变量-缓存-mysql
     *
     * @param strint $key 配置项key
     *
     * @return string
     */
    public static function cacheGetContent($key)
    {
        if(!empty(self::$_vals[$key])){
            return self::$_vals[$key];
        }

        $content = Yii::$app->redis->hget(Macro::CACHE_HSET_SITE_CONFIG,$key);
        if(!$content){
            $config = self::findOne(['title'=>$key]);
            if($config){
                $content = $config->content;
                Yii::$app->redis->hset(Macro::CACHE_HSET_SITE_CONFIG,$key,$content);
            }
        }
        $content=$content??'';
        self::$_vals[$key] = $content;

        return $content;
    }

    /**
     *
     * 从缓存中获取所有站点配置
     *
     * @return string
     */
    public static function cacheGetAll()
    {
        $redisContent = Yii::$app->redis->hgetall(Macro::CACHE_HSET_SITE_CONFIG);
        $content = [];
        if($redisContent){
            foreach ($redisContent as $k=>$v){
                if($k==0) continue;

                $content[$redisContent[$k-1]] = $v;

                self::$_vals[$redisContent[$k-1]] = $v;
            }
        }

        return $content;
    }

    /**
     *
     * 删除所有站点缓存配置
     *
     * @return string
     */
    public static function delAllCache()
    {
        Yii::$app->redis->del(Macro::CACHE_HSET_SITE_CONFIG);
        self::$_vals = [];

        return true;
    }

    public function setContent($content)
    {
        $this->content = $content;
        Yii::$app->redis->hset(Macro::CACHE_HSET_SITE_CONFIG,$this->title,$content);
    }

    /**
     * 获取管理员IP白名单列表
     *
     * @return array
     */
    public static function getAdminIps()
    {
        $ipList = SiteConfig::cacheGetContent('admin_ip_list');
        return empty($ipList)?[]:explode(',',$ipList);
    }

}