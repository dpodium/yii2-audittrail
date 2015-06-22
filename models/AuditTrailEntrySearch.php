<?php

namespace dpodium\yii2\audittrail\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use dpodium\yii2\audittrail\behaviors\AuditTrailBehavior;

/**
 * Search class for audit trail entries
 *
 * @author Darren Ng, Dynamic Podium
 * @link http://www.dpodium.com
 * @license MIT
 */
class AuditTrailEntrySearch extends \dpodium\yii2\audittrail\models\AuditTrailEntry {

	public function rules() {
		return [
			[['id', 'user_id'], 'integer'],
			[['id', 'model_type', 'user_id', 'type', 'change_remark', 'user_ipaddress'], 'safe'],
			[['type'], 'in', 'range' => AuditTrailBehavior::$AUDIT_TYPES],
		];
	}

	/**
	 * @inheritdoc
	 */
	public function scenarios() {
		//bypass scenarios() implementation in the parent class
		return Model::scenarios();
	}

	/**
	 * Creates the data-provider for searching audit trails
	 *
	 * @param mixed $params the params as used by yiis search methods
	 * @param \yii\db\ActiveRecord $subject the model to get the audit trail entries for
	 * @return \yii\data\ActiveDataProvider
	 */
	public function search($params, $pageSize, $subject = null) {
		//prepare data provider
		$query = $this->buildSearchQuery($subject);
		$dataProvider = new ActiveDataProvider([
			'query' => $query,
			'pagination' => [
				'pageSize' => $pageSize,
			],
		]);

		//if no query data, return it
		if (!($this->load($params) && $this->validate())) {
			return $dataProvider;
		}

		$this->applyFilter($query);

		return $dataProvider;
	}

	protected function applyFilter($query) {
		//apply filtering
		$query->andFilterWhere([
			'id' => $this->id,
			'happened_at' => $this->happened_at,
			'user_id' => $this->user_id,
			'type' => $this->type,
		]);
		$query
				->andFilterWhere(['like', 'foreign_pk', $this->foreign_pk])
				->andFilterWhere(['like', 'user_ipaddress', $this->user_ipaddress])
				->andFilterWhere(['like', 'change_remark', $this->change_remark]);
	}

	protected function buildSearchQuery($subject) {
		return AuditTrailEntry::find()->prepareQuery($subject);
	}

}
