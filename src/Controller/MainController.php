<?php

namespace App\Controller;

use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Crypto\Signature\Signer;
use Mdanter\Ecc\Serializer\PublicKey\PemPublicKeySerializer;
use Mdanter\Ecc\Serializer\PublicKey\DerPublicKeySerializer;
use Mdanter\Ecc\Serializer\Signature\DerSignatureSerializer;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\LabelAlignment;
use Endroid\QrCode\QrCode;


class MainController extends AppController {

    public static $rpcurl;

    const lbcPriceKey = 'lbc.price';

    const bittrexMarketUrl = 'https://bittrex.com/api/v1.1/public/getticker?market=BTC-LBC';

    const blockchainTickerUrl = 'https://blockchain.info/ticker';

    const tagReceiptAddress = 'bLockNgmfvnnnZw7bM6SPz6hk5BVzhevEp';

    protected $redis;

    public function initialize() {
        parent::initialize();
        self::$rpcurl = Configure::read('Lbry.RpcUrl');
        $this->redis = new \Predis\Client(Configure::read('Redis.Url'));
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

        $blocks = $this->Blocks->find()->select(['chainwork', 'confirmations', 'difficulty', 'hash', 'height', 'transaction_hashes', 'block_time', 'block_size'])->order(['height' => 'desc'])->limit(6)->toArray();
        for ($i = 0; $i < count($blocks); $i++) {
            $tx_hashes = preg_split('#,#', $blocks[$i]->transaction_hashes);
            $blocks[$i]->transaction_count = count($tx_hashes);
        }
        // hash rate
        $hashRate = $this->_formatHashRate($this->_gethashrate());

        // recent claims
        $claims = $this->Claims->find()->select(['transaction_hash_id', 'name', 'vout', 'claim_id', 'claim_type', 'author', 'title', 'description', 'content_type', 'is_nsfw', 'language', 'thumbnail_url', 'created_at', 'publisher_id'])->
            distinct(['Claims.claim_id'])->order(['Claims.created_at' => 'DESC'])->limit(5)->toArray();

        foreach($claims as $claim) {
            $publisher = $this->Claims->find()->select(['name'])->where(['claim_id' => $claim->publisher_id])->first();
            $claim->publisher = $publisher;
        }

        $this->set('recentBlocks', $blocks);
        $this->set('recentClaims', $claims);
        $this->set('hashRate', $hashRate);
    }

    public function claims($id = null) {
        $this->loadModel('Claims');
        $this->loadModel('Transactions');

        $canConvert = false;
        if(isset($this->redis)) {
            $priceInfo = json_decode($this->redis->get(self::lbcPriceKey));
        }
        if (isset($priceInfo->price)) {
            $canConvert = true;
        }

        if (!$id) {
            // paginate claims
            $offset = 0;
            $pageLimit = 96;
            $page = intval($this->request->query('page'));

            $conn = ConnectionManager::get('default');
            $stmt = $conn->execute('SELECT COUNT(id) AS Total FROM claim');
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
            $claims = $this->Claims->find()->distinct(['Claims.claim_id'])->select($this->Claims)->select(['publisher' => 'C.name'])->leftJoin(['C' => 'claim'], ['C.claim_id = Claims.publisher_id'])->order(['Claims.created_at' => 'DESC'])->offset($offset)->limit($pageLimit)->toArray();
            
            for ($i = 0; $i < count($claims); $i++) {
                if ($canConvert && $claims[$i]->fee > 0 && $claims[$i]->fee_currency == 'USD') {
                    $claims[$i]->price = $claims[$i]->fee / $priceInfo->price;
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
            $claim = $this->Claims->find()->select($this->Claims)->select(['publisher' => 'C.name'])->leftJoin(['C' => 'claim'], ['C.claim_id = Claims.publisher_id'])->where(['Claims.claim_id' => $id])->order(['Claims.created_at' => 'DESC'])->first();
            if (!$claim) {
                return $this->redirect('/');
            }

            if ($canConvert && $claim->fee > 0 && $claim->fee_currency == 'USD') {
                $claim->price = $claim->fee / $priceInfo->price;
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
            if (isset($claim->publisher) || $claim->claim_type == 1) {
                // find more claims for the publisher
                $moreClaims = $this->Claims->find()->select($this->Claims)->select(['publisher' => 'C.name'])->leftJoin(['C' => 'claim'], ['C.claim_id = Claims.publisher_id'])->where(['Claims.claim_type' => 1, 'Claims.id <>' => $claim->id, 'Claims.publisher_id' => isset($claim->publisher) ? $claim->publisher_id : $claim->claim_id])->limit(9)->order(['Claims.fee' => 'DESC', 'RAND()' => 'DESC'])->toArray();
                for ($i = 0; $i < count($moreClaims); $i++) {
                    if ($canConvert && $moreClaims[$i]->fee > 0 && $moreClaims[$i]->fee_currency == 'USD') {
                        $moreClaims[$i]->price = $moreClaims[$i]->fee / $priceInfo->price;
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
        $blocks = $this->Blocks->find()->select(['height', 'block_time', 'transaction_hashes'])->order(['height' => 'desc'])->limit(10)->toArray();
        for ($i = 0; $i < count($blocks); $i++) {
            $tx_hashes = preg_split('#,#', $blocks[$i]->transaction_hashes);
            $blocks[$i]->transaction_count = count($tx_hashes);
        }

        $transactions = $this->Transactions->find()->select(['Transactions.id', 'Transactions.hash', 'Transactions.input_count', 'Transactions.output_count', 'Transactions.transaction_time', 'Transactions.created_at'])->select(['value' => 'sum(O.value)'])->leftJoin(['O' => 'output'], ['O.transaction_id = Transactions.id'])->order(['Transactions.created_at' => 'desc'])->limit(10)->toArray();
        $this->set('blocks', $blocks);
        $this->set('txs', $transactions);
    }

    public function find() {
        $criteria = $this->request->query('q');

        $this->loadModel('Blocks');
        $this->loadModel('Claims');
        $this->loadModel('Addresses');
        $this->loadModel('Transactions');

        if (is_numeric($criteria)) {
            $height = (int) $criteria;
            $block = $this->Blocks->find()->select(['id'])->where(['height' => $height])->first();
            if ($block) {
                return $this->redirect('/blocks/' . $height);
            }
        } else if (strlen(trim($criteria)) === 34) {
            // Address
            $address = $this->Addresses->find()->select(['id', 'address'])->where(['address' => $criteria])->first();
            if ($address) {
                return $this->redirect('/address/' . $address->address);
            }
        } else if (strlen(trim($criteria)) === 40) {
            // Claim ID
            $claim = $this->Claims->find()->select(['claim_id'])->where(['claim_id' => $criteria])->first();
            if ($claim) {
                return $this->redirect('/claims/' . $claim->claim_id);
            }
        } else if (strlen(trim($criteria)) === 64) { // block or tx hash
            // Try block hash first
            $block = $this->Blocks->find()->select(['height'])->where(['hash' => $criteria])->first();
            if ($block) {
                return $this->redirect('/blocks/' . $block->height);
            } else {
                $tx = $this->Transactions->find()->select(['hash'])->where(['hash' => $criteria])->first();
                if ($tx) {
                    return $this->redirect('/tx/' . $tx->hash);
                }
            }
        } else {
            // finally, try exact claim name match
            $claims = $this->Claims->find()->distinct(['claim_id'])->where(['name' => $criteria])->order(['created_at' => 'DESC'])->limit(10)->toArray();
            if (count($claims) == 1) {
                return $this->redirect('/claims/' . $claims[0]->claim_id);
            }
            else {
                $this->set('claims', $claims);
            }
        }
    }
    
    public function blocks($height = null) {
        $this->loadModel('Blocks');
        $this->loadModel('Outputs');
        $this->loadModel('Transactions');

        if ($height === null) {
            // paginate blocks
            $offset = 0;
            $pageLimit = 50;
            $page = intval($this->request->query('page'));

            $conn = ConnectionManager::get('default');
            $stmt = $conn->execute('SELECT COUNT(id) AS Total FROM block');
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
            $currentBlock = $this->Blocks->find()->select(['height'])->order(['height' => 'DESC'])->first();
            $blocks = $this->Blocks->find()->select(
                ['height', 'difficulty', 'transaction_hashes', 'block_size', 'nonce', 'block_time']
            )->offset($offset)->limit($pageLimit)->order(['height' => 'DESC'])->toArray();
            $this->set('currentBlock', $currentBlock);
            $this->set('blocks', $blocks);
            $this->set('pageLimit', $pageLimit);
            $this->set('numPages', $numPages);
            $this->set('numRecords', $numBlocks);
            $this->set('currentPage', $page);
        } else {
            $height = intval($height);
            if ($height < 0) {
                return $this->redirect('/');
            }

            $block = $this->Blocks->find()->where(['height' => $height])->first();
            if (!$block) {
                return $this->redirect('/');
            }

            // Get the basic block transaction info
            $txs = $this->Transactions->find()->select(['Transactions.id', 'Transactions.input_count', 'Transactions.output_count', 'Transactions.hash', 'Transactions.version'])->select(['value' => 'sum(O.value)'])->leftJoin(['O' => 'output'], ['O.transaction_id = Transactions.id'])->where(['Transactions.block_hash_id' => $block->hash])->toArray();
            $this->set('block', $block);
            $this->set('blockTxs', $txs);
        }
    }

    public function tx($hash = null) {
        $this->loadModel('Blocks');
        $this->loadModel('Transactions');
        $this->loadModel('Inputs');
        $this->loadModel('Outputs');
        $this->loadModel('Addresses');
        $this->loadModel('Claims');
        
        $sourceAddress = $this->request->query('address');

        $tx = $this->Transactions->find()->select(
            ['Transactions.id', 'Transactions.block_hash_id', 'Transactions.input_count', 'Transactions.output_count', 'Transactions.hash', 'Transactions.transaction_time', 'Transactions.transaction_size', 'Transactions.created_at', 'Transactions.version', 'Transactions.lock_time', 'Transactions.raw'])->select(['value' => 'sum(O.value)'])->leftJoin(['O' => 'output'], ['O.transaction_id = Transactions.id'])->where(['Transactions.hash' => $hash])->first();
        if (!$tx) {
            return $this->redirect('/');
        }

        $block = $this->Blocks->find()->select(['confirmations', 'height'])->where(['hash' => $tx->block_hash_id])->first();
        $confirmations = $block->confirmations;
        $inputs = $this->Inputs->find()->where(['transaction_id' => $tx->id])->order(['prevout_n' => 'asc'])->toArray();
        foreach($inputs as $input) {
            $inputAddresses = $this->Addresses->find()->select(['id', 'address'])->where(['id' => $input->input_address_id])->toArray();
            $input->input_addresses = $inputAddresses;
        }
        
        $outputs = $this->Outputs->find()->where(['transaction_id' => $tx->id])->order(['vout' => 'asc'])->toArray();
        for ($i = 0; $i < count($outputs); $i++) {
            $spend_input = $this->Inputs->find()->select(['transaction_hash', 'id'])->where(['id' => $outputs[$i]->spent_by_input_id])->first();
            $outputs[$i]->spend_input = $spend_input;
            
            $output_address = trim($outputs[$i]->address_list, '[""]');
            $address = $this->Addresses->find()->select(['address'])->where(['address' => $output_address])->first();
            $outputs[$i]->output_addresses = [$address];
            
            $outputs[$i]->IsClaim = (strpos($outputs[$i]->script_pub_key_asm, 'CLAIM') > -1);
            $outputs[$i]->IsSupportClaim = (strpos($outputs[$i]->script_pub_key_asm, 'SUPPORT_CLAIM') > -1);
            $outputs[$i]->IsUpdateClaim = (strpos($outputs[$i]->script_pub_key_asm, 'UPDATE_CLAIM') > -1);
            $claim = $this->Claims->find()->select(['id', 'claim_id', 'vout', 'transaction_hash_id'])->where(['transaction_hash_id' => $tx->hash, 'vout' => $outputs[$i]->vout])->first();
            $outputs[$i]->Claim = $claim;
        }

        $totalIn = 0;
        $totalOut = 0;
        $fee = 0;
        foreach ($inputs as $in) {
            $totalIn = bcadd($totalIn, $in->value, 8);
        }
        foreach ($outputs as $out) {
            $totalOut = bcadd($totalOut, $out->value, 8);
        }
        $fee = bcsub($totalIn, $totalOut, 8);

        $this->set('tx', $tx);
        $this->set('block', $block);
        $this->set('confirmations', $confirmations);
        $this->set('fee', $fee);
        $this->set('inputs', $inputs);
        $this->set('outputs', $outputs);
        $this->set('sourceAddress', $sourceAddress);
    }

    public function stats() {
        $this->loadModel('Addresses');

        // exclude bHW58d37s1hBjj3wPBkn5zpCX3F8ZW3uWf (genesis block)
        $richList = $this->Addresses->find()->where(['address <>' => 'bHW58d37s1hBjj3wPBkn5zpCX3F8ZW3uWf'])->order(['Balance' => 'DESC'])->limit(500)->toArray();

        $priceRate = 0;
        //$priceInfo = json_decode($this->redis->get(self::lbcPriceKey));
        $priceInfo->price = 0.05;
        if (isset($priceInfo->price)) {
            $priceRate = $priceInfo->price;
        }
        
        $lbryAddresses = ['rFLUohPG4tP3gZHYoyhvADCtrDMiaYb7Qd', 'r9PGXsejVJb9ZfMf3QVdDEJCzxkd9JLxzL', 'r9srwX7DEN7Mex3a8oR1mKSqQmLBizoJvi', 'bRo4FEeqqxY7nWFANsZsuKEWByEgkvz8Qt', 'bU2XUzckfpdEuQNemKvhPT1gexQ3GG3SC2', 'bay3VA6YTQBL4WLobbG7CthmoGeUKXuXkD', 'bLPbiXBp6Vr3NSnsHzDsLNzoy5o36re9Cz', 'bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc', 'bVUrbCK8hcZ5XWti7b9eNxKEBxzc1rr393', 'bZja2VyhAC84a9hMwT8dwTU6rDRXowrjxH', 'bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc', 'bMgqQqYfwzWWYBk5o5dBMXtCndVAoeqy6h', 'bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc'];
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
        $this->set('lbryAddresses', $lbryAddresses);
    }

    public function address($addr = null) {
        set_time_limit(0);

        $this->loadModel('Blocks');
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
        //$this->loadModel('TagAddressRequests');
        //$pending = $this->TagAddressRequests->find()->where(['Address' => $addr, 'IsVerified <>' => 1])->first();
        //if (!$pending) {
        //    $tagRequestAmount = '25.' . rand(11111111, 99999999);
        //}

        $address = $this->Addresses->find()->where(['address' => $addr])->first();
        if (!$address) {
            if (strlen($addr) === 34) {
                $address = new \stdClass();
                $address->address = $addr;
            } else {
                return $this->redirect('/');
            }
        } else {
            $conn = ConnectionManager::get('default');

            $canTag = true;
            $addressId = $address->id;
            
            $stmt = $conn->execute('SELECT * FROM transaction_address WHERE address_id = ?', [$addressId]);
            $transactionAddresses = $stmt->fetch(\PDO::FETCH_OBJ);
            $numTransactions = count($transactionAddresses);
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

            $currentBlock = $this->Blocks->find()->select(['height'])->order(['height' => 'desc'])->first();
            $currentHeight = $currentBlock ? intval($currentBlock->height) : 0;

            $stmt = $conn->execute(sprintf(
                'SELECT T.id, T.hash, T.input_count, T.output_count' .
                '    TA.debit_amount, TA.credit_amount, ' .
                '    B.height, B.confirmations, ' .
                '    IFNULL(T.transaction_time, T.created_at) AS transaction_time ' .
                'FROM transaction T ' .
                'LEFT JOIN block B ON T.block_hash_id = B.hash ' .
                'RIGHT JOIN (SELECT transaction_id, debit_amount, credit_amount FROM transaction_address ' .
                '            WHERE address_id = ? ORDER BY transaction_time DESC LIMIT %d, %d) TA ON TA.transaction_id = T.id', $offset, $pageLimit), [$addressId]);
            $recentTxs = $stmt->fetchAll(\PDO::FETCH_OBJ);

            foreach($transactionAddresses as $ta) {
                $totalRecvAmount += $ta->credit_amount + 0;
                $totalSentAmount += $ta->debit_amount + 0;
            }
            $balanceAmount = $totalSentAmount - $totalRecvAmount;
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
        $stmt = $conn->execute("SELECT AVG(block_size) AS AvgBlockSize, DATE_FORMAT(FROM_UNIXTIME(block_time), '$sqlDateFormat') AS TimePeriod " .
                               "FROM block WHERE DATE_FORMAT(FROM_UNIXTIME(block_time), '$sqlDateFormat') >= ? GROUP BY TimePeriod ORDER BY TimePeriod ASC", [$start->format($dateFormat)]);
        $avgBlockSizes = $stmt->fetchAll(\PDO::FETCH_OBJ);
        foreach ($avgBlockSizes as $size) {
            if (!isset($resultSet[$size->TimePeriod])) {
                $resultSet[$size->TimePeriod] = [];
            }
            $resultSet[$size->TimePeriod]['AvgBlockSize'] = (float) $size->AvgBlockSize;
        }

        // get avg prices
        
        $conn_local = ConnectionManager::get('localdb');
        $stmt = $conn_local->execute("SELECT AVG(USD) AS AvgUSD, DATE_FORMAT(Created, '$sqlDateFormat') AS TimePeriod " .
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
        // Load 10 blocks
        $this->autoRender = false;
        $this->loadModel('Blocks');
        $blocks = $this->Blocks->find()->select(['Height' => 'height', 'BlockTime' => 'block_time', 'transaction_hashes'])->order(['Height' => 'desc'])->limit(10)->toArray();
        for ($i = 0; $i < count($blocks); $i++) {
            $tx_hashes = preg_split('#,#', $blocks[$i]->transaction_hashes);
            $blocks[$i]->TransactionCount = count($tx_hashes);
            unset($blocks[$i]->transaction_hashes);
        }

        $this->_jsonResponse(['success' => true, 'blocks' => $blocks]);
    }

    public function apirealtimetx() {
        // Load 10 transactions
        $this->autoRender = false;
        $this->loadModel('Transactions');
        $txs = $this->Transactions->find()->select(['id', 'Hash' => 'hash', 'InputCount' => 'input_count', 'OutputCount' => 'output_count', 'TxTime' => 'transaction_time'])->order(['TxTime' => 'desc'])->limit(10);
        foreach($txs as $tx) {
            $tx->Value = $tx->value();
        }

        $this->_jsonResponse(['success' => true, 'txs' => $txs]);
    }

    public function apistatus() {
        $this->autoRender = false;
        $this->loadModel('Blocks');

        // Get the max height block
        $height = 0;
        $difficulty = 0;
        $highestBlock = $this->Blocks->find()->select(['height', 'difficulty'])->order(['height' => 'desc'])->first();
        $height = $highestBlock->height;
        $difficulty = $highestBlock->difficulty;
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
        $blocks = $this->Blocks->find()->select(['Difficulty' => 'difficulty', 'Hash' => 'hash', 'Height' => 'height', 'transaction_hashes', 'BlockTime' => 'block_time', 'BlockSize' => 'block_size'])->order(['Height' => 'desc'])->limit(6)->toArray();
        for ($i = 0; $i < count($blocks); $i++) {
            $tx_hashes = preg_split('#,#', $blocks[$i]->transaction_hashes);
            $blocks[$i]->TransactionCount = count($tx_hashes);
            $blocks[$i]->Difficulty = number_format($blocks[$i]->Difficulty, 2, '.', '');
            unset($blocks[$i]->transaction_hashes);
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
        $addrTag = $this->Addresses->find()->select(['id'])->where(['LOWER(Tag)' => strtolower($entity->Tag)])->first();
        if ($addrTag) {
            return $this->_jsonError('Oops! The tag is already taken. Please specify a different tag.', 400);
        }

        // check for existing verification
        $exist = $this->TagAddressRequests->find()->select(['id'])->where(['Address' => $base58address, 'IsVerified' => 0])->first();
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
        
        $address = $this->Addresses->find()->select(['id'])->where(['address' => $base58address])->first();
        if (!$address) {
            return $this->_jsonError('Could not find address.', 400);
        }
        $conn = ConnectionManager::get('default');
        $stmt = $conn->execute(sprintf(
                'SELECT TA.debit_amount, TA.credit_amount, ' .
                'FROM transaction_address TA' .
                'WHERE TA.address_id = ?'), [$address->id]);
        $transaction_addresses = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $balance = 0;
        foreach($transaction_addresses as $ta) {
            $balance += $ta->credit_amount;
            $balance -= $ta->debit_amount;
        } 
        return $this->_jsonResponse(['success' => true, ['balance' => ['confirmed' => $balance, 'unconfirmed' => 0]]]);
    }

    public function apiaddrutxo($base58address = null) {
        $this->autoRender = false;
        $this->loadModel('Addresses');

        if (!isset($base58address)) {
            return $this->_jsonError('Base58 address not specified.', 400);
        }

        $arr = explode(',', $base58address);
        $addresses = $this->Addresses->find()->select(['id'])->where(['address IN' => $arr])->toArray();
        if (count($addresses) == 0) {
            return $this->_jsonError('No base58 address matching the specified parameter was found.', 404);
        }

        $addressIds = [];
        $params = [];
        foreach ($addresses as $address) {
            $addressIds[] = $address->id;
            $params[] = '?';
        }

        // Get the unspent outputs for the address
        $conn = ConnectionManager::get('default');
        $stmt = $conn->execute(sprintf(
                               'SELECT T.hash AS transaction_hash, O.vout, O.value, O.address_list, O.script_pub_key_asm, O.script_pub_key_hex, O._type, O.required_signatures, B.confirmations ' .
                               'FROM transaction T ' .
                               'JOIN output O ON O.transaction_id = T.id ' .
                               'JOIN block B ON B.hash = T.block_hash_id ' .
                               'WHERE O.id IN (SELECT id FROM output O2 WHERE address_id IN (%s)) AND O.is_spent <> 1 ORDER BY T.transaction_time ASC', implode(',', $params)), $addressIds);
        $outputs = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $utxo = [];
        foreach ($outputs as $out) {
            $utxo[] = [
                'transaction_hash' => $out->transaction_hash,
                'output_index' => $out->vout,
                'value' => (int) bcmul($out->value, 100000000),
                'addresses' => json_decode($out->address_list),
                'script' => $out->script_pub_key_asm,
                'script_hex' => $out->script_pub_key_hex,
                'script_type' => $out->type,
                'required_signatures' => (int) $out->required_signatures,
                'spent' => false,
                'confirmations' => (int) $out->confirmations
            ];
        }

        return $this->_jsonResponse(['success' => true, 'utxo' => $utxo]);
    }

    public function apiutxosupply() {
        $this->autoRender = false;
        $this->loadModel('Addresses');

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
        //aux is the address of hot wallets and where some of the LBRY operational fund are, but not sold on market.
        $reservedaux = $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'bRo4FEeqqxY7nWFANsZsuKEWByEgkvz8Qt'])->first() +
                       $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'bU2XUzckfpdEuQNemKvhPT1gexQ3GG3SC2'])->first() +
                       $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'bay3VA6YTQBL4WLobbG7CthmoGeUKXuXkD'])->first() +
                       $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'bLPbiXBp6Vr3NSnsHzDsLNzoy5o36re9Cz'])->first() +
                       $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc'])->first() +
                       $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'bVUrbCK8hcZ5XWti7b9eNxKEBxzc1rr393'])->first() +
                       $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'bZja2VyhAC84a9hMwT8dwTU6rDRXowrjxH'])->first() +
                       $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc'])->first() +
                       $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'bCrboXVztuSbZzVToCWSsu1pEr2oxKHu9v'])->first() +
                       $this->Addresses->find()->select(['Balance'])->where(['Address =' => 'bMgqQqYfwzWWYBk5o5dBMXtCndVAoeqy6h'])->first();

        $reservedtotal = $reservedcommunity->Balance + $reservedoperational->Balance + $reservedinstitutional->Balance + $reservedaux->Balance;


         $circulating = $txoutsetinfo->total_amount - $reservedtotal;

        return $this->_jsonResponse(['success' => true, 'utxosupply' => ['total' => $txoutsetinfo->total_amount, 'circulating' => $circulating]]);
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
            $res = json_decode(self::curl_json_post(self::$rpcurl, json_encode($req)));
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
            $res = json_decode(self::curl_json_post(self::$rpcurl, json_encode($req)));
            if (!isset($res->result)) {
                return 0;
            }
            return $res->result;
        } catch (\Exception $e) {
            return 'N/A';
        }
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
