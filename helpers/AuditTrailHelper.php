<?php

namespace dpodium\yii2\audittrail\helpers;

use yii\base\InvalidConfigException;
use yii\helpers\Json;

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

}
