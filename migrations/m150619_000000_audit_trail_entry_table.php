<?php

use yii\db\Schema;
use yii\db\Expression;

/**
 * Migration to create or remove audit trail entry table
 * 
 * @author Darren Ng, Dynamic Podium
 * @link http://www.dpodium.com
 * @license MIT
 */
class m150619_000000_audit_trail_entry_table extends \yii\db\Migration
{
	
	/**
	 * @inheritdoc
	 */
	public function up()
	{
		$this->createTable('{{%audit_trail_entry}}', [
			'id'=>Schema::TYPE_PK,
			'model_type'=>Schema::TYPE_STRING . ' NOT NULL',
			'happened_at'=>Schema::TYPE_INTEGER . ' NOT NULL',
			'foreign_pk'=>Schema::TYPE_STRING . ' NOT NULL',
			'user_id'=>Schema::TYPE_INTEGER . ' NULL',
			'user_ipaddress'=>Schema::TYPE_STRING . ' NULL',
			'change_remark'=>Schema::TYPE_TEXT . ' NULL',
			'type'=>Schema::TYPE_STRING . ' NOT NULL',
			'data'=>Schema::TYPE_TEXT . ' NULL',
		]);
		$this->createIndex('IN_audit_trail_entry_fast_access', '{{%audit_trail_entry}}', [
			new Expression('`model_type` ASC'), 
			new Expression('`happened_at` DESC'),
		]);
	}
	
	/**
	 * @inheritdoc
	 */
	public function down()
	{
		$this->dropIndex('IN_audit_trail_entry_fast_access', '{{%audit_trail_entry}}');
		$this->dropTable('{{%audit_trail_entry}}');
	}
	
}
