<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class ClaimsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->setPrimaryKey('id');
        $this->setTable('claim');

        //$this->addBehavior('SimpleAudit');
        $this->addAssociations([
            'belongsTo' => [
                'publisher' => [
                    'className' => 'App\Model\Table\ClaimsTable',
                    'foreignKey' => 'publisher_id',
                    'bindingKey' => 'claim_id',
                    'propertyName' => 'publisher'
                ]
            ]
        ]);
    }
}
?>