<?php
namespace yii\easyii\modules\file\api;

use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\Url;
use yii\easyii\modules\file\models\File as FileModel;
use yii\widgets\LinkPager;

class File extends \yii\easyii\components\API
{
    private $_files = [];
    private $_adp;
    private $_options = [
        'pageSize' => 20,
        'where' => '',
    ];

    public function api_get($id_slug)
    {
        if(!isset($this->_files[$id_slug])){
            $this->_files[$id_slug] = $this->findFile($id_slug);
        }
        return $this->_files[$id_slug];
    }

    public function api_all($options = [])
    {
        $result = [];

        if(is_array($options) && count($options)){
            $this->_options = array_merge($this->_options, $options);
        }

        foreach($this->adp->models as $file){
            $result[] = $this->parseFile($file);
        }
        return $result;
    }

    public function api_last($limit = 1)
    {
        if($limit === 1 && isset($this->_files['last'])){
            return $this->_files['last'];
        }

        $result = [];
        foreach(FileModel::find()->sort()->limit($limit)->all() as $file){
            $result[] = $this->parseFile($file);
        }
        if(!count($result)) {
            $result[] = $this->createObject('<a href="' . Url::to(['/admin/file/a/create']) . '" target="_blank">'.Yii::t('easyii/file/api', 'Create file').'</a>');
            return $limit > 1 ? $result : $result[0];
        }

        if($limit > 1){
            return $result;
        }else{
            $this->_files['last'] = $result[0];
            return $this->_files['last'];
        }
    }

    public function api_pagination()
    {
        return $this->adp->pagination;
    }

    public function api_pages()
    {
        return LinkPager::widget(['pagination' => $this->adp->pagination]);
    }

    private function findFile($id_slug)
    {
        $file = FileModel::find()->where(['or', 'file_id=:id_slug', 'slug=:id_slug'], [':id_slug' => $id_slug])->one();

        if($file){
            $result = $this->parseFile($file);

            $result->seo_h1 = $file->seo_h1;
            $result->seo_title = $file->seo_title;
            $result->seo_keywords = $file->seo_keywords;
            $result->seo_description = $file->seo_description;

            return $result;
        } else {
            return $this->notFound($id_slug);
        }
    }

    protected function getAdp()
    {
        if(!$this->_adp){
            $query = FileModel::find()->sort();

            if($this->_options['where']){
                $query->andWhere($this->_options['where']);
            }

            $this->_adp = new ActiveDataProvider([
                'query' => $query,
                'pagination' => [
                    'pageSize' => $this->_options['pageSize']
                ]
            ]);
        }
        return $this->_adp;
    }

    private function parseFile($file)
    {
        if(LIVE_EDIT){
            $file->title = $this->wrapLiveEdit($file->title, 'a/edit/'.$file->primaryKey);
        }
        return $this->createObject($file->attributes);
    }

    private function createObject($data)
    {
        $is_string = !is_array($data);

        return (object)[
            'id' => $is_string ? '' : $data['file_id'],
            'title' => $is_string ? $data : $data['title'],
            'slug' => $is_string ? '' : $data['slug'],
            'bytes' => $is_string ? '' : $data['size'],
            'size' => $is_string ? '' : Yii::$app->formatter->asShortSize($data['size'], 2),
            'file' => $is_string ? '' : Url::to(['/admin/file/download', 'id' => $data['file_id']),
            'link' => $is_string ? $data : '<a href="' . Url::to(['/admin/file/download', 'id' => $data['file_id']]) . '" class="easyiicms-file" target="_blank">'.$data['title'].'</a>',
            'downloads' => $is_string ? '' : $data['downloads'],
            'time' => $is_string ? '' : $data['time'],
            'date' => $is_string ? '' : Yii::$app->formatter->asDatetime($data['time'], 'medium'),
            'empty' => $is_string ? true : false
        ];
    }

    private function notFound($id_slug)
    {
        if(Yii::$app->user->isGuest) {
            return $this->createObject('');
        }
        elseif(preg_match(FileModel::$slugPattern, $id_slug)){
            return $this->createObject('<a href="' . Url::to(['/admin/file/a/create', 'slug' => $id_slug]) . '" target="_blank">'.Yii::t('easyii/file/api', 'Create file').'</a>');
        }
        else{
            return $this->createObject($this->errorText('WRONG FILE IDENTIFIER'));
        }
    }    
}