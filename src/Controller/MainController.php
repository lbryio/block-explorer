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
    }

    protected function _getLatestPrice() {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $priceInfo = new \stdClass();
        $priceInfo->time = $now->format('c');

        $shouldRefreshPrice = false;
        if (!$this->redis->exists(self::lbcPriceKey)) {
            $shouldRefreshPrice = true;
        } else {
            $priceInfo = json_decode($this->redis->get(self::lbcPriceKey));
            $lastPriceDt = new \DateTime($priceInfo->time);
            $diff = $now->diff($lastPriceDt);
            $diffHours = $diff->h;
            $diffHours = $diffHours + ($diff->days * 24);
            if ($diffHours >= 3) {
                $shouldRefreshPrice = true;
            }
        }

        if ($shouldRefreshPrice) {
            $btrxjson = json_decode(self::curl_get(self::bittrexMarketUrl));
            $blckjson = json_decode(self::curl_get(self::blockchainTickerUrl));

            if ($btrxjson->success) {
                $onelbc = $btrxjson->result->Ask;
                $lbcPrice = 0;
                if (isset($blckjson->USD)) {
                    $lbcPrice = $onelbc * $blckjson->USD->buy;
                    $priceInfo->price = number_format($lbcPrice, 2, '.', '');
                    $priceInfo->time = $now->format('c');
                    $this->redis->set(self::lbcPriceKey, json_encode($priceInfo));
                }
            }
        }

        $lbcUsdPrice = isset($priceInfo->price) ? '$' . $priceInfo->price : 'N/A';
        return $lbcUsdPrice;
    }

    public function index() {
        $this->loadModel('Blocks');

        $lbcUsdPrice = $this->_getLatestPrice();
        $this->set('lbcUsdPrice', $lbcUsdPrice);

        $blocks = $this->Blocks->find()->select(['Chainwork', 'Confirmations', 'Difficulty', 'Hash', 'Height', 'TransactionHashes', 'BlockTime', 'BlockSize'])->
            order(['Height' => 'desc'])->limit(6)->toArray();
        for ($i = 0; $i < count($blocks); $i++) {
            $tx_hashes = json_decode($blocks[$i]->TransactionHashes);
            $blocks[$i]->TransactionCount = count($tx_hashes);
        }

        // try to calculate the hashrate based on the last 12 blocks found
        $diffBlocks = $this->Blocks->find()->select(['Chainwork', 'BlockTime', 'Difficulty'])->order(['Height' => 'desc'])->limit(12)->toArray();
        $hashRate = 'N/A';
        if (count($diffBlocks) > 1) {
            $highestBlock = $diffBlocks[0];
            $lowestBlock = $diffBlocks[count($diffBlocks) - 1];

            $maxTime = max($highestBlock->BlockTime, $lowestBlock->BlockTime);
            $minTime = min($highestBlock->BlockTime, $lowestBlock->BlockTime);
            $timeDiff = $maxTime - $minTime;
            $math = EccFactory::getAdapter();
            $workDiff = bcsub($math->hexDec($highestBlock->Chainwork), $math->hexDec($lowestBlock->Chainwork));
            if ($timeDiff > 0) {
                $hashRate = $this->_formatHashRate(bcdiv($workDiff, $timeDiff)) . '/s';
            }
        }

        $this->set('recentBlocks', $blocks);
        $this->set('hashRate', $hashRate);
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
        /*if ($value > 1000000000000) {
            return number_format( $value / 1000000000000, 2, '.', '' ) . ' TH';
        }*/
        if ($value > 1000000000) {
            return number_format( $value / 1000000000, 2, '.', '' ) . ' GH';
        }
        if ($value > 1000000) {
            return number_format( $value / 1000000, 2, '.', '' ) . ' MH';
        }
        if ($value > 1000) {
            return number_format( $value / 1000, 2, '.', '' ) . ' KH';
        }

        return number_format($value) . ' H';
    }

    public function find() {
        $criteria = $this->request->query('q');
        if ($criteria === null || strlen(trim($criteria)) == 0) {
            return $this->redirect('/');
        }

        $this->loadModel('Blocks');
        $this->loadModel('Addresses');
        $this->loadModel('Transactions');

        if (is_numeric($criteria)) {
            $height = (int) $criteria;
            $block = $this->Blocks->find()->select(['Id'])->where(['Height' => $height])->first();
            if ($block) {
                return $this->redirect('/blocks/' . $height);
            }
        } else if (strlen(trim($criteria)) <= 40) {
            // Address
            $address = $this->Addresses->find()->select(['Id', 'Address'])->where(['Address' => $criteria])->first();
            if ($address) {
                return $this->redirect('/address/' . $address->Address);
            }
        } else {
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
        }

        // Not found, redirect to index
        return $this->redirect('/');
    }

    public function blocks($height = null) {
        $this->loadModel('Blocks');

        if ($height === null) {
            // paginate blocks
            return $this->redirect('/');
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

        if (!$hash) {
            return $this->redirect('/');
        }

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

    public function address($addr = null) {
        set_time_limit(0);

        $this->loadModel('Addresses');
        $this->loadModel('Transactions');
        $this->loadModel('Inputs');
        $this->loadModel('Outputs');

        if (!$addr) {
            return $this->redirect('/');
        }

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

            $stmt = $conn->execute('SELECT A.TotalReceived, A.TotalSent FROM Addresses A WHERE A.Id = ?', [$address->Id]);
            $totals = $stmt->fetch(\PDO::FETCH_OBJ);

            $stmt = $conn->execute('SELECT T.Id, T.Hash, T.InputCount, T.OutputCount, T.Value, ' .
                                   'TA.DebitAmount, TA.CreditAmount, ' .
                                   'B.Height, B.Confirmations, IFNULL(T.TransactionTime, T.CreatedTime) AS TxTime ' .
                                   'FROM Transactions T ' .
                                   'LEFT JOIN Blocks B ON T.BlockHash = B.Hash ' .
                                   'RIGHT JOIN (SELECT TransactionId, DebitAmount, CreditAmount FROM TransactionsAddresses ' .
                                   '            WHERE AddressId = ? ORDER BY TransactionTime DESC LIMIT 0, 20) TA ON TA.TransactionId = T.Id', [$addressId]);
            $recentTxs = $stmt->fetchAll(\PDO::FETCH_OBJ);

            $totalRecvAmount = $totals->TotalReceived == 0 ? '0' : $totals->TotalReceived + 0;
            $totalSentAmount = $totals->TotalSent == 0 ? '0' : $totals->TotalSent + 0;
            $balanceAmount = bcsub($totalRecvAmount, $totalSentAmount, 8) + 0;
        }

        $this->set('canTag', $canTag);
        $this->set('pending', $pending);
        $this->set('tagRequestAmount', $tagRequestAmount);
        $this->set('address', $address);
        $this->set('totalReceived', $totalRecvAmount);
        $this->set('totalSent', $totalSentAmount);
        $this->set('balanceAmount', $balanceAmount);
        $this->set('recentTxs', $recentTxs);
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
        $diffBlocks = $this->Blocks->find()->select(['Chainwork', 'BlockTime', 'Difficulty'])->order(['Height' => 'desc'])->limit(12)->toArray();
        $hashRate = 'N/A';
        if (count($diffBlocks) > 1) {
            $highestBlock = $diffBlocks[0];
            $lowestBlock = $diffBlocks[count($diffBlocks) - 1];

            $maxTime = max($highestBlock->BlockTime, $lowestBlock->BlockTime);
            $minTime = min($highestBlock->BlockTime, $lowestBlock->BlockTime);
            $timeDiff = $maxTime - $minTime;
            $math = EccFactory::getAdapter();
            $workDiff = bcsub($math->hexDec($highestBlock->Chainwork), $math->hexDec($lowestBlock->Chainwork));
            if ($timeDiff > 0) {
                $hashRate = $this->_formatHashRate(bcdiv($workDiff, $timeDiff)) . '/s';
            }
        }

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
            return $this->_jsonError('Invalid base58 address not specified.', 400);
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

    protected function _jsonResponse($object = [], $statusCode = null)
    {
        $this->response->statusCode($statusCode);
        $this->response->type('json');
        $this->response->body(json_encode($object));
    }

    protected function _jsonError($message, $statusCode = null) {
        return $this->_jsonResponse(['error' => true, 'message' => $message], $statusCode);
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