<?php
/**
 * Created by PhpStorm.
 * User: Dmitry Andreevich
 * Date: 25.09.2018
 * Time: 19:17
 */

namespace app\controllers;


use Aws\S3\S3Client;
use Codeception\Module\Yii2;
use Faker\Provider\File;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use Yii;
use yii\base\Exception;
use yii\base\Module;
use yii\helpers\FileHelper;
use yii\helpers\VarDumper;
use yii\web\Controller;
use yii\web\UploadedFile;

class MainController extends Controller
{
    protected $fsAdapter = null;
    protected $fileSystemRootPath = 'filesystem';

    public function __construct($id, Module $module, array $config = [])
    {
        $this->enableCsrfValidation = false;

        //header('Access-Control-Allow-Origin: *');
        //header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS, FILE');
        //header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, X-Requested-With');

        $this->switchAdapter();

        parent::__construct($id, $module, $config);
    }

    public function beforeAction($action)
    {
        if($this->fsAdapter == null) {
            $this->fsAdapter = new Local(__DIR__ . '/../' . $this->fileSystemRootPath);
        }

        return parent::beforeAction($action); // TODO: Change the autogenerated stub
    }

    public function actionIndex()
    {
        return $this->render('index');
    }


    public function actionGet_files_data()
    {
        if($this->fsAdapter) {
            $path = Yii::$app->request->post('path');
            $fs = new Filesystem($this->fsAdapter);
            $content = $fs->listContents("/$path");

            echo json_encode($content, JSON_FORCE_OBJECT);
            exit;
        }

        echo '{ "type": "error" }';
        exit;
    }
    public function actionDelete()
    {
        if($this->fsAdapter){
            $type = Yii::$app->request->post('type'); // dir or another file
            $path = Yii::$app->request->post('path');

            if( isset($path) && !empty($path) ) {
                if( isset($type) && !empty($type) ){
                    $fs = new Filesystem($this->fsAdapter);

                    if( $fs->has($path) ){
                        if($type === 'dir')
                            $fs->deleteDir($path);
                        else
                            $fs->delete($path);

                        exit;
                    }
                }
            }
        }

        echo '{ "type": "error" }';
        exit;
    }

    public function actionNew_folder()
    {
        if($this->fsAdapter){
            $folderName = Yii::$app->request->post('name');
            $path = Yii::$app->request->post('path');

            if( isset($folderName) && !empty($folderName) ) {
                $fs = new Filesystem($this->fsAdapter);

                if(!$fs->has("$path/$folderName"))
                    $fs->createDir("$path/$folderName");
            }
        }

        echo '{ "type": "error" }';
        exit;
    }

    public function actionRename()
    {
        if($this->fsAdapter){

            $path = Yii::$app->request->post('path');
            $newPath = Yii::$app->request->post('newPath');

            if( isset($path) && isset($newPath) ) {
                $fs = new Filesystem($this->fsAdapter);

                if( $fs->has($path) )
                    $fs->rename($path, $newPath);
            }
        }

        echo '{ "type": "error" }';
        exit;
    }

    public function actionGet_content()
    {
        if($this->fsAdapter){
            $path = Yii::$app->request->post('path');

            if( isset($path) ) {
                $fs = new Filesystem($this->fsAdapter);

                if( $fs->has($path) ){
                    $file = $fs->get($path);

                    if($file->getType() !== 'dir') {
                        $content = $file->read();
                        $response = [
                            'type' => $file->getMimetype(),
                            'basename' => basename(__DIR__ . '/../' . $this->fileSystemRootPath . '/' . $path),
                            'content' => strstr($file->getMimetype(), 'image') ? base64_encode($content) : $content
                        ];

                        echo json_encode($response);
                        exit;
                    }
                }
            }
        }

        echo '{ "type": "error" }';
        exit;
    }

    public function actionCreate_or_rewrite_file()
    {
        if($this->fsAdapter){
            $path = Yii::$app->request->post('path');
            $content = Yii::$app->request->post('content');

            if( isset($path) && isset($content) ) {
                $fs = new Filesystem($this->fsAdapter);

                if( $fs->has($path) ) {
                    $fs->update($path, $content);
                } else{
                    $fs->write($path, $content);
                }

                exit;
            }
        }

        echo '{ "type": "error" }';
        exit;
    }

    public function actionUpload()
    {
        $file = UploadedFile::getInstanceByName('file');
        $pathToSave = Yii::$app->request->post('path');
        if($file) {
            $fs = new Filesystem($this->fsAdapter);

            $fileSize = $file->size;
            $tmpPath = $file->tempName;

            /**
             * try file_get_contents($tmpPath);
             */
            $f = fopen($tmpPath, 'r');
            $content = fread($f, $fileSize);
            fclose($f);

            $pathToSave .= '/' . $file->getBaseName() . '.' . $file->getExtension();
            $fs->write($pathToSave, $content);

            echo $pathToSave;
            exit;
        }
    }

    protected function switchAdapter(){
        $selectedStore = Yii::$app->request->post('selectedStore');


        if ($selectedStore == 'S3'){
            $client = new S3Client([
                'credentials' => [
                    'key'    => 'AKIAJZV6CKJQVJQ5NMMA',
                    'secret' => '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'
                ],
                'region' => 'us-east-2',
                'version' => 'latest',
            ]);

            $this->fsAdapter = new AwsS3Adapter($client,'maxim-filestore');
        }else {
            $this->fsAdapter = new Local(__DIR__ . '/../' . $this->fileSystemRootPath);
        }
    }
}