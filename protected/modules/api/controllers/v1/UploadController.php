<?php
namespace app\modules\api\controllers\v1;

use app\modules\api\controllers\BaseController;
use Yii;
use yii\web\UploadedFile;
use app\components\Macro;
use app\components\Util;
use app\lib\helpers\ResponseHelper;
use app\common\models\model\FileModel;
//use mdm\upload\FileModel;
use app\lib\helpers\ControllerParameterValidator;

/*
 * 上传控制器
 */
class UploadController extends BaseController
{
    public function beforeAction($action)
    {
        return parent::beforeAction($action);
    }

    public function behaviors()
    {
        $parentBehaviors = parent::behaviors();
        //验证码不需要token验证
        $behaviors['authenticator']['optional'] = ['upload', 'uploadBase64','uploadResultExcelData'];
        return \yii\helpers\ArrayHelper::merge($parentBehaviors, $behaviors);
    }

    /**
     * 文件上传
     */
    public function actionUpload()
    {
        $errRespCode = 406;
        $maxSize = 20 * 1024 * 1024;
        $file = UploadedFile::getInstanceByName('file');
        //附件模块类型，例如头像，商品图片等
        $module = ControllerParameterValidator::getRequestParam($this->allParams, 'm', '', Macro::CONST_PARAM_TYPE_ALNUM);
        $moduleLen = strlen($module);
        if ($moduleLen < 2 || $moduleLen > 16) $module = '';

        if (!in_array($file->type, FileModel::$allowTypes)) {
            //添加响应代码，用于兼容饿了么element ui无移除已上传文件函数的问题
            Yii::$app->response->statusCode = $errRespCode;
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '不支持的文件类型');
        }

        if (!$file->size || $file->size > $maxSize) {
            Yii::$app->response->statusCode = $errRespCode;

            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '文件太大');
        }

        $path = '@webroot/uploads/' . ($module ? $module : 'attachment') . '/' . date('Ym');
        $fileModel = FileModel::saveAs($file, ['uploadPath' => $path]);

        if ($fileModel) {
            $data =[
                'url' => $fileModel->url,
                'id' => $fileModel->id
            ];
            return ResponseHelper::formatOutput(Macro::SUCCESS, '', $data);
        } else {
            $err = $fileModel->getErrors();
            if(is_array($err)){
                $err = json_encode($err);
            }
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, $err);
        }

    }


    /**
     * 图片base64上传
     */
    public function actionBase64Upload()
    {
        if (!empty($_POST['base64Img'])) {
            $name = empty($_REQUEST['imgName']) ? uniqid() : $_REQUEST['imgName'];
            $w = getRequestParam('w', 0, ErrCode::CONST_PARAM_TYPE_INT);
            $h = getRequestParam('h', 0, ErrCode::CONST_PARAM_TYPE_INT);

            $imgBin = $_POST['base64Img'];
            $arrTmp1 = explode(';', $imgBin);
            $arrTmp2 = explode('/', $arrTmp1[0]);
            if (count($arrTmp2) != 2) {
                return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '图片类型识别失败，请重试');
            }
            $ext = $arrTmp2[1];

            $randName = uniqid();

            // 产生目录结构
            $rand_num = 'images/tmp/' . substr($randName, 0, 3) . date('Ym', $_SERVER['REQUEST_TIME']) . '/';

            $upload_dir = PIGCMS_PATH . '/upload/' . $rand_num;
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $fileNmame = $randName . '.' . $ext;
            $data['file'] = $upload_dir . $fileNmame;
            $binTmp = explode(',', $imgBin);
            $wrRet = file_put_contents($upload_dir . $fileNmame, base64_decode($binTmp[1]));//返回的是字节数

            if ($w && $h) {
                import("source.class.Image");
                if (Image::thumb2($upload_dir . $fileNmame, $upload_dir . $fileNmame . "_{$w}x{$h}.{$ext}", '', $w, $h)) {
                    $fileNmame = $fileNmame . "_{$w}x{$h}.{$ext}";
                }
            }

            //$pigcms_id = $this->_attachmentAdd($uploadList[0]['name'], $rand_num . $uploadList[0]['savename'], $uploadList[0]['size']);
            $data['uid'] = 0;
            $data['name'] = $name;
            $data['from'] = 0;
            $data['type'] = 0;
            $data['size'] = $wrRet;
            $data['add_time'] = time();
            $data['ip'] = get_client_ip(1);
            $data['agent'] = $_SERVER['HTTP_USER_AGENT'];
            $pigcms_id = M('Attachment_user')->add($data);

            if (!$pigcms_id) {
                unlink($upload_dir . $fileNmame);

                json_return(ErrCode::ERR_UNKNOWN, '图片上传失败');
            } else {
                json_return(ErrCode::SUCCESS, getAttachmentUrl('/upload/' . $rand_num . $fileNmame), $pigcms_id);
            }

        } else {
            json_return(ErrCode::ERR_UNKNOWN, '图片上传失败');
        }
    }

    /**
     * 上传excel文件
     */
    public function actionUploadResultExcelData()
    {
        $errRespCode = 406;
        $maxSize = 20 * 1024 * 1024;
        $file = UploadedFile::getInstanceByName('file');
        //附件模块类型，例如头像，商品图片等
        $module = ControllerParameterValidator::getRequestParam($this->allParams, 'm', '', Macro::CONST_PARAM_TYPE_ALNUM);
        $moduleLen = strlen($module);
        if ($moduleLen < 2 || $moduleLen > 16) $module = '';

        if (!in_array($file->type, FileModel::$allowTypes)) {
            //添加响应代码，用于兼容饿了么element ui无移除已上传文件函数的问题
            Yii::$app->response->statusCode = $errRespCode;
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '不支持的文件类型');
        }

        if (!$file->size || $file->size > $maxSize) {
            Yii::$app->response->statusCode = $errRespCode;

            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, '文件太大');
        }
//        var_dump($file);die;
        $path = '@webroot/uploads/' . ($module ? $module : 'excel') . '/' . date('Ym');
        $fileModel = FileModel::saveAs($file, ['uploadPath' => $path]);
        if($fileModel){
            $fileType   = \PHPExcel_IOFactory::identify($path); //文件名自动判断文件类型
            $excelReader  = \PHPExcel_IOFactory::createReader($fileType);
            $phpexcel    = $excelReader->load($path)->getSheet(0);//载入文件并获取第一个sheet
            $total_line  = $phpexcel->getHighestRow();//总行数
//            var_dump($total_line);
            $total_column= $phpexcel->getHighestColumn();//总列数
        }else{
            $err = $fileModel->getErrors();
            return ResponseHelper::formatOutput(Macro::ERR_UNKNOWN, json_encode($err));
        }
    }
}
