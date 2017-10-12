<?php

namespace App\Controller;

use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Crypto\Signature\Signer;
use Mdanter\Ecc\Serializer\PublicKey\PemPublicKeySerializer;
use Mdanter\Ecc\Serializer\PublicKey\DerPublicKeySerializer;
use Mdanter\Ecc\Serializer\Signature\DerSignatureSerializer;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\LabelAlignment;
use Endroid\QrCode\QrCode;

class MainController extends AppController {

    const rpcurl = 'http://lrpc:lrpc@127.0.0.1:9245';

    const lbcPriceKey = 'lbc.price';

    const bittrexMarketUrl = 'https://bittrex.com/api/v1.1/public/getticker?market=BTC-LBC';

    const blockchainTickerUrl = 'https://blockchain.info/ticker';

    const tagReceiptAddress = 'bLockNgmfvnnnZw7bM6SPz6hk5BVzhevEp';

    protected $redis;

    public function initialize() {
        parent::initialize();
        $this->redis = new \Predis\Client('tcp://127.0.0.1:6379');
        try {
            $this->redis->info('mem');
        } catch (\Predis\Connection\ConnectionException $e) {
            $this->redis = null;
        }
    }

    protected function _getLatestPrice() {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $priceInfo = new \stdClass();
        $priceInfo->time = $now->format('c');

        $shouldRefreshPrice = false;
        if (!$this->redis) {
            $shouldRefreshPrice = true;
        } else {
            if (!$this->redis->exists(self::lbcPriceKey)) {
                $shouldRefreshPrice = true;
            } else {
                $priceInfo = json_decode($this->redis->get(self::lbcPriceKey));
                $lastPriceDt = new \DateTime($priceInfo->time);
                $diff = $now->diff($lastPriceDt);
                $diffMinutes = $diff->i;
                if ($diffMinutes >= 15 || $priceInfo->price == 0) { // 15 minutes (or if the price is 0)
                    $shouldRefreshPrice = true;
                }
            }
        }

        if ($shouldRefreshPrice) {
            $btrxjson = json_decode(self::curl_get(self::bittrexMarketUrl));
            $blckjson = json_decode(self::curl_get(self::blockchainTickerUrl));

            if ($btrxjson->success) {
                $onelbc = $btrxjson->result->Bid;
                $lbcPrice = 0;
                if (isset($blckjson->USD)) {
                    $lbcPrice = $onelbc * $blckjson->USD->buy;
                    if ($lbcPrice > 0) {
                        $priceInfo->price = number_format($lbcPrice, 2, '.', '');
                        $priceInfo->time = $now->format('c');
                        if ($this->redis) {
                            $this->redis->set(self::lbcPriceKey, json_encode($priceInfo));
                        }
                    }
                }
            }
        }

        $lbcUsdPrice = (isset($priceInfo->price) && ($priceInfo->price > 0)) ? '$' . $priceInfo->price : 'N/A';
        return $lbcUsdPrice;
    }

    public function index() {
        $this->loadModel('Blocks');
        $this->loadModel('Claims');

        $lbcUsdPrice = $this->_getLatestPrice();
        $this->set('lbcUsdPrice', $lbcUsdPrice);

        $blocks = $this->Blocks->find()->select(['Chainwork', 'Confirmations', 'Difficulty', 'Hash', 'Height', 'TransactionHashes', 'BlockTime', 'BlockSize'])->
            order(['Height' => 'desc'])->limit(6)->toArray();
        for ($i = 0; $i < count($blocks); $i++) {
            $tx_hashes = json_decode($blocks[$i]->TransactionHashes);
            $blocks[$i]->TransactionCount = count($tx_hashes);
        }

        // hash rate
        $hashRate = $this->_formatHashRate($this->_gethashrate());

        // recent claims
        $claims = $this->Claims->find()->select(['TransactionHash', 'Name', 'Vout', 'ClaimId', 'ClaimType', 'Author', 'Title', 'Description', 'ContentType',
                                                 'IsNSFW', 'Language', 'ThumbnailUrl', 'Created'])->
            distinct(['Claims.ClaimId'])->
            contain(['Publisher' => ['fields' => ['Name']]])->order(['Claims.Created' => 'DESC'])->limit(5)->toArray();

        $this->set('recentBlocks', $blocks);
        $this->set('recentClaims', $claims);
        $this->set('hashRate', $hashRate);
    }

    public function claims($id = null) {
        $this->loadModel('Claims');
        $this->loadModel('Transactions');

        $canConvert = false;
        $priceInfo = json_decode($this->redis->get(self::lbcPriceKey));
        if (isset($priceInfo->price)) {
            $canConvert = true;
        }

        if (!$id) {
            // paginate claims
            $offset = 0;
            $pageLimit = 96;
            $page = intval($this->request->query('page'));

            $conn = ConnectionManager::get('default');
            $stmt = $conn->execute('SELECT COUNT(Id) AS Total FROM Claims');
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
            $claims = $this->Claims->find()->distinct(['Claims.ClaimId'])->contain(['Stream', 'Publisher' => ['fields' => ['Name']]])->order(['Claims.Created' => 'DESC'])->offset($offset)->limit($pageLimit)->toArray();
            for ($i = 0; $i < count($claims); $i++) {
                if ($canConvert && $claims[$i]->Fee > 0 && $claims[$i]->FeeCurrency == 'USD') {
                    $claims[$i]->Price = $claims[$i]->Fee / $priceInfo->price;
                }

                if (isset($claims[$i]->Stream)) {
                    $json = json_decode($claims[$i]->Stream->Stream);
                    if (isset($json->metadata->license)) {
                        $claims[$i]->License = $json->metadata->license;
                    }
                    if (isset($json->metadata->licenseUrl)) {
                        $claims[$i]->LicenseUrl = $json->metadata->licenseUrl;
                    }
                }
            }

            $this->set('pageLimit', $pageLimit);
            $this->set('numPages', $numPages);
            $this->set('numRecords', $numClaims);
            $this->set('currentPage', $page);
            $this->set('claims', $claims);
        } else {
            $claim = $this->Claims->find()->contain(['Stream', 'Publisher' => ['fields' => ['ClaimId', 'Name']]])->where(['Claims.ClaimId' => $id])->order(['Claims.Created' => 'DESC'])->first();
            if (!$claim) {
                return $this->redirect('/');
            }

            if ($canConvert && $claim->Fee > 0 && $claim->FeeCurrency == 'USD') {
                $claim->Price = $claim->Fee / $priceInfo->price;
            }

            if (isset($claim->Stream)) {
                $json = json_decode($claim->Stream->Stream);
                if (isset($json->metadata->license)) {
                    $claim->License = $json->metadata->license;
                }
                if (isset($json->metadata->licenseUrl)) {
                    $claim->LicenseUrl = $json->metadata->licenseUrl;
                }
            }

            $moreClaims = [];
            if (isset($claim->Publisher) || $claim->ClaimType == 1) {
                // find more claims for the publisher
                $moreClaims = $this->Claims->find()->contain(['Stream', 'Publisher' => ['fields' => ['Name']]])->
                    where(['Claims.ClaimType' => 2, 'Claims.Id <>' => $claim->Id, 'Claims.PublisherId' => isset($claim->Publisher) ? $claim->Publisher->ClaimId : $claim->ClaimId])->
                    limit(9)->order(['Claims.Fee' => 'DESC', 'RAND()' => 'DESC'])->toArray();
                for ($i = 0; $i < count($moreClaims); $i++) {
                    if ($canConvert && $moreClaims[$i]->Fee > 0 && $moreClaims[$i]->FeeCurrency == 'USD') {
                        $moreClaims[$i]->Price = $moreClaims[$i]->Fee / $priceInfo->price;
                    }

                    if (isset($moreClaims[$i]->Stream)) {
                        $json = json_decode($moreClaims[$i]->Stream->Stream);
                        if (isset($json->metadata->license)) {
                            $moreClaims[$i]->License = $json->metadata->license;
                        }
                        if (isset($json->metadata->licenseUrl)) {
                            $moreClaims[$i]->LicenseUrl = $json->metadata->licenseUrl;
                        }
                    }
                }
            }

            $this->set('claim', $claim);
            $this->set('moreClaims', $moreClaims);
        }
    }

    public function realtime() {
        $this->loadModel('Blocks');
        $this->loadModel('Transactions');

        // load 10 blocks and transactions
        $conn = ConnectionManager::get('default');
        $blocks = $this->Blocks->find()->select(['Height', 'BlockTime', 'TransactionHashes'])->order(['Height' => 'desc'])->limit(10)->toArray();
        for ($i = 0; $i < count($blocks); $i++) {
            $tx_hashes = json_decode($blocks[$i]->TransactionHashes);
            $blocks[$i]->TransactionCount = count($tx_hashes);
        }

        $stmt = $conn->execute('SELECT T.Hash, T.InputCount, T.OutputCount, T.Value, IFNULL(T.TransactionTime, T.CreatedTime) AS TxTime ' .
                               'FROM Transactions T ORDER BY CreatedTime DESC LIMIT 10');
        $txs = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $this->set('blocks', $blocks);
        $this->set('txs', $txs);
    }

    public function apiblocksize($timePeriod = '24h') {
        $this->autoRender = false;

        if (!$this->request->is('get')) {
            return $this->_jsonError('Invalid HTTP request method.', 400);
        }

        $validPeriods = ['24h', '72h', '168h', '30d', '90d', '1y'];
        if (!in_array($timePeriod, $validPeriods)) {
            return $this->_jsonError('Invalid time period specified.', 400);
        }

        $isHourly = (strpos($timePeriod, 'h') !== false);
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $dateFormat = $isHourly ? 'Y-m-d H:00:00' : 'Y-m-d';
        $sqlDateFormat = $isHourly ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';
        $intervalPrefix = $isHourly ? 'PT' : 'P';
        $start = $now->sub(new \DateInterval($intervalPrefix . strtoupper($timePeriod)));

        $resultSet = [];

        $conn = ConnectionManager::get('default');

        // get avg block sizes for the time period
        $stmt = $conn->execute("SELECT AVG(BlockSize) AS AvgBlockSize, DATE_FORMAT(FROM_UNIXTIME(BlockTime), '$sqlDateFormat') AS TimePeriod " .
                               "FROM Blocks WHERE DATE_FORMAT(FROM_UNIXTIME(BlockTime), '$sqlDateFormat') >= ? GROUP BY TimePeriod ORDER BY TimePeriod ASC", [$start->format($dateFormat)]);
        $avgBlockSizes = $stmt->fetchAll(\PDO::FETCH_OBJ);
        foreach ($avgBlockSizes as $size) {
            if (!isset($resultSet[$size->TimePeriod])) {
                $resultSet[$size->TimePeriod] = [];
            }
            $resultSet[$size->TimePeriod]['AvgBlockSize'] = (float) $size->AvgBlockSize;
        }

        // get avg prices
        $stmt = $conn->execute("SELECT AVG(USD) AS AvgUSD, DATE_FORMAT(Created, '$sqlDateFormat') AS TimePeriod " .
                               "FROM PriceHistory WHERE DATE_FORMAT(Created, '$sqlDateFormat') >= ? GROUP BY TimePeriod ORDER BY TimePeriod ASC", [$start->format($dateFormat)]);
        $avgPrices = $stmt->fetchAll(\PDO::FETCH_OBJ);
        foreach ($avgPrices as $price) {
            if (!isset($resultSet[$price->TimePeriod])) {
                $resultSet[$price->TimePeriod] = [];
            }
            $resultSet[$price->TimePeriod]['AvgUSD'] = (float) $price->AvgUSD;
        }

        return $this->_jsonResponse(['success' => true, 'data' => $resultSet]);
    }

    public function apirealtimeblocks() {
        // load 10 blocks
        $this->autoRender = false;
        $this->loadModel('Blocks');
        $blocks = $this->Blocks->find()->select(['Height', 'BlockTime', 'TransactionHashes'])->order(['Height' => 'desc'])->limit(10)->toArray();
        for ($i = 0; $i < count($blocks); $i++) {
            $tx_hashes = json_decode($blocks[$i]->TransactionHashes);
            $blocks[$i]->TransactionCount = count($tx_hashes);
            unset($blocks[$i]->TransactionHashes);
        }

        $this->_jsonResponse(['success' => true, 'blocks' => $blocks]);
    }

    public function apirealtimetx() {
        // load 10 transactions
        $this->autoRender = false;
        $conn = ConnectionManager::get('default');
        $stmt = $conn->execute('SELECT T.Hash, T.InputCount, T.OutputCount, T.Value, IFNULL(T.TransactionTime, T.CreatedTime) AS TxTime ' .
                               'FROM Transactions T ORDER BY CreatedTime DESC LIMIT 10');
        $txs = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $this->_jsonResponse(['success' => true, 'txs' => $txs]);
    }

    protected function _formatHashRate($value) {
        if ($value === 'N/A') {
            return $value;
        }

        /*if ($value > 1000000000000) {
            return number_format( $value / 1000000000000, 2, '.', '' ) . ' TH';
        }*/
        if ($value > 1000000000) {
            return number_format( $value / 1000000000, 2, '.', '' ) . ' GH/s';
        }
        if ($value > 1000000) {
            return number_format( $value / 1000000, 2, '.', '' ) . ' MH/s';
        }
        if ($value > 1000) {
            return number_format( $value / 1000, 2, '.', '' ) . ' KH/s';
        }

        return number_format($value) . ' H/s';
    }

    public function find() {
        $criteria = $this->request->query('q');
        if ($criteria === null || strlen(trim($criteria)) == 0) {
            return $this->redirect('/');
        }

        $this->loadModel('Blocks');
        $this->loadModel('Claims');
        $this->loadModel('Addresses');
        $this->loadModel('Transactions');

        if (is_numeric($criteria)) {
            $height = (int) $criteria;
            $block = $this->Blocks->find()->select(['Id'])->where(['Height' => $height])->first();
            if ($block) {
                return $this->redirect('/blocks/' . $height);
            }
        } else if (strlen(trim($criteria)) === 34) {
            // Address
            $address = $this->Addresses->find()->select(['Id', 'Address'])->where(['Address' => $criteria])->first();
            if ($address) {
                return $this->redirect('/address/' . $address->Address);
            }
        } else if (strlen(trim($criteria)) === 40) {
            // Claim ID
            $claim = $this->Claims->find()->select(['ClaimId'])->where(['ClaimId' => $criteria])->first();
            if ($claim) {
                return $this->redirect('/claim/' . $claim->ClaimId);
            }
        } else if (strlen(trim($criteria)) === 64) { // block or tx hash
            // Try block hash first
            $block = $this->Blocks->find()->select(['Height'])->where(['Hash' => $criteria])->first();
            if ($block) {
                return $this->redirect('/blocks/' . $block->Height);
            } else {
                $tx = $this->Transactions->find()->select(['Hash'])->where(['Hash' => $criteria])->first();
                if ($tx) {
                    return $this->redirect('/tx/' . $tx->Hash);
                }
            }
        } else {
            // finally, try exact claim name match
            $claim = $this->Claims->find()->select(['ClaimId'])->where(['Name' => $criteria])->first();
            if ($claim) {
                return $this->redirect('/claims/' . $claim->ClaimId);
            }
        }

        // Not found, redirect to index
        return $this->redirect('/');
    }

    public function blocks($height = null) {
        $this->loadModel('Blocks');

        if ($height === null) {
            // paginate blocks
            $offset = 0;
            $pageLimit = 50;
            $page = intval($this->request->query('page'));

            $conn = ConnectionManager::get('default');
            $stmt = $conn->execute('SELECT COUNT(Id) AS Total FROM Blocks');
            $count = $stmt->fetch(\PDO::FETCH_OBJ);
            $numBlocks = $count->Total;

            $numPages = ceil($numBlocks  / $pageLimit);
            if ($page < 1) {
                $page = 1;
            }
            if ($page > $numPages) {
                $page = $numPages;
            }

            $offset = ($page - 1) * $pageLimit;
            $blocks = $this->Blocks->find()->offset($offset)->limit($pageLimit)->order(['Height' => 'DESC'])->toArray();
            $this->set('blocks', $blocks);
            $this->set('pageLimit', $pageLimit);
            $this->set('numPages', $numPages);
            $this->set('numRecords', $numBlocks);
            $this->set('currentPage', $page);
        } else {
            $this->loadModel('Transactions');
            $height = intval($height);
            if ($height < 0) {
                return $this->redirect('/');
            }

            $block = $this->Blocks->find()->where(['Height' => $height])->first();
            if (!$block) {
                return $this->redirect('/');
            }

            try {
                // update the block confirmations
                $req = ['method' => 'getblock', 'params' => [$block->Hash]];
                $response = self::curl_json_post(self::rpcurl, json_encode($req));
                $json = json_decode($response);
                $rpc_block = $json->result;
                if (isset($rpc_block->confirmations)) {
                    $block->Confirmations = $rpc_block->confirmations;
                    $conn = ConnectionManager::get('default');
                    $conn->execute('UPDATE Blocks SET Confirmations = ? WHERE Id = ?', [$rpc_block->confirmations, $block->Id]);
                }
            } catch (\Exception $e) {
                // try again next time
            }

            // Get the basic block transaction info
            $txs = $this->Transactions->find()->select(['InputCount', 'OutputCount', 'Hash', 'Value', 'Version'])->where(['BlockHash' => $block->Hash])->toArray();

            $this->set('block', $block);
            $this->set('blockTxs', $txs);
        }
    }

    public function tx($hash = null) {
        $this->loadModel('Blocks');
        $this->loadModel('Transactions');
        $this->loadModel('Inputs');
        $this->loadModel('Outputs');
        $sourceAddress = $this->request->query('address');

        $tx = $this->Transactions->find()->select(
            ['Id', 'BlockHash', 'InputCount', 'OutputCount', 'Hash', 'Value', 'TransactionTime', 'TransactionSize', 'Created', 'Version', 'LockTime', 'Raw'])->where(['Hash' => $hash])->first();
        if (!$tx) {
            return $this->redirect('/');
        }

        if ($tx->TransactionSize == 0) {
            $tx->TransactionSize = (strlen($tx->Raw) / 2);
            $conn = ConnectionManager::get('default');
            $conn->execute('UPDATE Transactions SET TransactionSize = ? WHERE Id = ?', [$tx->TransactionSize, $tx->Id]);
        }

        $block = $this->Blocks->find()->select(['Confirmations', 'Height'])->where(['Hash' => $tx->BlockHash])->first();
        $inputs = $this->Inputs->find()->contain(['InputAddresses'])->where(['TransactionId' => $tx->Id])->order(['PrevoutN' => 'asc'])->toArray();
        $outputs = $this->Outputs->find()->contain(['OutputAddresses', 'SpendInput' => ['fields' => ['Id', 'TransactionHash', 'PrevoutN', 'PrevoutHash']]])->where(['Outputs.TransactionId' => $tx->Id])->order(['Vout' => 'asc'])->toArray();
        for ($i = 0; $i < count($outputs); $i++) {
            $outputs[$i]->IsClaim = (strpos($outputs[$i]->ScriptPubKeyAsm, 'CLAIM') > -1);
            $outputs[$i]->IsSupportClaim = (strpos($outputs[$i]->ScriptPubKeyAsm, 'SUPPORT_CLAIM') > -1);
            $outputs[$i]->IsUpdateClaim = (strpos($outputs[$i]->ScriptPubKeyAsm, 'UPDATE_CLAIM') > -1);
        }

        $totalIn = 0;
        $totalOut = 0;
        $fee = 0;
        foreach ($inputs as $in) {
            $totalIn = bcadd($totalIn, $in->Value, 8);
        }
        foreach ($outputs as $out) {
            $totalOut = bcadd($totalOut, $out->Value, 8);
        }
        $fee = bcsub($totalIn, $totalOut, 8);

        $this->set('tx', $tx);
        $this->set('block', $block);
        $this->set('confirmations', $block ? number_format($block->Confirmations, 0, '', ',') : '0');
        $this->set('fee', $fee);
        $this->set('inputs', $inputs);
        $this->set('outputs', $outputs);
        $this->set('sourceAddress', $sourceAddress);
    }

    public function stats() {
        $this->loadModel('Addresses');

        // exclude bHW58d37s1hBjj3wPBkn5zpCX3F8ZW3uWf (genesis block)
        $richList = $this->Addresses->find()->where(['Address <>' => 'bHW58d37s1hBjj3wPBkn5zpCX3F8ZW3uWf'])->order(['Balance' => 'DESC'])->limit(500)->toArray();

        $priceRate = 0;
        $priceInfo = json_decode($this->redis->get(self::lbcPriceKey));
        if (isset($priceInfo->price)) {
            $priceRate = $priceInfo->price;
        }

        // calculate percentages
        $totalBalance = 0;
        $maxBalance = 0;
        $minBalance = 0;
        foreach ($richList as $item) {
            $totalBalance = bcadd($totalBalance, $item->Balance, 8);
            $minBalance = $minBalance == 0 ? $item->Balance : min($minBalance, $item->Balance);
            $maxBalance = max($maxBalance, $item->Balance);
        }
        for ($i = 0; $i < count($richList); $i++) {
            $item = $richList[$i];
            $percentage = bcdiv($item->Balance, $totalBalance, 8) * 100;
            $richList[$i]->Top500Percent = $percentage;
            $richList[$i]->MinMaxPercent = bcdiv($item->Balance, $maxBalance, 8) * 100;
        }

        $this->set('richList', $richList);
        $this->set('rate', $priceRate);
    }

    public function address($addr = null) {
        set_time_limit(0);

        $this->loadModel('Addresses');
        $this->loadModel('Transactions');
        $this->loadModel('Inputs');
        $this->loadModel('Outputs');

        if (!$addr) {
            return $this->redirect('/');
        }

        $offset = 0;
        $pageLimit = 50;
        $numTransactions = 0;
        $page = intval($this->request->query('page'));

        $canTag = false;
        $totalRecvAmount = 0;
        $totalSentAmount = 0;
        $balanceAmount = 0;
        $recentTxs = [];

        $tagRequestAmount = 0;
        // Check for pending tag request
        $this->loadModel('TagAddressRequests');
        $pending = $this->TagAddressRequests->find()->where(['Address' => $addr, 'IsVerified <>' => 1])->first();
        if (!$pending) {
            $tagRequestAmount = '25.' . rand(11111111, 99999999);
        }


        $address = $this->Addresses->find()->where(['Address' => $addr])->first();
        if (!$address) {
            if (strlen($addr) === 34) {
                $address = new \stdClass();
                $address->Address = $addr;
            } else {
                return $this->redirect('/');
            }
        } else {
            $conn = ConnectionManager::get('default');

            $canTag = true;
            $addressId = $address->Id;

            $stmt = $conn->execute('SELECT COUNT(TransactionId) AS Total FROM TransactionsAddresses WHERE AddressId = ?', [$addressId]);
            $count = $stmt->fetch(\PDO::FETCH_OBJ);
            $numTransactions = $count->Total;
            $all = $this->request->query('all');
            if ($all === 'true') {
                $offset = 0;
                $pageLimit = $numTransactions;
                $numPages = 1;
                $page = 1;
            } else {
                $numPages = ceil($numTransactions / $pageLimit);
                if ($page < 1) {
                    $page = 1;
                }
                if ($page > $numPages) {
                    $page = $numPages;
                }

                $offset = ($page - 1) * $pageLimit;
            }

            $stmt = $conn->execute('SELECT A.TotalReceived, A.TotalSent, A.Balance FROM Addresses A WHERE A.Id = ?', [$address->Id]);
            $totals = $stmt->fetch(\PDO::FETCH_OBJ);

            $stmt = $conn->execute(sprintf('SELECT T.Id, T.Hash, T.InputCount, T.OutputCount, T.Value, ' .
                                   'TA.DebitAmount, TA.CreditAmount, ' .
                                   'B.Height, B.Confirmations, IFNULL(T.TransactionTime, T.CreatedTime) AS TxTime ' .
                                   'FROM Transactions T ' .
                                   'LEFT JOIN Blocks B ON T.BlockHash = B.Hash ' .
                                   'RIGHT JOIN (SELECT TransactionId, DebitAmount, CreditAmount FROM TransactionsAddresses ' .
                                   '            WHERE AddressId = ? ORDER BY TransactionTime DESC LIMIT %d, %d) TA ON TA.TransactionId = T.Id', $offset, $pageLimit), [$addressId]);
            $recentTxs = $stmt->fetchAll(\PDO::FETCH_OBJ);

            $totalRecvAmount = $totals->TotalReceived == 0 ? '0' : $totals->TotalReceived + 0;
            $totalSentAmount = $totals->TotalSent == 0 ? '0' : $totals->TotalSent + 0;
            $balanceAmount = $totals->Balance == 0 ? '0' : $totals->Balance + 0;
        }

        $this->set('offset', $offset);
        $this->set('canTag', $canTag);
        $this->set('pending', $pending);
        $this->set('tagRequestAmount', $tagRequestAmount);
        $this->set('address', $address);
        $this->set('totalReceived', $totalRecvAmount);
        $this->set('totalSent', $totalSentAmount);
        $this->set('balanceAmount', $balanceAmount);
        $this->set('recentTxs', $recentTxs);
        $this->set('numRecords', $numTransactions);
        $this->set('numPages', $numPages);
        $this->set('currentPage', $page);
        $this->set('pageLimit', $pageLimit);
    }

    public function qr($data = null) {
        $this->autoRender = false;

        if (!$data || strlen(trim($data)) == 0 || strlen(trim($data)) > 50) {
            return;
        }

        $qrCode = new QrCode($data);
        $qrCode->setSize(300);

        // Set advanced options
        $qrCode
            ->setWriterByName('png')
            ->setMargin(10)
            ->setEncoding('UTF-8')
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::LOW)
            ->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0])
            ->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255])
            ->setLogoWidth(150)
            ->setValidateResult(false);

        header('Content-Type: '.$qrCode->getContentType());
        echo $qrCode->writeString();
        exit(0);
    }

    public static function curl_get($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        Log::debug('Request execution completed.');

        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);

            throw new \Exception(sprintf('The request failed: %s', $error), $errno);
        } else {
            curl_close($ch);
        }

        return $response;
    }

    private function _gethashrate() {
        $req = ['method' => 'getnetworkhashps', 'params' => []];
        try {
            $res = json_decode(self::curl_json_post(self::rpcurl, json_encode($req)));
            if (!isset($res->result)) {
                return 0;
            }
            return $res->result;
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
    private function _gettxoutsetinfo() {
        $req = ['method' => 'gettxoutsetinfo', 'params' => []];
        try {
            $res = json_decode(self::curl_json_post(self::rpcurl, json_encode($req)));
            if (!isset($res->result)) {
                return 0;
            }
            return $res->result;
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
    public function apistatus() {
        $this->autoRender = false;
        $this->loadModel('Blocks');

        // Get the max height block
        $height = 0;
        $difficulty = 0;
        $highestBlock = $this->Blocks->find()->select(['Height', 'Difficulty'])->order(['Height' => 'desc'])->first();
        $height = $highestBlock->Height;
        $difficulty = $highestBlock->Difficulty;
        $lbcUsdPrice = $this->_getLatestPrice();

        // Calculate hash rate
        $hashRate = $this->_formatHashRate($this->_gethashrate());

        return $this->_jsonResponse(['success' => true, 'status' => [
            'height' => $height,
            'difficulty' => number_format($difficulty, 2, '.', ''),
            'price' => $lbcUsdPrice,
            'hashrate' => $hashRate
        ]]);
    }

    public function apirecentblocks() {
        $this->autoRender = false;
        $this->loadModel('Blocks');
        $blocks = $this->Blocks->find()->select(['Difficulty', 'Hash', 'Height', 'TransactionHashes', 'BlockTime', 'BlockSize'])->
            order(['Height' => 'desc'])->limit(6)->toArray();
        for ($i = 0; $i < count($blocks); $i++) {
            $tx_hashes = json_decode($blocks[$i]->TransactionHashes);
            $blocks[$i]->TransactionCount = count($tx_hashes);
            $blocks[$i]->Difficulty = number_format($blocks[$i]->Difficulty, 2, '.', '');
            unset($blocks[$i]->TransactionHashes);
        }
        return $this->_jsonResponse(['success' => true, 'blocks' => $blocks]);
    }

    public function apiaddrtag($base58address = null) {
        $this->autoRender = false;
        if (!isset($base58address) || strlen(trim($base58address)) !== 34) {
            return $this->_jsonError('Invalid base58 address specified.', 400);
        }
        if (!$this->request->is('post')) {
            return $this->_jsonError('Invalid HTTP request method.', 400);
        }

        if (trim($base58address) == self::tagReceiptAddress) {
            return $this->_jsonError('You cannot submit a tag request for this address.', 400);
        }

        $this->loadModel('Addresses');
        $this->loadModel('TagAddressRequests');
        $data = [
            'Address' => $base58address,
            'Tag' => trim($this->request->data('tag')),
            'TagUrl' => trim($this->request->data('url')),
            'VerificationAmount' => $this->request->data('vamount')
        ];

        // verify
        $entity = $this->TagAddressRequests->newEntity($data);
        if (strlen($entity->Tag) === 0 || strlen($entity->Tag) > 30) {
            return $this->_jsonError('Oops! Please specify a valid tag. It should be no more than 30 characters long.', 400);
        }

        if (strlen($entity->TagUrl) > 0) {
            if (strlen($entity->TagUrl) > 200) {
                return $this->_jsonError('Oops! The link should be no more than 200 characters long.', 400);
            }
            if (!filter_var($entity->TagUrl, FILTER_VALIDATE_URL)) {
                return $this->_jsonError('Oops! The link should be a valid URL.', 400);
            }
        } else {
            unset($entity->TagUrl);
        }

        if ($entity->VerificationAmount < 25.1 || $entity->VerificationAmount > 25.99999999) {
            return $this->_jsonError('Oops! The verification amount is invalid. Please refresh the page and try again.', 400);
        }

        // check if the tag is taken
        $addrTag = $this->Addresses->find()->select(['Id'])->where(['LOWER(Tag)' => strtolower($entity->Tag)])->first();
        if ($addrTag) {
            return $this->_jsonError('Oops! The tag is already taken. Please specify a different tag.', 400);
        }

        // check for existing verification
        $exist = $this->TagAddressRequests->find()->select(['Id'])->where(['Address' => $base58address, 'IsVerified' => 0])->first();
        if ($exist) {
            return $this->_jsonError('Oops! There is a pending tag verification for this address.', 400);
        }

        // save the request
        if (!$this->TagAddressRequests->save($entity)) {
            return $this->_jsonError('Oops! The verification request could not be saved. If this problem persists, please send an email to hello@aureolin.co');
        }

        return $this->_jsonResponse(['success' => true, 'tag' => $entity->Tag]);
    }

    public function apiaddrbalance($base58address = null) {
        $this->autoRender = false;
        $this->loadModel('Addresses');

        if (!isset($base58address)) {
            return $this->_jsonError('Base58 address not specified.', 400);
        }

        // TODO: Add unconfirmed_balance to response
        $result = $this->Addresses->find()->select(['Balance'])->where(['Address' => $base58address])->first();
        if (!$result) {
            // Return 0 for address that does not exist?
            $result = new \stdClass();
            $result->Balance = 0;
        }

        return $this->_jsonResponse(['success' => true, ['balance' => ['confirmed' => $result->Balance, 'unconfirmed' => 0]]]);
    }

    public function apiaddrutxo($base58address = null) {
        $this->autoRender = false;
        $this->loadModel('Addresses');

        if (!isset($base58address)) {
            return $this->_jsonError('Base58 address not specified.', 400);
        }

        $arr = explode(',', $base58address);
        $addresses = $this->Addresses->find()->select(['Id'])->where(['Address IN' => $arr])->toArray();
        if (count($addresses) == 0) {
            return $this->_jsonError('No base58 address matching the specified parameter was found.', 404);
        }

        $addressIds = [];
        $params = [];
        foreach ($addresses as $address) {
            $addressIds[] = $address->Id;
            $params[] = '?';
        }

        // Get the unspent outputs for the address
        $conn = ConnectionManager::get('default');
        $stmt = $conn->execute(sprintf(
                               'SELECT T.Hash AS TransactionHash, O.Vout, O.Value, O.Addresses, O.ScriptPubKeyAsm, O.ScriptPubKeyHex, O.Type, O.RequiredSignatures, B.Confirmations ' .
                               'FROM Transactions T ' .
                               'JOIN Outputs O ON O.TransactionId = T.Id ' .
                               'JOIN Blocks B ON B.Hash = T.BlockHash ' .
                               'WHERE O.Id IN (SELECT OutputId FROM OutputsAddresses WHERE AddressId IN (%s)) AND O.IsSpent <> 1 ORDER BY T.TransactionTime ASC', implode(',', $params)), $addressIds);
        $outputs = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $utxo = [];
        foreach ($outputs as $out) {
            $utxo[] = [
                'transaction_hash' => $out->TransactionHash,
                'output_index' => $out->Vout,
                'value' => (int) bcmul($out->Value, 100000000),
                'addresses' => json_decode($out->Addresses),
                'script' => $out->ScriptPubKeyAsm,
                'script_hex' => $out->ScriptPubKeyHex,
                'script_type' => $out->Type,
                'required_signatures' => (int) $out->RequiredSignatures,
                'spent' => false,
                'confirmations' => (int) $out->Confirmations
            ];
        }

        return $this->_jsonResponse(['success' => true, 'utxo' => $utxo]);
    }

      public function apiutxosupply() {
        $this->autoRender = false;
        $circulating = 0;
        $reservedcommunity = 0;
        $reservedoperational = 0;
        $reservedinstitutional = 0;
        $reservedtotal = 0;
        $circulating = 0;
          
        $txoutsetinfo = $this->_gettxoutsetinfo();
        $reservedcommunity = $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'rFLUohPG4tP3gZHYoyhvADCtrDMiaYb7Qd'])->first();
        $reservedoperational = $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'r9PGXsejVJb9ZfMf3QVdDEJCzxkd9JLxzL'])->first();
        $reservedinstitutional = $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'r9srwX7DEN7Mex3a8oR1mKSqQmLBizoJvi'])->first();
        $reservedtotal = $reservedcommunity->Balance + $reservedoperational->Balance + $reservedinstitutional->Balance;
        $circulating = $txoutsetinfo->total_amount - $reservedtotal;
   
        return $this->_jsonResponse(['success' => true, ['utxosupply' => ['total' => $txoutsetinfo->total_amount, 'circulating' => $circulating]]]);

    }
    
    private static function curl_json_post($url, $data, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);

            throw new \Exception(sprintf('The request failed: %s', $error), $errno);
        } else {
            curl_close($ch);
        }

        // Close any open file handle
        return $response;
    }
}

?>
