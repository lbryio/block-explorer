<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class TransactionAddressesTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->setTable('transaction_address');
        $this->addBehavior('SimpleAudit');
    }
}

?>