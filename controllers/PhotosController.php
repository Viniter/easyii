<?php
namespace yii\easyii\controllers;

use Yii;
use yii\helpers\Url;
use yii\web\UploadedFile;
use yii\web\Response;

use yii\easyii\helpers\Image;
use yii\easyii\components\Controller;
use yii\easyii\models\Photo;
use yii\easyii\behaviors\SortableController;

class PhotosController extends Controller
{
    public $defaultSettings = [
        'photoThumbWidth' => 100,
        'photoThumbHeight' => 100,
        'photoThumbCrop' => true,
    ];

    public function behaviors()
    {
        return [
            [
                'class' => 'yii\filters\ContentNegotiator',
                'formats' => [
                    'application/json' => Response::FORMAT_JSON
                ],
            ],
            [
                'class' => SortableController::className(),
                'model' => Photo::className(),
            ]
        ];
    }

    public function actionUpload($model, $item_id, $maxWidth, $thumbWidth, $thumbHeight = null, $thumbCrop = true)
    {
        $success = null;

        $photo = new Photo;
        $photo->model = $model;
        $photo->item_id = $item_id;
        $photo->image = UploadedFile::getInstance($photo, 'image');

        if($photo->image && $photo->validate(['image'])){
            $photo->image = Image::upload($photo->image, 'photos', $maxWidth);
            if($photo->image){
                $photo->thumb = Image::createThumbnail($photo->image, $thumbWidth, $thumbHeight, $thumbCrop);
                if($photo->save()){
                    $success = [
                        'message' => Yii::t('easyii', 'Photo uploaded'),
                        'photo' => [
                            'id' => $photo->primaryKey,
                            'thumb' => $photo->thumb,
                            'image' => $photo->image,
                            'description' => ''
                        ]
                    ];
                }
                else{
                    @unlink(Yii::getAlias('@webroot') . str_replace(Url::base(true), '', $photo->image));
                    @unlink(Yii::getAlias('@webroot') . str_replace(Url::base(true), '', $photo->thumb));
                    $this->error = Yii::t('easyii', 'Create error. {0}', $photo->formatErrors());
                }
            }
            else{
                $this->error = Yii::t('easyii', 'File upload error. Check uploads folder for write permissions');
            }
        }
        else{
            $this->error = Yii::t('easyii', 'File is incorrect');
        }

        return $this->formatResponse($success);
    }

    public function actionDescription($id)
    {
        if(($model = Photo::findOne($id)))
        {
            if(Yii::$app->request->post('description'))
            {
                $model->description = Yii::$app->request->post('description');
                if(!$model->update()) {
                    $this->error = Yii::t('easyii', 'Update error. {0}', $model->formatErrors());
                }
            }
            else{
                $this->error = Yii::t('easyii', 'Bad response');
            }
        }
        else{
            $this->error = Yii::t('easyii', 'Not found');
        }

        return $this->formatResponse(Yii::t('easyii', 'Photo description saved'));
    }

    public function actionImage($id, $maxWidth, $thumbWidth, $thumbHeight = null, $thumbCrop = true)
    {
        $success = null;

        if(($photo = Photo::findOne($id)))
        {
            $oldImage = $photo->image;
            $oldThumb = $photo->thumb;

            $photo->image = UploadedFile::getInstance($photo, 'image');

            if($photo->image && $photo->validate(['image'])){
                $photo->image = Image::upload($photo->image, 'photos', $maxWidth);
                if($photo->image){
                    $photo->thumb = Image::createThumbnail($photo->image, $thumbWidth, $thumbHeight, $thumbCrop);
                    if($photo->save()){
                        @unlink(Yii::getAlias('@webroot').$oldImage);
                        @unlink(Yii::getAlias('@webroot').$oldThumb);

                        $success = [
                            'message' => Yii::t('easyii', 'Photo uploaded'),
                            'photo' => [
                                'thumb' => $photo->thumb,
                                'image' => $photo->image
                            ]
                        ];
                    }
                    else{
                        @unlink(Yii::getAlias('@webroot').$photo->image);
                        @unlink(Yii::getAlias('@webroot').$photo->thumb);

                        $this->error = Yii::t('easyii', 'Update error. {0}', $photo->formatErrors());
                    }
                }
                else{
                    $this->error = Yii::t('easyii', 'File upload error. Check uploads folder for write permissions');
                }
            }
            else{
                $this->error = Yii::t('easyii', 'File is incorrect');
            }

        }
        else{
            $this->error =  Yii::t('easyii', 'Not found');
        }

        return $this->formatResponse($success);
    }

    public function actionDelete($id)
    {
        if(($model = Photo::findOne($id))){
            $model->delete();
        } else{
            $this->error = Yii::t('easyii', 'Not found');
        }
        return $this->formatResponse(Yii::t('easyii', 'Photo deleted'));
    }

    public function actionUp($id, $model, $item_id)
    {
        return $this->move($id, 'up', ['model' => $model, 'item_id' => $item_id]);
    }

    public function actionDown($id, $model, $item_id)
    {
        return $this->move($id, 'down', ['model' => $model, 'item_id' => $item_id]);
    }
}