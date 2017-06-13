<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class InputsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->primaryKey('Id');
        $this->table('Inputs');

        $this->addBehavior('SimpleAudit');

        $this->addAssociations([
            'belongsToMany' => [
                'InputAddresses' => [
                    'className' => 'App\Model\Table\AddressesTable',
                    'joinTable' => 'InputsAddresses',
                    'foreignKey' => 'InputId',
                    'targetForeignKey' => 'AddressId',
                    'propertyName' => 'InputAddresses'
                ]
            ]
        ]);
    }
}

?>