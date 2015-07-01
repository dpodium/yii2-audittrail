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
class m150701_000000_url_column extends \yii\db\Migration {

    /**
     * @inheritdoc
     */
    public function up() {

        $this->addColumn('{{%audit_trail_entry}}', 'url', Schema::TYPE_STRING . ' NULL');
    }

    /**
     * @inheritdoc
     */
    public function down() {
        $this->dropColumn('{{%audit_trail_entry}}', 'url');
    }

}
