<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class TagAddressRequestsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->primaryKey('Id');
        $this->table('TagAddressRequests');

        $this->addBehavior('SimpleAudit');
    }
}

?>