<?php

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Claim extends Entity {
    function getLbryLink() {
        $link = $this->Name;
        if (isset($this->Publisher->Name)) {
            $link = $this->Publisher->Name . '/' . $link;
        }
        $link = 'lbry://' . $link;
        return $link;
    }

    function getExplorerLink() {
        $link = '/claims/' . $this->ClaimId;
        return $link;
    }
    
    function getContentTag() {
        $ctTag = null;
        if (substr($this->ContentType, 0, 5) === 'audio') {
            $ctTag = 'audio';
        } else if (substr($this->ContentType, 0, 5) === 'video') {
            $ctTag = 'video';
        } else if (substr($this->ContentType, 0, 5) === 'image') {
            $ctTag = 'image';
        }
    
        if (!$ctTag && $this->ClaimType == 1) {
            $ctTag = 'identity';
        }
        return $ctTag;
    }
    
    function getAutoThumbText() {
        $autoThumbText = '';
        if ($this->ClaimType == 1) { 
            $autoThumbText = strtoupper(substr($this->Name, 1, min( strlen($this->Name), 3 ))); 
        } else {
            $str = (strlen(trim($this->Title)) > 0) ? $this->Title : $this->Name;
            $autoThumbText = strtoupper(substr($str, 0, min (strlen($str), 2 )));
        }
        return $autoThumbText;
    }
}

?>
