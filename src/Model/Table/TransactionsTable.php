<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class TransactionsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->primaryKey('id');
        $this->table('transaction');

        $this->addBehavior('SimpleAudit');
    }
}

?>