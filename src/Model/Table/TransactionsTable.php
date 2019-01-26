<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class TransactionsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->setPrimaryKey('id');
        $this->setTable('transaction');

        $this->addBehavior('SimpleAudit');
    }
}

?>