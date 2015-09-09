<?php

use yii\db\Schema;
use yii\db\Expression;

/**
 * Migration to change ID from INT to BIGINT
 * 
 * @author Darren Ng, Dynamic Podium
 * @link http://www.dpodium.com
 * @license MIT
 */
class m150706_000000_alter_id_to_bigint extends \yii\db\Migration {

    /**
     * @inheritdoc
     */
    public function up() {
        $this->alterColumn('{{%audit_trail_entry}}', 'id', Schema::TYPE_BIGINT . " auto_increment");
    }

    /**
     * @inheritdoc
     */
    public function down() {
        $this->alterColumn('{{%audit_trail_entry}}', 'id', Schema::TYPE_INT . " auto_increment");
    }

}
