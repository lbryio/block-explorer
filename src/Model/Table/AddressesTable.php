<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class AddressesTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->primaryKey('Id');
        $this->table('Addresses');

        $this->addBehavior('SimpleAudit');
    }
}

?>