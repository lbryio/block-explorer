<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class OutputsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->primaryKey('id');
        $this->table('output');

        $this->addBehavior('SimpleAudit');

        $this->addAssociations([
            'belongsTo' => [
                'spend_input' => [
                    'className' => 'App\Model\Table\InputsTable',
                    'foreignKey' => 'spent_by_input_id',
                    'propertyName' => 'spend_input'
                ]
            ],
            'belongsToMany' => [
                'output_addresses' => [
                    'className' => 'App\Model\Table\AddressesTable',
                    'joinTable' => 'output_addresses',
                    'foreignKey' => 'output_id',
                    'targetForeignKey' => 'address_id',
                    'propertyName' => 'output_addresses'
                ]
            ]
        ]);
    }
}

?>