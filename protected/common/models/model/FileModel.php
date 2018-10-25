<?php

namespace app\common\models\model;

use Yii;
use yii\web\UploadedFile;
use yii\helpers\FileHelper;
use app\common\models\model\BaseModel;
use yii\db\ActiveRecord;

/**
 * 文件上传Model
 *
 * @property integer $id
 * @property string $name
 * @property string $filename
 * @property integer $size
 * @property string $type
 * @see https://github.com/mdmsoft/yii2-upload-file
 */
class FileModel extends BaseModel
{
    /**
     * @var string 
     */
    public static $defaultUploadPath = '@webroot/uploads';
    /**
     * @var integer
     */
    public static $defaultDirectoryLevel = 1;

    /**
     * @var \$types
     */
    public static $allowTypes = [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/gif',
        'text/csv',
        'text/plain',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/octet-stream'
    ];

    /**
     * @var UploadedFile 
     */
    public $file;

    /**
     * @var string Upload path
     */
    public $uploadPath;

    /**
     * @var integer the level of sub-directories to store uploaded files. Defaults to 1.
     * If the system has huge number of uploaded files (e.g. one million), you may use a bigger value
     * (usually no bigger than 3). Using sub-directories is mainly to ensure the file system
     * is not over burdened with a single directory having too many files.
     */
    public $directoryLevel;

    /**
     * @var \Closure
     */
    public $saveCallback;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%uploaded_file}}';

    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors[] =[

            'class' => 'app\common\models\model\UploadBehavior',
            'attribute' => 'file', // required, use to receive input file
            'savedAttribute' => 'file_id', // optional, use to link model with saved file.
            'uploadPath' => '@webroot/uploads', // saved directory. default to '@runtime/upload'
            'autoSave' => true, // when true then uploaded file will be save before ActiveRecord::save()
            'autoDelete' => true, // when true then uploaded file will deleted before ActiveRecord::delete()
        ];

        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['file'], 'required'],
            [['file'], 'file', 'skipOnEmpty' => false],
            [['uploadPath'], 'default', 'value' => static::$defaultUploadPath],
            [['name', 'size'], 'default', 'value' => function($obj, $attribute) {
                if($attribute=='name'){
                    $obj->file->$attribute = substr($obj->file->$attribute,0,32);
                }
                return $obj->file->$attribute;
            }],
            [['type'], 'default', 'value' => function() {
                return FileHelper::getMimeType($this->file->tempName);
            }],
            [['filename'], 'default', 'value' => function() {
                $level = $this->directoryLevel === null ? static::$defaultDirectoryLevel : $this->directoryLevel;
                $key = md5(microtime() . $this->file->name);
                $base = Yii::getAlias($this->uploadPath);
                if ($level > 0) {
                    for ($i = 0; $i < $level; ++$i) {
                        if (($prefix = substr($key, 0, 2)) !== false) {
                            $base .= DIRECTORY_SEPARATOR . $prefix;
                            $key = substr($key, 2);
                        }
                    }
                }

                $type = FileHelper::getMimeType($this->file->tempName);
                $pathInfo = pathinfo($this->file->name);
//                if('inode/x-empty'===$type){
//                    $ext =$pathInfo['extension'];
//                }else{
//                    $tmpArr = explode("/",$type);
//                    $ext = end($tmpArr);
//                }
                $ext =$pathInfo['extension'];

                $file =  $base . DIRECTORY_SEPARATOR . "{$key}";
                $ext && $file.='.'.$ext;

                return $file;
            }],
            [['size'], 'integer'],
            [['name'], 'string', 'max' => 32],
            [['type'], 'string', 'max' => 128],
            [['ip'], 'default', 'value'=>Yii::$app->request->userIp],
            [['uid'], 'default', 'value'=>empty(Yii::$app->user)?0:Yii::$app->user->id],
            [['filename'], 'string', 'max' => 256]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => '文件名',
            'filename' => '文件路径',
            'size' => '文件大小',
            'type' => '文件类型',
        ];
    }

    /**
     * @inherited
     */
    public function beforeSave($insert)
    {
        if ($this->file && $this->file instanceof UploadedFile && parent::beforeSave($insert)) {
            FileHelper::createDirectory(dirname($this->filename));
            if ($this->saveCallback === null) {
                $ret = $this->file->saveAs($this->filename, false);
                $this->filename = str_replace(Yii::getAlias('@webroot'), '',$this->filename);
                $this->size = intval($this->size/1000);
                return $ret;
            } else {
                return call_user_func($this->saveCallback, $this);
            }
        }
        return false;
    }

    /**
     * @inherited
     */
    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            return unlink(Yii::getAlias('@webroot').$this->filename);
        }
        return false;
    }

    /**
     * Save file
     * @param UploadedFile|string $file
     * @param array $options
     * @return boolean|static
     */
    public static function saveAs($file, $options = [])
    {
        if (!($file instanceof UploadedFile)) {
            $file = UploadedFile::getInstanceByName($file);
        }
        $options['file'] = $file;
        $model = new static($options);
        $model->save();
        return $model;
    }

    public function getContent()
    {
        return file_get_contents($this->filename);
    }

    public function getUrl()
    {
        return $base = ['host'=>Yii::$app->request->hostInfo,'filepath'=>$this->filename];
//        return $base = Yii::$app->request->hostInfo.$this->filename;
    }
}
