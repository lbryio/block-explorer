<?php

namespace App\Model\Entity;

use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;

class Claim extends Entity {
    function getLbryLink() {
        $link = $this->name;
        $ClaimModel = TableRegistry::get('Claims');
        $publisher = $ClaimModel->find()->select(['name'])->where(['claim_id' => $this->publisher_id])->first();
        
        if (isset($publisher->name)) {
            $link = $publisher->name . '/' . $link;
        }
        $link = 'lbry://' . $link;
        return $link;
    }

    function getExplorerLink() {
        $link = '/claims/' . $this->claim_id;
        return $link;
    }
    
    function getContentTag() {
        $ctTag = null;
        if (substr($this->content_type, 0, 5) === 'audio') {
            $ctTag = 'audio';
        } else if (substr($this->content_type, 0, 5) === 'video') {
            $ctTag = 'video';
        } else if (substr($this->content_type, 0, 5) === 'image') {
            $ctTag = 'image';
        }
    
        if (!$ctTag && $this->claim_type == 1) {
            $ctTag = 'identity';
        }
        return $ctTag;
    }
    
    function getAutoThumbText() {
        $autoThumbText = '';
        if ($this->claim_type == 1) { 
            $autoThumbText = strtoupper(substr($this->name, 1, min( strlen($this->name), 3 ))); 
        } else {
            $str = (strlen(trim($this->title)) > 0) ? $this->title : $this->name;
            $autoThumbText = strtoupper(substr($str, 0, min (strlen($str), 2 )));
        }
        return $autoThumbText;
    }
}

?>
