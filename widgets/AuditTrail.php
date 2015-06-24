<?php

namespace dpodium\yii2\audittrail\widgets;

use Yii;
use yii\helpers\Html;
use yii\base\InvalidConfigException;
use dpodium\yii2\audittrail\behaviors\AuditTrailBehavior;
use dpodium\yii2\audittrail\models\AuditTrailEntrySearch;

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
 * 		'dataTableOptions'=>['class'=>'table table-condensed table-bordered'],
 * ]);
 * </code>
 * 
 * @author Darren Ng, Dynamic Podium
 * @link http://www.dpodium.com
 * @license MIT
 */
class AuditTrail extends \yii\grid\GridView {

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
     * @var mixed the params to use for the search filtering. Defaults to
     * 'Yii::$app->request->getQueryParams()'
     */
    public $searchParams = null;

    /**
     * @var \Closure|null optional closure to render the value of the user_id column.
     * If provided use the format 'function ($userId, $model)' and return the contents 
     * of the cell.
     * 
     * If not set the user id will be render in plain format.
     */
    public $userIdCallback = null;

    /**
     * @var \Closure|null optional closure to render the value of the type column.
     * If provided use the format 'function ($type, $model)' and return the contents
     * of the cell.
     * To see what possible values there are for the type, check out the statics of the
     * class AuditTrailBehavior.
     * 
     * If not set the type will be rendered in plain format.
     */
    public $changeTypeCallback = null;

    /**
     * @var string[] Attributes listed in this array won't be listed in the data table no
     * matter if there were changes in that attribute or not.
     */
    public $hiddenAttributes = [];

    /**
     * @var mixed the options for the inner table displaying the actual changes
     */
    public $dataTableOptions = ['class' => 'table table-condensed table-bordered'];

    /**
     * @var array configuration for the data tables column-widths. Three keys are used:
     * - 'attribute':	width of the first column containing the attribute name
     * - 'from':		width of the from-column
     * - 'to:			width of the to column
     * 
     * Used a string to define this property. The string will be used as the css-width-value
     * of the corresponding '<col>'-tag within the colgroup definition.
     */
    public $dataTableColumnWidths = [
        'attribute' => null,
        'from' => '30%',
        'to' => '30%',
    ];

    /**
     * @var array the data column definitions, following GridView standard of configuration
     */
    public $extraColumns = [];

    /**
     * @var boolean allow to search directly from gridview
     */
    public $allowSearch = true;

    /**
     * @var int pageSize audit trail page size
     */
    public $pageSize = 10;

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
     * Prepares the default column configuration for the grid view
     * 
     * @return mixed the default column configuration for the gridview
     */
    protected function createColumnConfig() {
        //prepare column config
        $column_config = [
            'happened_at:datetime',
            [
                'attribute' => 'type',
                'format' => $this->changeTypeCallback === null ? 'text' : 'raw',
                'value' => function ($model, $key, $index, $column) {
                    if ($this->changeTypeCallback === null) {
                        return $model->type;
                    } else {
                        return call_user_func($this->changeTypeCallback, $model->type, $model);
                    }
                },
            ],
            [
                'attribute' => 'user_id',
                'format' => $this->userIdCallback === null ? 'text' : 'raw',
                'value' => function ($model, $key, $index, $column) {
                    if ($this->userIdCallback === null) {
                        return $model->user_id;
                    } else {
                        return call_user_func($this->userIdCallback, $model->user_id, $model);
                    }
                },
            ],
        ];
        if ($this->showChangeRemarks) {
            $column_config[] = 'change_remark';
        }
        $column_config[] = [
            'attribute' => 'data',
            'format' => 'raw',
            'value' => function($model, $key, $index, $column) {
                return $this->createDataColumnConfig($model, $key, $index, $column);
            },
        ];

        return $this->addExtraColumnIntoConfig($column_config);
    }

    /**
     * Adds configured extra columns into the column configuration
     * 
     * @param array $column_config the currently created config
     */
    protected function addExtraColumnIntoConfig($column_config) {
        if (!empty($this->extraColumns)) {
            $col_offset = $this->showChangeRemarks ? 2 : 1;
            array_splice($column_config, count($column_config) - $col_offset, 0, $this->extraColumns);
        }
        return $column_config;
    }

    /**
     * Create the data column's config
     * 
     * @param \yii\db\ActiveRecord $model
     * @param mixed $key key associated to DataProvider
     * @param int $index index associated to DataProvider
     * @param int $column column index currently rendering
     * @return null
     */
    protected function createDataColumnConfig($model, $key, $index, $column) {
        //catch empty data
        $changes = $model->changes;
        if ($changes === null || count($changes) == 0) {
            return null;
        }

        $ret = Html::beginTag('table', $this->dataTableOptions);

        //colgroup
        $ret .= Html::beginTag('colgroup');
        $widths = $this->dataTableColumnWidths;
        $ret .= Html::tag('col', '', ['style' => sprintf('width: %s;', isset($widths['attribute']) ? $widths['attribute'] : 'auto')]);
        if ($model->type === AuditTrailBehavior::AUDIT_TYPE_UPDATE) {
            $ret .= Html::tag('col', '', ['style' => sprintf('width: %s;', isset($widths['from']) ? $widths['from'] : 'auto')]);
        }
        $ret .= Html::tag('col', '', ['style' => sprintf('width: %s;', isset($widths['to']) ? $widths['to'] : 'auto')]);

        //table head
        $ret .= Html::beginTag('thead');
        $ret .= Html::beginTag('tr');
        $ret .= Html::tag('th', Yii::t('app', 'Attribute'));
        if ($model->type === AuditTrailBehavior::AUDIT_TYPE_UPDATE) {
            $ret .= Html::tag('th', Yii::t('app', 'From'));
        }
        $ret .= Html::tag('th', Yii::t('app', 'To'));
        $ret .= Html::endTag('tr');
        $ret .= Html::endTag('thead');

        //table body
        $ret .= Html::beginTag('tbody');
        foreach ($changes as $change) {
            //skip hidden attributes
            if (in_array($change['attr'], $this->hiddenAttributes))
                continue;
            //render data row
            $ret .= Html::beginTag('tr');
            $ret .= Html::tag('td', $this->model->getAttributeLabel($change['attr']));
            if ($model->type === AuditTrailBehavior::AUDIT_TYPE_UPDATE) {
                $ret .= Html::tag('td', $this->formatValue($change['attr'], $change['from']));
            }
            $ret .= Html::tag('td', $this->formatValue($change['attr'], $change['to']));
            $ret .= Html::endTag('tr');
        }
        $ret .= Html::endTag('tbody');

        $ret .= Html::endTag('table');

        return $ret;
    }

    /**
     * Formats a value into its final outoput. If the value is null, the formatters null-display is used.
     * If there is a value and nothing is declared in attributeOutput, the raw value is returned. If an
     * output is defined (either a format-string or a closure, it is used for formatting.
     * 
     * @param string $attrName name of the attribute
     * @param mixed $value the value
     * @throws InvalidConfigException if the attributeOutput for this attribute is not a string or closure
     * @return mixed the formatted output value
     */
    protected function formatValue($attrName, $value) {
        //check if there is a formatter defined
        if (isset($this->behaviorInstance->attributeOutput[$attrName])) {
            $attrOutput = $this->behaviorInstance->attributeOutput[$attrName];

            //assert attr output format is either a string or a closure
            if (!is_string($attrOutput) && !($attrOutput instanceof \Closure)) {
                throw new InvalidConfigException(Yii::t('app', 'The attribute out put for the attribute {0} is invalid. It needs to be a string or a closure!', $attrName));
            }

            //perform formatting
            if ($attrOutput instanceof \Closure) {
                return call_user_func($attrOutput, $value);
            } else {
                return Yii::$app->formatter->format($value, $attrOutput);
            }
        } else {
            return Yii::$app->formatter->asNText($value);
        }
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
