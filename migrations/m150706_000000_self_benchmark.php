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
class m150706_000000_self_benchmark extends \yii\db\Migration {

    /**
     * @inheritdoc
     */
    public function up() {
        $this->addColumn('{{%audit_trail_entry}}', 'picoseconds_collect_data', Schema::TYPE_INTEGER . ' NOT NULL DEFAULT 0');
        $this->addColumn('{{%audit_trail_entry}}', 'picoseconds_convert_attribute', Schema::TYPE_INTEGER . ' NOT NULL DEFAULT 0');
    }

    /**
     * @inheritdoc
     */
    public function down() {
        $this->dropColumn('{{%audit_trail_entry}}', 'picoseconds_collect_data');
        $this->dropColumn('{{%audit_trail_entry}}', 'picoseconds_convert_attribute');
    }

}
