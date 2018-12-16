<?php

namespace App\Model\Entity;

use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;


class Transaction extends Entity {
    public function value() {
        $OutputModel = TableRegistry::get('Outputs');
        $outputs = $OutputModel->find()->select(['value'])->where(['transaction_id' => $this->id])->toArray();
        $value = 0;
        foreach($outputs as $o) {
            $value += $o->value;
        }
        return $value;
    }

}

?>