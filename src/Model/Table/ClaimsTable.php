<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class ClaimsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->primaryKey('Id');
        $this->table('Claims');

        //$this->addBehavior('SimpleAudit');
        $this->addAssociations([
            'belongsTo' => [
                'Publisher' => [
                    'className' => 'App\Model\Table\ClaimsTable',
                    'foreignKey' => 'PublisherId',
                    'bindingKey' => 'ClaimId',
                    'propertyName' => 'Publisher'
                ]
            ]
        ]);
    }
}

?>