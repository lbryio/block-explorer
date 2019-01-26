<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class BlocksTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->setPrimaryKey('id');
        $this->setTable('block');

        $this->addBehavior('SimpleAudit');
    }
}

?>