<?php

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Claim extends Entity {
    function getLink() {
        $link = $this->Name;
        if (isset($this->Publisher->Name)) {
            $link = $this->Publisher->Name . '/' . $link;
        }
        $link = 'lbry://' . $link;
        return $link;
    }
}

?>