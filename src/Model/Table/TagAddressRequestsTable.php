<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class TagAddressRequestsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->setPrimaryKey('Id');
        $this->setTable('TagAddressRequests');

        $this->addBehavior('SimpleAudit');
    }
}