<?php

use yii\db\Schema;
use yii\db\Expression;

/**
 * Migration to add URL column
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

        $this->addColumn('{{%audit_trail_entry}}', 'url', Schema::TYPE_TEXT . ' NULL');
    }

    /**
     * @inheritdoc
     */
    public function down() {
        $this->dropColumn('{{%audit_trail_entry}}', 'url');
    }

}
