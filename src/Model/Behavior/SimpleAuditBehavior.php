<?php

namespace App\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\Event\Event;
use Cake\Routing\Router;

class SimpleAuditBehavior extends Behavior {
    private static $DefaultUser = '0';

    private $_userField = 'Id';

    protected $_defaultConfig = [
        'abortOnUserInvalid'    => false,
        'implementedMethods'    => ['audit' => 'audit'],
        'fieldMap' => [
            'CreatedOn'         => 'Created',
            'ModifiedOn'        => 'Modified',
            'CreatedBy'         => 'CreatedBy',
            'ModifiedBy'        => 'ModifiedBy'
        ]
    ];

    public function audit(Entity $entity, $systemOperation = false) {
        $time = $this->_currentUtcTime()->format('Y-m-d H:i:s');
        $user = ($systemOperation) ? self::$DefaultUser : $this->_currentUser();

        if (!$systemOperation
            && $this->_config['abortOnUserInvalid']
            && $user == self::$DefaultUser)
        {
            return false;
        }

        $fieldMap = $this->_config['fieldMap'];

        if ($entity->isNew()) {
            $entity->set($fieldMap['CreatedOn'], $time);
            $entity->set($fieldMap['CreatedBy'], $user);
        }

        $entity->set($fieldMap['ModifiedOn'], $time);
        $entity->set($fieldMap['ModifiedBy'], $user);
        return true;
    }

    public function beforeSave(Event $event, Entity $entity) {
        return $this->audit($entity);
    }

    private function _currentUtcTime() {
        return new \DateTime('now', new \DateTimeZone('UTC'));
    }

    private function _currentUser() {
        $request = Router::getRequest(true);
        if ($request) {
            $session = $request->session();
            $fieldValue = $session->read(sprintf('Auth.User.' . $this->_userField));
            return (intval($fieldValue) > 0) ? $fieldValue : self::$DefaultUser;
        }
    }
}

?>