<?php

namespace App\Model\Table;

use Cake\ORM\Table;

class InputsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);

        $this->setPrimaryKey('id');
        $this->setTable('input');

        $this->addBehavior('SimpleAudit');

        $this->addAssociations([
            'belongsToMany' => [
                'input_addresses' => [
                    'className' => 'App\Model\Table\AddressesTable',
                    'joinTable' => 'input_addresses',
                    'foreignKey' => 'input_id',
                    'targetForeignKey' => 'address_id',
                    'propertyName' => 'input_addresses'
                ]
            ]
        ]);
    }
}

?>