<?php

namespace dpodium\yii2\audittrail\models;

use dpodium\yii2\audittrail\helpers\AuditTrailHelper;

/**
 * Query class for audit trail entries
 *
 * @author Darren Ng, Dynamic Podium
 * @link http://www.dpodium.com
 * @license MIT
 */
class AuditTrailEntryQuery extends \yii\db\ActiveQuery {

    /**
     * Prepares the query with basic queries
     * 
     * @param \yii\db\ActiveRecord $subject the subject which the audit trail is keeping track of
     */
    public function prepareQuery($subject) {
        if (isset($subject)) {
            $this->andWhere(['model_type' => $subject::className(), 'foreign_pk' => static::createPrimaryKeyJson($subject)]);
        }
        return $this;
    }

    protected static function createPrimaryKeyJson($model) {
        return AuditTrailHelper::createPrimaryKeyJson($model);
    }

}
