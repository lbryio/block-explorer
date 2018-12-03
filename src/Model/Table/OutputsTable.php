<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class OutputsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->setPrimaryKey('Id');
        $this->setTable('Outputs');

        $this->addBehavior('SimpleAudit');

        $this->addAssociations([
            'belongsTo' => [
                'SpendInput' => [
                    'className' => 'App\Model\Table\InputsTable',
                    'foreignKey' => 'SpentByInputId',
                    'propertyName' => 'SpendInput'
                ]
            ],
            'belongsToMany' => [
                'OutputAddresses' => [
                    'className' => 'App\Model\Table\AddressesTable',
                    'joinTable' => 'OutputsAddresses',
                    'foreignKey' => 'OutputId',
                    'targetForeignKey' => 'AddressId',
                    'propertyName' => 'OutputAddresses'
                ]
            ]
        ]);
    }
}