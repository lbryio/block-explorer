<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class ClaimStreamsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->primaryKey('Id');
        $this->table('ClaimStreams');

        //$this->addBehavior('SimpleAudit');
    }
}

?>