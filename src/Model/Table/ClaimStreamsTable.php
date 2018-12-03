<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class ClaimStreamsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->setPrimaryKey('Id');
        $this->setTable('ClaimStreams');

        //$this->addBehavior('SimpleAudit');
    }
}