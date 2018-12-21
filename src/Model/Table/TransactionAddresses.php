<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class TransactionAddressesTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->table('transaction_address');

        $this->addBehavior('SimpleAudit');
    }
}

?>