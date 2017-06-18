<?php

namespace App\Controller;

use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;

class ClaimsController extends AppController {
    public function apirecent() {
        $this->autoRender = false;
        $this->loadModel('Claims');

        $offset = 0;
        $pageLimit = 24;
        $page = intval($this->request->query('page'));

        $conn = ConnectionManager::get('default');
        $stmt = $conn->execute('SELECT COUNT(Id) AS Total FROM Claims WHERE ThumbnailUrl IS NOT NULL AND LENGTH(TRIM(ThumbnailUrl)) > 0');
        $count = $stmt->fetch(\PDO::FETCH_OBJ);
        $numClaims = $count->Total;

        $numPages = ceil($numClaims  / $pageLimit);
        if ($page < 1) {
            $page = 1;
        }
        if ($page > $numPages) {
            $page = $numPages;
        }

        $offset = ($page - 1) * $pageLimit;
        $claims = $this->Claims->find()->contain(['Stream', 'Publisher' => ['fields' => ['Name']]])->where(
                ['Claims.ThumbnailUrl IS NOT' => null, 'LENGTH(TRIM(Claims.ThumbnailUrl)) >' => 0])->
            order(['Claims.Created' => 'DESC'])->offset($offset)->limit($pageLimit)->toArray();

        return $this->_jsonResponse(['success' => true, 'claims' => $claims, 'num_pages' => $numPages, 'total' => (int) $numClaims]);
    }
}

?>