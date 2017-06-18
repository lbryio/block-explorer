<?php

namespace App\Controller;

use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;

class ClaimsController extends AppController {
    public function apibrowse() {
        $this->autoRender = false;
        $this->loadModel('Claims');

        $pageLimit = 48;
        $beforeId = intval($this->request->query('before'));
        $afterId = intval($this->request->query('after'));
        $sort = trim($this->request->query('sort'));
        $nsfw = trim($this->request->query('nsfw'));
        switch ($sort) {
            case 'popular':
                // TODO: sort by upvote/downvote score
                break;
            case 'random':
                $order = ['RAND()' => 'ASC'];
                break;
            case 'oldest':
                $order = ['Claims.Created' => 'ASC'];
                break;
            case 'newest':
            default:
                $order = ['Claims.Created' => 'DESC'];
                break;
        }

        $conn = ConnectionManager::get('default');
        $stmt = $conn->execute('SELECT COUNT(Id) AS Total FROM Claims WHERE ThumbnailUrl IS NOT NULL AND LENGTH(TRIM(ThumbnailUrl)) > 0');
        $count = $stmt->fetch(\PDO::FETCH_OBJ);
        $numClaims = $count->Total;

        if ($beforeId < 0) {
            $beforeId = 0;
        }

        $conditions = ['Claims.ThumbnailUrl IS NOT' => null, 'LENGTH(TRIM(Claims.ThumbnailUrl)) >' => 0];
        if ($afterId > 0) {
            $conditions['Claims.Id >'] = $afterId;
        } else if ($beforeId) {
            $conditions['Claims.Id <'] = $beforeId;
        }

        if ($nsfw !== 'true') {
            $conditions['Claims.IsNSFW <>'] = 1;
        }

        $claims = $this->Claims->find()->contain(['Stream', 'Publisher' => ['fields' => ['Name']]])->distinct(['Claims.ClaimId'])->where($conditions)->
            limit($pageLimit)->order($order)->toArray();

        return $this->_jsonResponse(['success' => true, 'claims' => $claims, 'total' => (int) $numClaims]);
    }
}

?>