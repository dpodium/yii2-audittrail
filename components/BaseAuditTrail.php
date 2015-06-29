<?php

namespace dpodium\yii2\audittrail\components;

use Yii;
use yii\helpers\Html;
use yii\base\InvalidConfigException;
use dpodium\yii2\audittrail\behaviors\AuditTrailBehavior;
use dpodium\yii2\audittrail\helpers\AuditTrailHelper;

/**
 * This widget is the base of the widget that renders the audit trail of a model
 * in the form of a gridview. It can be extended into various implementations for specific
 * audit view.
 * 
 * @author Darren Ng, Dynamic Podium
 * @link http://www.dpodium.com
 * @license MIT
 */
class BaseAuditTrail extends \yii\grid\GridView {

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
     * @var int pageSize audit trail page size
     */
    public $pageSize = 10;

    /**
     * @var array Map of attributes to Closure or direct GridView formatter which will be used to format values display in GridView.
     * 
     * If using Closure, method signature is function($value) { return String }
     */
    public $attributeOutput = [];

    /**
     * Prepares the default column configuration for the grid view
     * 
     * @return mixed the default column configuration for the gridview
     */
    protected function createColumnConfig() {
        //prepare column config
        return [
            'happened_at:datetime',
            AuditTrailHelper::createChangeTypeColumnConfig($this->changeTypeCallback),
            AuditTrailHelper::createUserIdColumnConfig($this->userIdCallback),
            AuditTrailHelper::createDataColumnConfig($this->dataTableOptions, $this->dataTableColumnWidths, $this->hiddenAttributes, $this->attributeOutput),
        ];
    }


}
