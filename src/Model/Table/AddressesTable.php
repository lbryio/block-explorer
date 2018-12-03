<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class AddressesTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->setPrimaryKey('Id');
        $this->setTable('Addresses');

        $this->addBehavior('SimpleAudit');
    }
}

?>