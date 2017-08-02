<?php

namespace zxbodya\yii2\galleryManager;


use common\models\c2\entity\EntityAttachment;
use common\models\c2\search\CmsBlockItem;
use Yii;
use yii\base\Action;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use yii\web\HttpException;
use yii\web\UploadedFile;

/**
 * Backend controller for GalleryManager widget.
 * Provides following features:
 *  - Image removal
 *  - Image upload/Multiple upload
 *  - Arrange images in gallery
 *  - Changing name/description associated with image
 *
 * @author Bogdan Savluk <savluk.bogdan@gmail.com>
 */
class GalleryManagerAction extends Action
{
    public $modelClass = 'common\models\c2\entity\CmsBlockItem';
    /**
     * Glue used to implode composite primary keys
     * @var string
     */
    public $pkGlue = '_';

    /**
     * $types to be defined at Controller::actions()
     * @var array Mapping between types and model class names
     * @example 'post'=>'common\models\Post'
     * @see     GalleryManagerAction::run
     */
    public $types = [];


    protected $type;
    protected $behaviorName;
    protected $galleryId;

    /** @var  ActiveRecord */
    protected $owner;
    /** @var  GalleryBehavior */
    protected $behavior;


    public function run($action)
    {
        $this->type = Yii::$app->request->get('type');
        $this->behaviorName = Yii::$app->request->get('behaviorName');
        $this->galleryId = Yii::$app->request->get('galleryId');
//        $pkNames = call_user_func([$this->types[$this->type], 'primaryKey']);
        $pkValues = explode($this->pkGlue, $this->galleryId);

//        $pk = array_combine($pkNames, $pkValues);

//        $this->owner = call_user_func([$this->types[$this->type], 'findOne'], $pk);
        $this->behavior = new GalleryBehavior();

//        return true;
        switch ($action) {
            case 'delete':
                return $this->actionDelete(Yii::$app->request->post('id'));
                break;
            case 'ajaxUpload':
                return $this->actionAjaxUpload();
                break;
            case 'changeData':
                return $this->actionChangeData(Yii::$app->request->post('photo'));
                break;
            case 'order':
                return $this->actionOrder(Yii::$app->request->post('order'));
                break;
            case 'more':
                return $this->actionMore(Yii::$app->request->post('page'),Yii::$app->request->post('pid'));
                break;
            default:
                throw new HttpException(400, 'Action do not exists');
                break;
        }
    }


    protected function actionMore($page,$pid){
        $limts = 2;
        $images = array();
        foreach ($this->behavior->getImagesMore(($page-1)*$limts,$limts,$pid) as $image) {
            $images[] = array(
                'id' => $image->id,
                'position' => $image->position,
                'label' => (string)$image->label,
                'content' => (string)$image->content,
                'preview' => $this->getUrl($image->id,'preview'),
                'link' => $this->getUrl($image->id,'preview')
            );
        }
        $pageCount = $this->behavior->getImagePageCount($limts,$pid);
        return json_encode(['images'=>$images,'page_count'=>$pageCount]);
    }

    public function getUrl($imageId, $version = 'original')
    {
        return \Yii::$app->settings->get('url\image_base_url') . '/' . $this->getFileName($imageId, $version);
    }

    protected function getFileName($imageId, $version = 'original')
    {
        $cmsblockItem = CmsBlockItem::findOne($imageId);
        $entity = EntityAttachment::findOne(['hash'=>$cmsblockItem->link]);
        \Yii::info('路径222' . $entity->logic_path .$entity->hash . '.' . $entity->extension);
        return $entity->logic_path . '/' .$entity->hash . '.' . $entity->extension;
    }
    

    /**
     * Removes image with ids specified in post request.
     * On success returns 'OK'
     *
     * @param $ids
     *
     * @throws HttpException
     * @return string
     */
    protected function actionDelete($ids)
    {
        Yii::info($ids);
//        $this->behavior->deleteImages($ids);
        CmsBlockItem::deleteAll(['in','id',$ids]);
        return 'OK';
    }

    /**
     * Method to handle file upload thought XHR2
     * On success returns JSON object with image info.
     *
     * @return string
     * @throws HttpException
     */
    public function actionAjaxUpload()
    {

        $imageFile = UploadedFile::getInstanceByName('gallery-image');

        $fileName = $imageFile->tempName;

        $image = $this->behavior->addImage($fileName);

        // not "application/json", because  IE8 trying to save response as a file

        Yii::$app->response->headers->set('Content-Type', 'text/html');

        return Json::encode(
            array(
                'id' => $image->id,
                'rank' => $image->rank,
                'name' => (string)$image->name,
                'description' => (string)$image->description,
                'preview' => $image->getUrl('preview'),
            )
        );
    }

    /**
     * Saves images order according to request.
     *
     * @param array $order new arrange of image ids, to be saved
     *
     * @return string
     * @throws HttpException
     */
    public function actionOrder($order)
    {

        $count = count($order);
        Yii::info('排序顺序' .json_encode($order) . '排序数量' . $count);

        $ids = [];
        $positions = [];
        $index = 1;
        foreach($order as $k => $v){
            $ids[] = $k;
            $positions[] = $index;
            $index++;
//            CmsBlockItem::updateAllCounters(['position'=>$index],['id'=>$k]);
        }

        $idsStr = '';
        $sql = "UPDATE c2_cms_block_item SET position = CASE id ";
        foreach ($ids as $index=> $id) {
            $sql .= sprintf("WHEN %d THEN %d ", $id, $positions[$index]);
            $idsStr .= $id . ',';
        }
        $idsStr = rtrim($idsStr, ",");
        $sql .= "END WHERE id IN ($idsStr)";
        Yii::info($sql);
        Yii::$app->db->createCommand($sql)->execute();
        return true;


    }



    /**
     * Method to update images name/description via AJAX.
     * On success returns JSON array of objects with new image info.
     *
     * @param $imagesData
     *
     * @throws HttpException
     * @return string
     */
    public function actionChangeData($imagesData)
    {
        if (count($imagesData) == 0) {
            throw new HttpException(400, 'Nothing to save');
        }
        $images = $this->behavior->updateImagesData($imagesData);
        $resp = array();
        foreach ($images as $model) {
            $resp[] = array(
                'id' => $model->id,
                'rank' => $model->rank,
                'name' => (string)$model->name,
                'description' => (string)$model->description,
                'preview' => $model->getUrl('preview'),
            );
        }

        return Json::encode($resp);
    }
}
