<?php

namespace dpodium\yii2\audittrail\widgets;

use Yii;
use yii\base\InvalidConfigException;

/**
 * This widget renders the audit trail of a model in the form of a gridview.
 * Following is an complex configuration for the widget as an example:
 * 
 * <code>
 * AuditTrail::widget([
 * 		'model'=>$model,
 * 		'userIdCallback'=>function ($userId, $model) {
 * 			return User::findOne($userId)->fullname;
 * 		},
 * 		'changeTypeCallback'=>function ($type, $model) {
 * 			return Html::tag('span', strtoupper($type), ['class'=>'label label-info']);
 * 		},
 *		'dataTableOptions'=>['class'=>'table table-condensed table-bordered'],
 * ]);
 * </code>
 * 
 * @author Pascal Mueller, AS infotrack AG
 * @link http://www.asinfotrack.ch
 * @license MIT
 * @extendedby Darren Ng, Dynamic Podium (http://www.dpodium.com)
 */
class AuditTrail extends \dpodium\yii2\audittrail\components\BaseAuditTrail {

    /**
     * @var \dpodium\yii2\audittrail\behaviors\AuditTrailBehavior holds the configuration
     * of the audit trail behavior once loaded or null if not found
     */
    protected $behaviorInstance;

    /**
     * @var \yii\db\ActiveRecord the model to list the audit for. The model
     * MUST implement AuditTrailBehavior!
     */
    public $model;

    /**
     * @var array the data column definitions, following GridView standard of configuration
     */
    public $extraColumns = [];

    /**
     * @var boolean allow to search directly from gridview
     */
    public $allowSearch = true;

    /**
     * @var boolean whether to show change remarks or not. Defaults to false.
     */
    public $showChangeRemarks = false;
    
    /**
     * (non-PHPdoc)
     * @see \yii\grid\GridView::init()
     */
    public function init() {
        //assert model is not null
        if (empty($this->model)) {
            throw new InvalidConfigException(Yii::t('app', 'Model cannot be null!'));
        }

        //assert model has behavior
        $this->behaviorInstance = $this->getBehaviorInstance();
        if ($this->behaviorInstance === null) {
            throw new InvalidConfigException(Yii::t('app', 'Model of type {0} doesn\'t have AuditTrailBehavior!', [ $this->model->className()]));
        }

        //data provider configuration
        $searchModel = new $this->behaviorInstance->searchModelClass();
        $this->setDataProvider($searchModel);
        $this->setSearchModel($searchModel);
        
        $this->attributeOutput = $this->behaviorInstance->attributeOutput;

        //prepare columns of grid view
        $this->columns = $this->createColumnConfig();

        //parent initialization
        parent::init();
    }

    /**
     * Sets the data provider for this gridview
     * 
     * @param \dpodium\yii2\audittrail\models\AuditTrailEntrySearch $searchModel
     */
    protected function setDataProvider($searchModel) {
        $searchParams = $this->searchParams === null ? Yii::$app->request->getQueryParams() : $this->searchParams;
        $this->dataProvider = $searchModel->search($searchParams, $this->pageSize, $this->model);
    }

    /**
     * Sets the search model for this gridview
     * 
     * @param \dpodium\yii2\audittrail\models\AuditTrailEntrySearch $searchModel
     */
    protected function setSearchModel($searchModel) {
        if ($this->allowSearch) {
            $this->filterModel = $searchModel;
        }
    }

    /**
     * @inheritdoc
     */
    protected function createColumnConfig() {
        $column_config = parent::createColumnConfig();
        if ($this->showChangeRemarks) {
            $column_config[] = 'change_remark';
        }
        if (!empty($this->extraColumns)) {
            $col_offset = $this->showChangeRemarks ? 2 : 1;
            array_splice($column_config, count($column_config) - $col_offset, 0, $this->extraColumns);
        }
        return $column_config;
    }

    /**
     * @inheritdoc
     */
    protected function formatAttr($entry, $attr) {
        return $this->model->getAttributeLabel($attr);
    }
    
    
    /**
     * Finds the models audit trail behavior configuration and returns it
     * 
     * @return \dpodium\yii2\audittrail\behaviors\AuditTrailBehavior|null the configuration or null if not found
     */
    protected function getBehaviorInstance() {
        return $this->model->getBehavior('yii2-audittrail');
    }

}
