<?php

namespace dpodium\yii2\audittrail\helpers;

use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\helpers\Html;
use dpodium\yii2\audittrail\behaviors\AuditTrailBehavior;

class AuditTrailHelper {

    public static function createPrimaryKeyJson($model) {
        //fetch the models pk
        $pk = $model->primaryKey();

        //assert that a valid pk was received
        if ($pk === null || !is_array($pk) || count($pk) == 0) {
            $msg = Yii::t('app', 'Invalid primary key definition: please provide a pk-definition for table {table}', ['table' => $model->tableName()]);
            throw new InvalidConfigException($msg);
        }

        //create final array and return it
        $arrPk = [];
        foreach ($pk as $pkCol) {
            $arrPk[$pkCol] = $model->{$pkCol};
        }
        return Json::encode($arrPk);
    }

    /**
     * Create the change type column configuration in yii2 GridView format
     * 
     * @param Closure $formatCallback to format the string, null means no format
     * @return array the configuration
     */
    public static function createChangeTypeColumnConfig($formatCallback) {
        return [
            'attribute' => 'type',
            'format' => $formatCallback === null ? 'text' : 'raw',
            'value' => function ($model, $key, $index, $column) use ($formatCallback) {
                if ($formatCallback === null) {
                    return $model->type;
                } else {
                    return call_user_func($formatCallback, $model->type, $model);
                }
            },
        ];
    }

    /**
     * Create the user id column configuration in yii2 GridView format
     * 
     * @param Closure $formatCallback to format the string, null means no format
     * @return array the configuration
     */
    public static function createUserIdColumnConfig($formatCallback) {
        return [
            'attribute' => 'user_id',
            'format' => $formatCallback === null ? 'text' : 'raw',
            'value' => function ($model, $key, $index, $column) use ($formatCallback) {
                if ($formatCallback === null) {
                    return $model->user_id;
                } else {
                    return call_user_func($formatCallback, $model->user_id, $model);
                }
            },
        ];
    }

    /**
     * Create the data column's config
     * 
     * @param array $dataTableOptions option settings for the table view
     * @param array $dataTableColumnWidths column width for the internal view
     * @param array $hiddenAttributes attributes which will be hidden
     * @param Closure[] / string[] $outputAttributes output formatter to format the attributes
     * @return null
     */
    public static function createDataColumnConfig($dataTableOptions, $dataTableColumnWidths, $hiddenAttributes, $outputAttributes) {
        return [
            'attribute' => 'data',
            'format' => 'raw',
            'value' => function($model, $key, $index, $column) use ($dataTableOptions, $dataTableColumnWidths, $hiddenAttributes, $outputAttributes) {
                return self::renderDataTable($model, $dataTableOptions, $dataTableColumnWidths, $hiddenAttributes, $outputAttributes);
            },
        ];
    }

    public static function renderDataTable($model, $dataTableOptions, $dataTableColumnWidths, $hiddenAttributes, $outputAttributes) {
        //catch empty data
        $changes = $model->changes;
        if ($changes === null || count($changes) == 0) {
            return null;
        }

        $ret = Html::beginTag('table', $dataTableOptions);

        //colgroup
        $ret .= Html::beginTag('colgroup');
        $widths = $dataTableColumnWidths;
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
            if (in_array($change['attr'], $hiddenAttributes))
                continue;
            //render data row
            $ret .= Html::beginTag('tr');
            $ret .= Html::tag('td', self::formatAttr($model, $change['attr']));
            if ($model->type === AuditTrailBehavior::AUDIT_TYPE_UPDATE) {
                $ret .= Html::tag('td', self::formatValue($change['attr'], $change['from'], $outputAttributes));
            }
            $ret .= Html::tag('td', self::formatValue($change['attr'], $change['to'], $outputAttributes));
            $ret .= Html::endTag('tr');
        }
        $ret .= Html::endTag('tbody');

        $ret .= Html::endTag('table');

        return $ret;
    }

    /**
     * Gets the attribute label
     * 
     * @param \dpodium\yii2\audittrail\models\AuditTrailEntry the entry for the audit trail record
     * @param string the attr key
     * @return string the attr name
     */
    public static function formatAttr($entry, $attr) {
        $model = isset($entry->model_type) && class_exists($entry->model_type) ? new $entry->model_type() : null;
        return isset($model) ? $model->getAttributeLabel($attr) : $attr;
    }

    /**
     * Formats a value into its final output. If the value is null, the formatters null-display is used.
     * If there is a value and nothing is declared in attributeOutput, the raw value is returned. If an
     * output is defined (either a format-string or a closure, it is used for formatting.
     * 
     * @param string $attrName name of the attribute
     * @param mixed $value the value
     * @throws InvalidConfigException if the attributeOutput for this attribute is not a string or closure
     * @return mixed the formatted output value
     */
    public static function formatValue($attrName, $value, $outputAttributes) {
        //check if there is a formatter defined
        if (isset($outputAttributes[$attrName])) {
            $attrOutput = $outputAttributes[$attrName];

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

}
