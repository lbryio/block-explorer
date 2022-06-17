<?php

namespace App\Controller;

use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Crypto\Signature\Signer;
use Mdanter\Ecc\Serializer\PublicKey\PemPublicKeySerializer;
use Mdanter\Ecc\Serializer\PublicKey\DerPublicKeySerializer;
use Mdanter\Ecc\Serializer\Signature\DerSignatureSerializer;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\LabelAlignment;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Response\QrCodeResponse;


class MainController extends AppController {

    protected $rpcurl;

    const lbcPriceKey = 'lbc.price';

    const txOutSetInfo = 'lbrcrd.tosi';

    const bittrexMarketUrl = 'https://api.bittrex.com/v3/markets/LBC-BTC/ticker';

    const blockchainTickerUrl = 'https://blockchain.info/ticker';

    const tagReceiptAddress = 'bLockNgmfvnnnZw7bM6SPz6hk5BVzhevEp';

    const blockedListUrl = 'https://api.odysee.com/file/list_blocked';

    protected $redis;

    public function initialize() {
        parent::initialize();
        $this->rpcurl = Configure::read('Lbry.RpcUrl');
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

            if ($btrxjson) {
                $onelbc = $btrxjson->bidRate;
                $lbcPrice = 0;
                if (isset($blckjson->USD)) {
                    $lbcPrice = $onelbc * $blckjson->USD->buy;
                    if ($lbcPrice > 0) {
                        $priceInfo->price = number_format($lbcPrice, 3, '.', '');
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

        $blocks = $this->Blocks->find()->select(['chainwork', 'confirmations', 'difficulty', 'hash', 'height', 'block_time', 'block_size','tx_count'])->order(['height' => 'desc'])->limit(6)->toArray();
        // hash rate
        $hashRate = $this->_formatHashRate($this->_gethashrate());

        // recent claims
        //$claims = $this->Claims->find()->distinct(['Claims.claim_id'])->select($this->Claims)->select(['publisher' => 'C.name'])->leftJoin(['C' => 'claim'], ['C.claim_id = Claims.publisher_id'])->order(['Claims.created_at' => 'DESC'])->limit(5)->toArray();
        $claims = $this->Claims->find()->select($this->Claims)->select(['publisher' => 'C.name'])->leftJoin(['C' => 'claim'], ['C.claim_id = Claims.publisher_id'])->order(['Claims.created_at' => 'DESC'])->limit(5)->toArray();

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
//            $stmt = $conn->execute('SELECT COUNT(id) AS Total FROM claim');
//            $count = $stmt->fetch(\PDO::FETCH_OBJ);
            $numClaims = 20000000;

            $stmt = $conn->execute('SELECT MAX(id) AS MaxId FROM claim');
            $res = $stmt->fetch(\PDO::FETCH_OBJ);
            $maxClaimId = $res->MaxId;

            $numPages = ceil($numClaims  / $pageLimit);
            if ($page < 1) {
                $page = 1;
            }
            if ($page > $numPages) {
                $page = $numPages;
            }

            $startLimitId = $maxClaimId - ($page * $pageLimit);
            $endLimitId = $startLimitId + $pageLimit;
            if ($endLimitId > $maxClaimId) {
                $endLimitId = $maxClaimId;
            }

            $blockedList = json_decode($this->_getBlockedList());
            $claims = $this->Claims->find()->select($this->Claims)->
                select(['publisher' => 'C.name', 'publisher_transaction_hash_id' => 'C.transaction_hash_id', 'publisher_vout' => 'C.vout'])->
                leftJoin(['C' => 'claim'], ['C.claim_id = Claims.publisher_id'])->
                where(['Claims.id >' => $startLimitId, 'Claims.id <=' => $endLimitId])->
                order(['Claims.id' => 'DESC'])->toArray();

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

                $claimChannel = null;
                if ($claims[$i]->publisher_transaction_hash_id) {
                    $claimChannel = new \stdClass();
                    $claimChannel->transaction_hash_id = $claims[$i]->publisher_transaction_hash_id;
                    $claimChannel->vout = $claims[$i]->publisher_vout;
                }

                $blocked = $this->_isClaimBlocked($claims[$i], $claimChannel, $blockedList);
                $claims[$i]->isBlocked = $blocked;
                $claims[$i]->thumbnail_url = $blocked ? null : $claims[$i]->thumbnail_url; // don't show the thumbnails too
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
                $moreClaimsQuery = $this->Claims->find()->select([
                    'claim_id', 'bid_state', 'fee', 'fee_currency', 'is_nsfw', 'claim_type', 'name',
                    'title', 'description', 'content_type', 'language', 'author', 'license', 'content_type',
                    'created_at'
                ])->select(['publisher' => 'C.name'])->leftJoin(['C' => 'claim'], ['C.claim_id = Claims.publisher_id'])->where(['Claims.claim_type' => 1, 'Claims.id <>' => $claim->id, 'Claims.publisher_id' => isset($claim->publisher) ? $claim->publisher_id : $claim->claim_id])->limit(9);
                if (isset($claim->publisher) && $claim->publisher_id !== 'f2cf43b86b9d70175dc22dbb9ff7806241d90780') { // prevent ORDER BY for this particular claim
                    $moreClaimsQuery = $moreClaimsQuery->order(['Claims.fee' => 'DESC', 'RAND()' => 'DESC']);
                    $moreClaims = $moreClaimsQuery->toArray();
                }
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

            // fetch blocked list
            $blockedList = json_decode($this->_getBlockedList());
            $claimChannel = $this->Claims->find()->select(['transaction_hash_id', 'vout'])->where(['claim_id' => $claim->publisher_id])->first();
            $claimIsBlocked = $this->_isClaimBlocked($claim, $claimChannel, $blockedList);

            $this->set('claim', $claim);
            $this->set('claimIsBlocked', $claimIsBlocked);
            $this->set('moreClaims', $claimIsBlocked ? [] : $moreClaims);
        }
    }

    public function realtime() {
        $this->loadModel('Blocks');
        $this->loadModel('Transactions');
        $this->loadModel('Outputs');

        // load 10 blocks and transactions
        $blocks = $this->Blocks->find()->select(['height', 'block_time', 'tx_count'])->order(['height' => 'desc'])->limit(10)->toArray();

        $transactions = $this->Transactions->find()->select(['Transactions.id', 'Transactions.hash', 'Transactions.value', 'Transactions.input_count', 'Transactions.output_count', 'Transactions.transaction_time', 'Transactions.created_at'])->order(['Transactions.created_at' => 'desc'])->limit(10)->toArray();

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
            $claims = $this->Claims->find()->distinct(['claim_id'])->where(['name' => $criteria])->order(["FIELD(bid_state, 'Controlling') DESC"])->limit(10)->toArray();
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
            $stmt = $conn->execute('SELECT height AS Total FROM block oder by id desc limit 1');
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
                ['height', 'difficulty', 'block_size', 'nonce', 'block_time','tx_count']
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
            $txs = $this->Transactions->find()->select(['Transactions.id', 'Transactions.value', 'Transactions.input_count', 'Transactions.output_count', 'Transactions.hash', 'Transactions.version'])->where(['Transactions.block_hash_id' => $block->hash])->toArray();
            $last_block = $this->Blocks->find()->select(['height'])->order(['height' => 'desc'])->first();
            $confirmations = $last_block->height - $block->height + 1;
            $this->set('block', $block);
            $this->set('blockTxs', $txs);
            $this->set('confirmations', $confirmations);
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

        $tx = $this->Transactions->find()->where(['Transactions.hash' => $hash])->first();
        if (!$tx) {
            return $this->redirect('/');
        }

        $block = $this->Blocks->find()->select(['confirmations', 'height'])->where(['hash' => $tx->block_hash_id])->first();
        $confirmations = 0;
        if($tx->block_hash_id == 'MEMPOOL') {
            $confirmations = 0;
        }
        else {
            $last_block = $this->Blocks->find()->select(['height'])->order(['height' => 'desc'])->first();
            $confirmations = $last_block->height - $block->height + 1;
        }
        $inputs = $this->Inputs->find()->where(['transaction_id' => $tx->id])->order(['prevout_n' => 'asc'])->toArray();
        foreach($inputs as $input) {
            $inputAddresses = $this->Addresses->find()->select(['id', 'address'])->where(['id' => $input->input_address_id])->toArray();
            $input->input_addresses = $inputAddresses;
        }

        $outputs = $this->Outputs->find()->select($this->Outputs)->select(['spend_input_hash' => 'I.transaction_hash', 'spend_input_id' => 'I.id'])->where(['Outputs.transaction_id' => $tx->id])->leftJoin(['I' => 'input'], ['I.id = Outputs.spent_by_input_id'])->order(['Outputs.vout' => 'asc'])->toArray();
        for ($i = 0; $i < count($outputs); $i++) {
            $outputs[$i]->IsClaim = (strpos($outputs[$i]->script_pub_key_asm, 'CLAIM') > -1);
            $outputs[$i]->IsSupportClaim = (strpos($outputs[$i]->script_pub_key_asm, 'SUPPORT_CLAIM') > -1);
            $outputs[$i]->IsUpdateClaim = (strpos($outputs[$i]->script_pub_key_asm, 'UPDATE_CLAIM') > -1);
            $claim = $this->Claims->find()->select(['id', 'claim_id', 'claim_address', 'vout', 'transaction_hash_id'])->where(['transaction_hash_id' => $tx->hash, 'vout' => $outputs[$i]->vout])->first();
            $outputs[$i]->Claim = $claim;

            $output_address = trim($outputs[$i]->address_list, '[""]');
             if(!$output_address && $claim) {
                $output_address = $claim->claim_address;
            }
            $address = $this->Addresses->find()->select(['address'])->where(['address' => $output_address])->first();
            $outputs[$i]->output_addresses = [$address];
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
        $richList = $this->Addresses->find()->where(['address <>' => 'bHW58d37s1hBjj3wPBkn5zpCX3F8ZW3uWf'])->order(['balance' => 'DESC'])->limit(500)->toArray();

        $priceRate = 0;
        if(isset($this->redis)) {
            $priceInfo = json_decode($this->redis->get(self::lbcPriceKey));
            if (isset($priceInfo->price)) {
                $priceRate = $priceInfo->price;
            }
        }

        $lbryAddresses = ['rEqocTgdPdoD8NEbrECTUPfpquJ4zPVCJ8', 'rKaAUDxr24hHNNTQuNtRvNt8SGYJMdLXo3', 'r7hj61jdbGXcsccxw8UmEFCReZoCWLRr7t', 'bRo4FEeqqxY7nWFANsZsuKEWByEgkvz8Qt', 'bU2XUzckfpdEuQNemKvhPT1gexQ3GG3SC2', 'bay3VA6YTQBL4WLobbG7CthmoGeUKXuXkD', 'bLPbiXBp6Vr3NSnsHzDsLNzoy5o36re9Cz', 'bVUrbCK8hcZ5XWti7b9eNxKEBxzc1rr393', 'bZja2VyhAC84a9hMwT8dwTU6rDRXowrjxH', 'bMgqQqYfwzWWYBk5o5dBMXtCndVAoeqy6h', 'bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc', 'bX6napXtY2nVTBRc8PwULBuGWn2i3SCtrN', 'bG1fEEqDVepDy3AbvM8outQ3FQUu76aDot'];
        $totalBalance = 0;
        $maxBalance = 0;
        $minBalance = 0;
        foreach ($richList as $item) {
            $totalBalance = bcadd($totalBalance, $item->balance, 8);
            $minBalance = $minBalance == 0 ? $item->balance : min($minBalance, $item->balance);
            $maxBalance = max($maxBalance, $item->balance);
        }
        for ($i = 0; $i < count($richList); $i++) {
            $item = $richList[$i];
            $percentage = bcdiv($item->balance, $totalBalance, 8) * 100;
            $richList[$i]->Top500Percent = $percentage;
            $richList[$i]->MinMaxPercent = bcdiv($item->balance, $maxBalance, 8) * 100;
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
        $this->loadModel('TransactionAddresses');

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
            $transactionAddresses = $this->TransactionAddresses->find()->where(['address_id' => $address->id])->toArray();
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

            $stmt = $conn->execute(sprintf(
                'SELECT T.id, T.hash, T.input_count, T.output_count, T.block_hash_id, ' .
                '    TA.debit_amount, TA.credit_amount, ' .
                '    B.height, B.confirmations, ' .
                '    IFNULL(T.transaction_time, T.created_at) AS transaction_time ' .
                'FROM transaction T ' .
                'LEFT JOIN block B ON T.block_hash_id = B.hash ' .
                'RIGHT JOIN (SELECT transaction_id, debit_amount, credit_amount FROM transaction_address ' .
                '            WHERE address_id = ?) TA ON TA.transaction_id = T.id ' .
                'ORDER BY transaction_time DESC LIMIT %d, %d', $offset, $pageLimit), [$address->id]);
            $recentTxs = $stmt->fetchAll(\PDO::FETCH_OBJ);

            foreach($transactionAddresses as $ta) {
                $totalRecvAmount += $ta->credit_amount + 0;
                $totalSentAmount += $ta->debit_amount + 0;
            }
            $balanceAmount = $totalRecvAmount - $totalSentAmount;
        }

        $this->set('offset', $offset);
        $this->set('canTag', $canTag);
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
        $qrCode->setWriterByName('png');
        $qrCode->setMargin(10);
        $qrCode->setEncoding('UTF-8');
        $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevel(ErrorCorrectionLevel::HIGH));
        $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
        $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);
        $qrCode->setLogoWidth(150);
        $qrCode->setValidateResult(false);

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

        // get avg prices
        /*
        $conn_local = ConnectionManager::get('localdb');
        $stmt_price = $conn_local->execute("SELECT AVG(USD) AS AvgUSD, DATE_FORMAT(Created, '$sqlDateFormat') AS TimePeriod " .
                               "FROM PriceHistory WHERE DATE_FORMAT(Created, '$sqlDateFormat') >= ? GROUP BY TimePeriod ORDER BY TimePeriod ASC", [$start->format($dateFormat)]);
        $avgPrices = $stmt_price->fetchAll(\PDO::FETCH_OBJ);
        foreach ($avgPrices as $price) {
            if (!isset($resultSet[$price->TimePeriod])) {
                $resultSet[$price->TimePeriod] = [];
            }
            $resultSet[$price->TimePeriod]['AvgUSD'] = (float) $price->AvgUSD;
        }
        */

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

        return $this->_jsonResponse(['success' => true, 'data' => $resultSet]);
    }

    public function apirealtimeblocks() {
        // Load 10 blocks
        $this->autoRender = false;
        $this->loadModel('Blocks');
        $blocks = $this->Blocks->find()->select(['Height' => 'height', 'BlockTime' => 'block_time', 'TransactionCount'=>'tx_count'])->order(['Height' => 'desc'])->limit(10)->toArray();

        $this->_jsonResponse(['success' => true, 'blocks' => $blocks]);
    }

    public function apirealtimetx() {
        // Load 10 transactions
        $this->autoRender = false;
        $this->loadModel('Transactions');
        $txs = $this->Transactions->find()->select(['id', 'Value' => 'value', 'Hash' => 'hash', 'InputCount' => 'input_count', 'OutputCount' => 'output_count', 'TxTime' => 'transaction_time'])->order(['TxTime' => 'desc'])->limit(10);

        $this->_jsonResponse(['success' => true, 'txs' => $txs]);
    }

    /*protected function _gettxoutsetinfo() {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $txOutSetInfo = new \stdClass();
        $txOutSetInfo->time = $now->format('c');

        $shouldRefreshSet = false;
        if (!$this->redis) {
            $shouldRefreshSet = true;
        } else {
            if (!$this->redis->exists(self::txOutSetInfo)) {
                $shouldRefreshSet = true;
            } else {
                $txOutSetInfo = json_decode($this->redis->get(self::txOutSetInfo));
                $lastTOSIDt = new \DateTime($txOutSetInfo->time);
                $diff = $now->diff($lastTOSIDt);
                $diffMinutes = $diff->i;
                if ($diffMinutes >= 15 || $txOutSetInfo->set == 'N/A') {
                    $shouldRefreshSet = true;
                }
            }
        }

        if ($shouldRefreshSet) {
            $req = ['method' => 'gettxoutsetinfo', 'params' => []];
            try {
                $res = json_decode(self::curl_json_post(self::$rpcurl, json_encode($req)));
                if (!isset($res->result)) {
                    $txOutSetInfo->tosi = 'N/A';
                }
                $txOutSetInfo->tosi = $res->result;
            } catch (\Exception $e) {
                $txOutSetInfo->tosi = 'N/A';
            }
            $txOutSetInfo->time = $now->format('c');
            if ($this->redis) {
                $this->redis->set(self::txOutSetInfo, json_encode($txOutSetInfo));
            }
        }

        return (isset($txOutSetInfo->tosi)) ? $txOutSetInfo->tosi : 'N/A';
    }*/

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
        $blocks = $this->Blocks->find()->select(['Difficulty' => 'difficulty', 'Hash' => 'hash', 'Height' => 'height', 'BlockTime' => 'block_time', 'BlockSize' => 'block_size', 'TransactionCount' => 'tx_count'])->order(['Height' => 'desc'])->limit(6)->toArray();
        for ($i = 0; $i < count($blocks); $i++) {
            $blocks[$i]->Difficulty = number_format($blocks[$i]->Difficulty, 2, '.', '');
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

        $address = $this->Addresses->find()->select(['id', 'balance'])->where(['address' => $base58address])->first();
        if (!$address) {
            return $this->_jsonError('Could not find address.', 400);
        }

        return $this->_jsonResponse(['success' => true, ['balance' => ['confirmed' => $address->balance, 'unconfirmed' => 0]]]);
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
                               'SELECT T.hash AS transaction_hash, O.vout, O.value, O.address_list, O.script_pub_key_asm, O.script_pub_key_hex, O.type, O.required_signatures, B.confirmations ' .
                               'FROM transaction T ' .
                               'JOIN output O ON O.transaction_id = T.id ' .
                               'JOIN block B ON B.hash = T.block_hash_id ' .
                               'WHERE O.id IN (SELECT O2.id FROM output O2 WHERE address_id IN (%s)) AND O.is_spent <> 1 ORDER BY T.transaction_time ASC', implode(',', $params)), $addressIds);
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
        $txoutsetinfo = $this->_gettxoutsetinfo();

        $reservedcommunity = ['rEqocTgdPdoD8NEbrECTUPfpquJ4zPVCJ8'];
        $reservedoperational = ['r7hj61jdbGXcsccxw8UmEFCReZoCWLRr7t'];
        $reservedinstitutional = ['rKaAUDxr24hHNNTQuNtRvNt8SGYJMdLXo3'];
        $reservedaux = [
            'bRo4FEeqqxY7nWFANsZsuKEWByEgkvz8Qt',
            'bU2XUzckfpdEuQNemKvhPT1gexQ3GG3SC2',
            'bay3VA6YTQBL4WLobbG7CthmoGeUKXuXkD',
            'bLPbiXBp6Vr3NSnsHzDsLNzoy5o36re9Cz',
            'bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc',
            'bVUrbCK8hcZ5XWti7b9eNxKEBxzc1rr393',
            'bZja2VyhAC84a9hMwT8dwTU6rDRXowrjxH',
            'bCrboXVztuSbZzVToCWSsu1pEr2oxKHu9v',
            'bMgqQqYfwzWWYBk5o5dBMXtCndVAoeqy6h',
            'bX6napXtY2nVTBRc8PwULBuGWn2i3SCtrN'
        ];
        $allAddresses = array_merge($reservedcommunity, $reservedoperational, $reservedinstitutional, $reservedaux);

        $reservedtotal = $this->Addresses->find()->select(['balance' => 'SUM(balance)'])->where(['Addresses.address IN' => $allAddresses])->first();

        $circulating = (isset($txoutsetinfo) ? $txoutsetinfo->total_amount : 0) - ($reservedtotal->balance);
        return $this->_jsonResponse(['success' => true, 'utxosupply' => ['total' => isset($txoutsetinfo) ? $txoutsetinfo->total_amount : 0, 'circulating' => $circulating]]);
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
            $res = json_decode(self::curl_json_post($this->rpcurl, json_encode($req)));
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

    private function _isClaimBlocked($claim, $claimChannel, $blockedList) {
        if (!$blockedList || !isset($blockedList->data)) {
            // invalid blockedList response
            return false;
        }

        $blockedOutpoints = $blockedList->data->outpoints;
        $claimIsBlocked = false;
        foreach ($blockedOutpoints as $outpoint) {
            // $parts[0] = txid
            // $parts[1] = vout
            $parts = explode(':', $outpoint);
            if ($claim->transaction_hash_id == $parts[0] && $claim->vout == $parts[1]) {
                $claimIsBlocked = true;
                break;
            }

            // check if the publisher (channel) is blocked
            // block the channel if that's the case
            if ($claimChannel && $claimChannel->transaction_hash_id == $parts[0] && $claimChannel->vout == $parts[1]) {
                $claimIsBlocked = true;
                break;
            }
        }

        return $claimIsBlocked;
    }

    private function _gettxoutsetinfo() {
        $cachedOutsetInfo = Cache::read('gettxoutsetinfo', 'api_requests');
        if ($cachedOutsetInfo !== false) {
            $res = json_decode($cachedOutsetInfo);
            if (isset($res->result)) {
                return $res->result;
            }
        }

        $req = ['method' => 'gettxoutsetinfo', 'params' => []];
        try {
            $response = self::curl_json_post($this->rpcurl, json_encode($req));
            $res = json_decode($response);
            if (!isset($res->result)) {
                return null;
            }
            Cache::write('gettxoutsetinfo', $response, 'api_requests');
            return $res->result;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function _getBlockedList() {
        $cachedList = Cache::read('list_blocked', 'api_requests');
        if ($cachedList !== false) {
            return $cachedList;
        }

        // get the result from the api
        $response = self::curl_get(self::blockedListUrl);
        Cache::write('list_blocked', $response, 'api_requests');
        return $response;
    }
}
