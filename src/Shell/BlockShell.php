<?php

namespace App\Shell;

use Cake\Console\ConsoleOutput;
use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Mdanter\Ecc\EccFactory;

class BlockShell extends Shell {

    public static $rpcurl;

    const mempooltxkey = 'lbc.mempooltx';

    const pubKeyAddress = [0, 85];

    const scriptAddress = [5, 122];

    const redisurl = 'tcp://127.0.0.1:6379';

    public function initialize() {
        parent::initialize();
        self::$rpcurl = Configure::read('Lbry.RpcUrl');
        $this->loadModel('Blocks');
        $this->loadModel('Addresses');
        $this->loadModel('Claims');
        $this->loadModel('ClaimStreams');
        $this->loadModel('Inputs');
        $this->loadModel('Outputs');
        $this->loadModel('Transactions');
    }

    public function main() {
        $this->out('No arguments specified');
    }

    public function fixscripthashtx() {
        $conn = ConnectionManager::get('default');
        $otxs = $this->Outputs->find()->select(['TransactionId'])->distinct(['TransactionId'])->where(['Type' => 'scripthash'])->toArray();
        foreach ($otxs as $otx) {
            $txid = $otx->TransactionId;
            $tx = $this->Transactions->find()->select(['Hash'])->where(['Id' => $txid])->first();
            $req = ['method' => 'getrawtransaction', 'params' => [$tx->Hash]];
            $response = self::curl_json_post(self::$rpcurl, json_encode($req));
            $json = json_decode($response);
            $tx_result = $json->result;
            $raw_tx = $tx_result;
            $tx_data = self::decode_tx($raw_tx);
            $all_tx_data = $this->txdb_data_from_decoded($tx_data);

            foreach ($all_tx_data['outputs'] as $out) {
                if ($out['Type'] != 'scripthash') {
                    continue;
                }

                // get the old address
                $old_output = $this->Outputs->find()->select(['Id', 'Addresses'])->where(['TransactionId' => $txid, 'Vout' => $out['Vout']])->first();
                if ($old_output) {
                    $old_addresses = json_decode($old_output->Addresses);
                    $old_address = $old_addresses[0];
                    $new_addresses = json_decode($out['Addresses']);
                    $new_address = $new_addresses[0];

                    // update the output with new addresses array
                    $conn->begin();
                    $conn->execute('UPDATE Outputs SET Addresses = ? WHERE Id = ?', [$out['Addresses'], $old_output->Id]);

                    // update the old address with the new one
                    $conn->execute('UPDATE Addresses SET Address = ? WHERE Address = ?', [$new_address, $old_address]);
                    $conn->commit();

                    echo "$old_address => $new_address\n";
                }
            }
        }
    }

    public static function hex2str($hex){
        $string = '';
        for ($i = 0; $i < strlen($hex)-1; $i+=2){
            $string .= chr(hexdec($hex[$i].$hex[$i+1]));
        }
        return $string;
    }

    public function updateclaimfees() {
        self::lock('claimfees');

        $conn = ConnectionManager::get('default');
        try {
            $stmt = $conn->execute('SELECT CS.Id, CS.Stream FROM ClaimStreams CS JOIN Claims C ON C.Id = CS.Id WHERE C.Fee = 0 AND C.Id <= 11462 ORDER BY Id ASC');
            $claims = $stmt->fetchAll(\PDO::FETCH_OBJ);
            foreach ($claims as $claim) {
                $stream = json_decode($claim->Stream);
                if (isset($stream->metadata->fee) && $stream->metadata->fee->amount > 0) {
                    $fee = $stream->metadata->fee->amount;
                    $currency = $stream->metadata->fee->currency;

                    $conn->execute('UPDATE Claims SET Fee = ?, FeeCurrency = ? WHERE Id = ?', [$fee, $currency, $claim->Id]);
                    echo "Updated fee for claim ID: $claim->Id. Fee: $fee, Currency: $currency\n";
                }
            }
        } catch (\Exception $e) {
            print_r($e);
        }

        self::unlock('claimfees');
    }

    public function buildclaimindex() {
        self::lock('buildindex');

        // start with all txs
        $decoder_url = 'http://127.0.0.1:5000';
        $redis = self::_init_redis();
        $conn = ConnectionManager::get('default');
        $redis_key = 'claim.oid';
        $last_claim_oid = $redis->exists($redis_key) ? $redis->get($redis_key) : 0;
        try {
            $stmt = $conn->execute('SELECT COUNT(Id) AS RecordCount FROM Outputs WHERE Id > ?', [$last_claim_oid]);
            $count = min(500000, $stmt->fetch(\PDO::FETCH_OBJ)->RecordCount);

            $idx = 0;
            $stmt = $conn->execute('SELECT O.Id, O.TransactionId, O.Vout, O.ScriptPubKeyAsm, T.Hash, IFNULL(T.TransactionTime, T.CreatedTime) AS TxTime FROM Outputs O ' .
                                   'JOIN Transactions T ON T.Id = O.TransactionId WHERE O.Id > ? ORDER BY O.Id ASC LIMIT 500000',
                                   [$last_claim_oid]);
            while ($out = $stmt->fetch(\PDO::FETCH_OBJ)) {
                $idx++;
                $idx_str = str_pad($idx, strlen($count), '0', STR_PAD_LEFT);

                $txid = $out->TransactionId;
                $vout = $out->Vout;

                if (strpos($out->ScriptPubKeyAsm, 'OP_CLAIM_NAME') !== false) {
                    // check if the claim already exists in the claims table
                    $stmt2 = $conn->execute('SELECT Id FROM Claims WHERE TransactionHash = ? AND Vout = ?', [$out->Hash, $out->Vout]);
                    $exist_claim = $stmt2->fetch(\PDO::FETCH_OBJ);
                    if ($exist_claim) {
                        echo "[$idx_str/$count] claim already exists for [$out->Hash:$vout]. Skipping.\n";
                        continue;
                    }

                    $asm_parts = explode(' ', $out->ScriptPubKeyAsm, 4);
                    $name_hex = $asm_parts[1];
                    $claim_name = @pack('H*', $name_hex);

                    // decode claim
                    $url = sprintf("%s/claim_decode/%s", $decoder_url, $claim_name);
                    $json = null;
                    try {
                        $json = self::curl_json_get($url);
                    } catch (\Exception $e) {
                        echo "[$idx_str/$count] claimdecode failed for [$out->Hash:$vout]. Skipping.\n";
                        continue;
                    }
                    $claim = json_decode($json);

                    if ($claim) {
                        $req = ['method' => 'getvalueforname', 'params' => [$claim_name]];
                        $json = null;
                        try {
                            $json = json_decode(self::curl_json_post(self::$rpcurl, json_encode($req)));
                            if (!$json) {
                                echo "[$idx_str/$count] getvalueforname failed for [$out->Hash:$vout]. Skipping.\n";
                                continue;
                            }
                        } catch (\Exception $e) {
                            echo "[$idx_str/$count] getvalueforname failed for [$out->Hash:$vout]. Skipping.\n";
                            continue;
                        }

                        echo "[$idx_str/$count] claim found for [$out->Hash:$vout]. Processing claim... \n";
                        $claim_data = [];

                        $claim_id = $json->result->claimId;
                        $tx_dt = \DateTime::createFromFormat('U', $out->TxTime);

                        $claim_stream_data = null;
                        if ($claim->claimType === 'streamType') {
                            // Build claim object to save
                            $claim_data = [
                                'ClaimId' => $claim_id,
                                'TransactionHash' => $out->Hash,
                                'Vout' => $out->Vout,
                                'Name' => $claim_name,
                                'Version' => $claim->version,
                                'ClaimType' => 2, // streamType
                                'ContentType' => isset($claim->stream->source->contentType) ? $claim->stream->source->contentType : null,
                                'Title' => isset($claim->stream->metadata->title) ? $claim->stream->metadata->title : null,
                                'Description' => isset($claim->stream->metadata->description) ? $claim->stream->metadata->description : null,
                                'Language' => isset($claim->stream->metadata->language) ? $claim->stream->metadata->language : null,
                                'Author' => isset($claim->stream->metadata->author) ? $claim->stream->metadata->author : null,
                                'ThumbnailUrl' => isset($claim->stream->metadata->thumbnail) ? $claim->stream->metadata->thumbnail : null,
                                'IsNSFW' => isset($claim->stream->metadata->nsfw) ? $claim->stream->metadata->nsfw : 0,
                                'Fee' => isset($claim->stream->metadata->fee) ? $claim->stream->metadata->fee->amount : 0,
                                'FeeCurrency' => isset($claim->stream->metadata->fee) ? $claim->stream->metadata->fee->currency : 0,
                                'Created' => $tx_dt->format('Y-m-d H:i:s'),
                                'Modified' => $tx_dt->format('Y-m-d H:i:s')
                            ];

                            $claim_stream_data = [
                                'Stream' => json_encode($claim->stream)
                            ];

                            if (isset($claim->publisherSignature)) {
                                $sig_claim = $this->Claims->find()->select(['Id', 'ClaimId', 'Name'])->where(['ClaimId' => $claim->publisherSignature->certificateId])->first();
                                if ($sig_claim) {
                                    $claim_data['PublisherId'] = $sig_claim->ClaimId;
                                    $claim_data['PublisherName'] = $sig_claim->Name;
                                }
                            }
                        } else {
                            $claim_data = [
                                'ClaimId' => $claim_id,
                                'TransactionHash' => $out->Hash,
                                'Vout' => $out->Vout,
                                'Name' => $claim_name,
                                'Version' => $claim->version,
                                'ClaimType' => 1,
                                'Certificate' => json_encode($claim->certificate),
                                'Created' => $tx_dt->format('Y-m-d H:i:s'),
                                'Modified' => $tx_dt->format('Y-m-d H:i:s')
                            ];
                        }

                        $conn->begin();
                        $data_error = false;

                        $claim_entity = $this->Claims->newEntity($claim_data);
                        $res = $this->Claims->save($claim_entity);

                        if (!$res) {
                            $data_error = true;
                            echo "[$idx_str/$count] claim for [$out->Hash:$vout] FAILED to save.\n";
                        }

                        if (!$data_error) {
                            if ($claim_stream_data) {
                                $claim_stream_data['Id'] = $claim_entity->Id;
                                $claim_stream_entity = $this->ClaimStreams->newEntity($claim_stream_data);

                                $res = $this->ClaimStreams->save($claim_stream_entity);
                                if (!$res) {
                                    $data_error = true;
                                }
                            }
                        }

                        if (!$data_error) {
                            $conn->commit();
                            echo "[$idx_str/$count] claim for [$out->Hash:$vout] indexed.\n";
                        } else {
                            $conn->rollback();
                            echo "[$idx_str/$count] claim for [$out->Hash:$vout] NOT indexed. Rolled back.\n";
                        }
                    } else {
                        echo "[$idx_str/$count] claim for [$out->Hash:$vout] could not be decoded. Skipping.\n";
                    }
                } else {
                    echo "[$idx_str/$count] no claim found for [$out->Hash:$vout]. Skipping.\n";
                }

                $redis->set($redis_key, $out->Id);
            }
        } catch (\Exception $e) {
            // continue
            print_r($e);
        }

        self::unlock('buildindex');
    }

    protected function _getclaimfortxout($pubkeyasm, $tx_hash, $vout, $tx_time = null) {
        $claim_data = null;
        $claim_stream_data = null;

        $asm_parts = explode(' ', $pubkeyasm, 4);
        $name_hex = $asm_parts[1];
        $claim_name = @pack('H*', $name_hex);

        // decode claim
        $decoder_url = 'http://127.0.0.1:5000';
        $url = sprintf("%s/claim_decode/%s", $decoder_url, $claim_name);
        $json = null;
        try {
            $json = self::curl_json_get($url);
        } catch (\Exception $e) {
            echo "***claimdecode failed for [$tx_hash:$vout]. Skipping.\n";
        }

        if ($json) {
            $claim = json_decode($json);
            if ($claim) {
                $req = ['method' => 'getvalueforname', 'params' => [$claim_name]];
                $json = null;
                try {
                    $json = json_decode(self::curl_json_post(self::$rpcurl, json_encode($req)));
                    if ($json) {
                        $claim_data = [];
                        $claim_id = $json->result->claimId;
                        $now = new \DateTime('now', new \DateTimeZone('UTC'));
                        $tx_dt = ($tx_time != null) ? $tx_time : $now;
                        if ($claim->claimType === 'streamType') {
                            // Build claim object to save
                            $claim_data = [
                                'ClaimId' => $claim_id,
                                'TransactionHash' => $tx_hash,
                                'Vout' => $vout,
                                'Name' => $claim_name,
                                'Version' => $claim->version,
                                'ClaimType' => 2, // streamType
                                'ContentType' => isset($claim->stream->source->contentType) ? $claim->stream->source->contentType : null,
                                'Title' => isset($claim->stream->metadata->title) ? $claim->stream->metadata->title : null,
                                'Description' => isset($claim->stream->metadata->description) ? $claim->stream->metadata->description : null,
                                'Language' => isset($claim->stream->metadata->language) ? $claim->stream->metadata->language : null,
                                'Author' => isset($claim->stream->metadata->author) ? $claim->stream->metadata->author : null,
                                'ThumbnailUrl' => isset($claim->stream->metadata->thumbnail) ? $claim->stream->metadata->thumbnail : null,
                                'IsNSFW' => isset($claim->stream->metadata->nsfw) ? $claim->stream->metadata->nsfw : 0,
                                'Fee' => isset($claim->stream->metadata->fee) ? $claim->stream->metadata->fee->amount : 0,
                                'FeeCurrency' => isset($claim->stream->metadata->fee) ? $claim->stream->metadata->fee->currency : 0,
                                'Created' => $tx_dt->format('Y-m-d H:i:s'),
                                'Modified' => $tx_dt->format('Y-m-d H:i:s')
                            ];

                            $claim_stream_data = [
                                'Stream' => json_encode($claim->stream)
                            ];

                            if (isset($claim->publisherSignature)) {
                                $sig_claim = $this->Claims->find()->select(['Id', 'ClaimId', 'Name'])->where(['ClaimId' => $claim->publisherSignature->certificateId])->first();
                                if ($sig_claim) {
                                    $claim_data['PublisherId'] = $sig_claim->ClaimId;
                                    $claim_data['PublisherName'] = $sig_claim->Name;
                                }
                            }
                        } else {
                            $claim_data = [
                                'ClaimId' => $claim_id,
                                'TransactionHash' => $tx_hash,
                                'Vout' => $vout,
                                'Name' => $claim_name,
                                'Version' => $claim->version,
                                'ClaimType' => 1,
                                'Certificate' => json_encode($claim->certificate),
                                'Created' => $tx_dt->format('Y-m-d H:i:s'),
                                'Modified' => $tx_dt->format('Y-m-d H:i:s')
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    echo "***getvalueforname failed for [$out->Hash:$vout]. Skipping.\n";
                }
            }
        }

        return ['claim_data' => $claim_data, 'claim_stream_data' => $claim_stream_data];
    }

    public function fixzerooutputs() {
        self::lock('zerooutputs');

        $redis = self::_init_redis();
        $conn = ConnectionManager::get('default');

        /** 2017-06-12 21:38:07 **/
        //$last_fixed_txid = $redis->exists('fix.txid') ? $redis->get('fix.txid') : 0;
        try {
            $stmt = $conn->execute('SELECT Id FROM Transactions WHERE Created >= ? AND Created <= ? LIMIT 1000000', ['2017-06-15 20:44:50', '2017-06-16 08:02:09']);
            $txids = $stmt->fetchAll(\PDO::FETCH_OBJ);

            $count = count($txids);
            $idx = 0;
            foreach ($txids as $distincttx) {
                $idx++;
                $idx_str = str_pad($idx, strlen($count), '0', STR_PAD_LEFT);
                $txid = $distincttx->Id;
                echo "[$idx_str/$count] Processing txid: $txid... ";
                $total_diff = 0;

                // findtx
                $start_ms = round(microtime(true) * 1000);
                $tx = $this->Transactions->find()->select(['Hash'])->where(['Id' => $txid])->first();
                $diff_ms = (round(microtime(true) * 1000)) - $start_ms;
                $total_diff += $diff_ms;
                echo "findtx took {$diff_ms}ms. ";

                // Get the inputs and outputs
                // Get the raw transaction (Use getrawtx daemon instead (for speed!!!)

                // getraw
                $req = ['method' => 'getrawtransaction', 'params' => [$tx->Hash]];
                $start_ms = round(microtime(true) * 1000);
                $response = self::curl_json_post(self::$rpcurl, json_encode($req));
                $diff_ms = (round(microtime(true) * 1000)) - $start_ms;
                $total_diff += $diff_ms;
                echo "getrawtx took {$diff_ms}ms. ";

                $start_ms = round(microtime(true) * 1000);
                $json = json_decode($response);
                $tx_result = $json->result;
                $raw_tx = $tx_result;
                $tx_data = self::decode_tx($raw_tx);

                $all_tx_data = $this->txdb_data_from_decoded($tx_data);

                $inputs = $all_tx_data['inputs'];
                $outputs = $all_tx_data['outputs'];

                $addr_id_map = [];
                $addr_id_drcr = []; // debits and credits grouped by address
                $total_tx_value = 0;

                $diff_ms = (round(microtime(true) * 1000)) - $start_ms;
                $total_diff += $diff_ms;
                echo "decodetx took {$diff_ms}ms. ";

                // Create / update addresses
                $addr_id_map = [];
                $new_addr_map = [];
                foreach($all_tx_data['addresses'] as $address => $addro) {
                    $prev_addr = $this->Addresses->find()->select(['Id'])->where(['Address' => $address])->first();
                    if (!$prev_addr) {
                        $new_addr = [
                            'Address' => $address,
                            'FirstSeen' => $block_ts->format('Y-m-d H:i:s')
                        ];
                        $entity = $this->Addresses->newEntity($new_addr);
                        $res = $this->Addresses->save($entity);
                        if (!$res) {
                            $data_error = true;
                        } else {
                            $addr_id_map[$address] = $entity->Id;
                        }
                        $new_addr_map[$address] = 1;
                    } else {
                        $addr_id_map[$address] = $prev_addr->Id;
                    }
                }

                $start_ms = round(microtime(true) * 1000);
                $num_outputs = count($outputs);
                foreach ($outputs as $out) {
                    $vout = $out['Vout'];
                    $total_tx_value = bcadd($total_tx_value, $out['Value'], 8);

                    // check if the output exists
                    $stmt = $conn->execute('SELECT Id FROM Outputs WHERE TransactionId = ? AND Vout = ?', [$txid, $vout]);
                    $exist_output = $stmt->fetch(\PDO::FETCH_OBJ);

                    if (!$exist_output) {
                        $out['TransactionId'] = $txid;
                        $out_entity = $this->Outputs->newEntity($out);

                        //$stmt->execute('INSERT INTO Outputs')
                        $conn->execute('INSERT INTO Outputs (TransactionId, Vout, Value, Type, ScriptPubKeyAsm, ScriptPubKeyHex, RequiredSignatures, Hash160, Addresses, Created, Modified) '.
                                       'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                                       [$out['TransactionId'],
                                        $out['Vout'],
                                        $out['Value'],
                                        $out['Type'],
                                        $out['ScriptPubKeyAsm'],
                                        $out['ScriptPubKeyHex'],
                                        $out['RequiredSignatures'],
                                        $out['Hash160'],
                                        $out['Addresses']
                                       ]);

                        // get the last insert id
                        $stmt = $conn->execute('SELECT LAST_INSERT_ID() AS outputId');
                        $linsert = $stmt->fetch(\PDO::FETCH_OBJ);
                        $out_entity->Id = $linsert->outputId;

                        if ($out_entity->Id === 0) {
                            $data_error = true;
                            break;
                        }

                        $json_addr = json_decode($out['Addresses']);
                        $address = $json_addr[0];

                        // Get the address ID
                        $addr_id = -1;
                        if (isset($addr_id_map[$address])) {
                            $addr_id = $addr_id_map[$address];
                        } else {
                            $src_addr = $this->Addresses->find()->select(['Id'])->where(['Address' => $address])->first();
                            if ($src_addr) {
                                $addr_id = $src_addr->Id;
                                $addr_id_map[$address] = $addr_id;
                            }
                        }

                        if ($addr_id > -1) {
                            $conn->execute('INSERT INTO OutputsAddresses (OutputId, AddressId) VALUES (?, ?) ON DUPLICATE KEY UPDATE OutputId = OutputId', [$out_entity->Id, $addr_id]);
                            $conn->execute('INSERT INTO TransactionsAddresses (TransactionId, AddressId) VALUES (?, ?) ON DUPLICATE KEY UPDATE TransactionId = TransactionId', [$txid, $addr_id]);
                        }

                        if ($addr_id > -1 && isset($new_addr_map[$address])) {
                            if (!isset($addr_id_drcr[$addr_id])) {
                                $addr_id_drcr[$addr_id] = ['debit' => 0, 'credit' => 0];
                            }
                            $addr_id_drcr[$addr_id]['credit'] = bcadd($addr_id_drcr[$addr_id]['credit'], $out['Value'], 8);

                            // Update the Received amount for the address based on the output
                            $conn->execute('UPDATE Addresses SET TotalReceived = TotalReceived + ? WHERE Id = ?', [$out['Value'], $addr_id]);
                        }
                    }
                }
                $diff_ms = (round(microtime(true) * 1000)) - $start_ms;
                $total_diff += $diff_ms;
                echo "$num_outputs output(s) took {$diff_ms}ms. ";

                // Fix the input values
                $start_ms = round(microtime(true) * 1000);
                $num_inputs = count($inputs);
                foreach ($inputs as $in) {
                    if (isset($in['PrevoutHash'])) {
                        $prevout_hash = $in['PrevoutHash'];
                        $in_prevout = $in['PrevoutN'];
                        $prevout_tx_id = -1;
                        $prevout_tx = $this->Transactions->find()->select(['Id'])->where(['Hash' => $prevout_hash])->first();
                        if (!$prevout_tx) {
                            continue;
                        }

                        $prevout_tx_id = $prevout_tx->Id;
                        $stmt = $conn->execute('SELECT Value, Addresses FROM Outputs WHERE TransactionId = ? AND Vout = ?', [$prevout_tx_id, $in_prevout]);
                        $src_output = $stmt->fetch(\PDO::FETCH_OBJ);
                        if ($src_output) {
                            $in['Value'] = $src_output->Value;
                            //$conn->execute('UPDATE Inputs SET Value = ? WHERE TransactionId = ? AND PrevoutHash = ? AND PrevoutN = ?', [$in['Value'], $txid, $prevout_hash, $in_prevout]);

                            // Check if the input exists
                            $stmt = $conn->execute('SELECT Id FROM Inputs WHERE TransactionId = ? AND PrevoutHash = ? AND PrevoutN = ?', [$txid, $prevout_hash, $in_prevout]);
                            $exist_input = $stmt->fetch(\PDO::FETCH_OBJ);

                            if (!$exist_input) {
                                $json_addr = json_decode($src_output->Addresses);
                                $address = $json_addr[0];

                                // Get the address ID
                                $addr_id = -1;
                                if (isset($addr_id_map[$address])) {
                                    $addr_id = $addr_id_map[$address];
                                } else {
                                    $src_addr = $this->Addresses->find()->select(['Id'])->where(['Address' => $address])->first();
                                    if ($src_addr) {
                                        $addr_id = $src_addr->Id;
                                        $addr_id_map[$address] = $addr_id;
                                    }
                                }

                                $in_entity = $this->Inputs->newEntity($in);
                                $in['TransactionId'] = $txid;
                                if ($addr_id > -1) {
                                    $in['AddressId'] = $addr_id;
                                }
                                $conn->execute('INSERT INTO Inputs (TransactionId, TransactionHash, AddressId, PrevoutHash, PrevoutN, Sequence, Value, ScriptSigAsm, ScriptSigHex, Created, Modified) ' .
                                               'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                                               [$in['TransactionId'],
                                                $in['TransactionHash'],
                                                isset($in['AddressId']) ? $in['AddressId'] : null,
                                                $in['PrevoutHash'],
                                                $in['PrevoutN'],
                                                $in['Sequence'],
                                                isset($in['Value']) ? $in['Value'] : 0,
                                                $in['ScriptSigAsm'],
                                                $in['ScriptSigHex']
                                                ]);

                                // get last insert id
                                $stmt = $conn->execute('SELECT LAST_INSERT_ID() AS inputId');
                                $linsert = $stmt->fetch(\PDO::FETCH_OBJ);
                                $in_entity->Id = $linsert->inputId;

                                if ($in_entity->Id === 0) {
                                    $data_error = true;
                                    break;
                                }

                                if ($addr_id > -1) {
                                    $conn->execute('INSERT INTO InputsAddresses (InputId, AddressId) VALUES (?, ?) ON DUPLICATE KEY UPDATE InputId = InputId', [$in_entity->Id, $addr_id]);
                                    $conn->execute('INSERT INTO TransactionsAddresses (TransactionId, AddressId) VALUES (?, ?) ON DUPLICATE KEY UPDATE TransactionId = TransactionId', [$txid, $addr_id]);
                                }

                                if ($addr_id > -1 && isset($new_addr_map[$address])) {
                                    if (!isset($addr_id_drcr[$addr_id])) {
                                        $addr_id_drcr[$addr_id] = ['debit' => 0, 'credit' => 0];
                                    }
                                    $addr_id_drcr[$addr_id]['debit'] = bcadd($addr_id_drcr[$addr_id]['debit'], $in['Value'], 8);

                                    // Update total sent
                                    $conn->execute('UPDATE Addresses SET TotalSent = TotalSent + ? WHERE Id = ?', [$in['Value'], $addr_id]);
                                }
                            }
                        }
                    }
                }
                $diff_ms = (round(microtime(true) * 1000)) - $start_ms;
                $total_diff += $diff_ms;
                echo "$num_inputs input(s) took {$diff_ms}ms. ";

                // update tx time
                $start_ms = round(microtime(true) * 1000);
                $upd_addr_ids = [];
                //$conn->execute('UPDATE Transactions SET Value = ? WHERE Id = ?', [$total_tx_value, $txid]);
                foreach ($addr_id_drcr as $addr_id => $drcr) {
                    try {
                        $conn->execute('UPDATE TransactionsAddresses SET DebitAmount = ?, CreditAmount = ? WHERE TransactionId = ? AND AddressId = ?',
                                       [$drcr['debit'], $drcr['credit'], $txid, $addr_id]);
                    } catch (Exception $e) {
                        print_r($e);
                        $data_error = true;
                        break;
                    }
                }

                //$redis->set('fix.txid', $txid);
                $diff_ms = (round(microtime(true) * 1000)) - $start_ms;
                $total_diff += $diff_ms;
                echo "update took {$diff_ms}ms. Total {$total_diff} ms.\n";
            }
        } catch (\Exception $e) {
            print_r($e);
        }

        self::unlock('zerooutputs');
    }

    public function addrtxamounts() {
        set_time_limit(0);

        self::lock('addrtxamounts');

        try {
            $conn = ConnectionManager::get('default');
            $stmt = $conn->execute('SELECT TransactionId, AddressId FROM TransactionsAddresses WHERE DebitAmount = 0 AND CreditAmount = 0 LIMIT 1000000');
            $txaddresses = $stmt->fetchAll(\PDO::FETCH_OBJ);

            $count = count($txaddresses);
            $idx = 0;

            echo "Processing $count tx address combos...\n";
            foreach ($txaddresses as $txaddr) {
                $idx++;
                $idx_str = str_pad($idx, strlen($count), '0', STR_PAD_LEFT);

                // Check the inputs
                $stmt = $conn->execute('SELECT SUM(I.Value) AS DebitAmount FROM Inputs I JOIN InputsAddresses IA ON IA.InputId = I.Id WHERE I.TransactionId = ? AND IA.AddressId = ?',
                                       [$txaddr->TransactionId, $txaddr->AddressId]);
                $res = $stmt->fetch(\PDO::FETCH_OBJ);
                $debitamount = $res->DebitAmount ? $res->DebitAmount : 0;

                $stmt = $conn->execute('SELECT SUM(O.Value) AS CreditAmount FROM Outputs O JOIN OutputsAddresses OA ON OA.OutputId = O.Id WHERE O.TransactionId = ? AND OA.AddressId = ?',
                                       [$txaddr->TransactionId, $txaddr->AddressId]);
                $res = $stmt->fetch(\PDO::FETCH_OBJ);
                $creditamount = $res->CreditAmount ? $res->CreditAmount : 0;

                echo "[$idx_str/$count] Updating tx $txaddr->TransactionId, address id $txaddr->AddressId with debit amount: $debitamount, credit amount: $creditamount... ";
                $conn->execute('UPDATE TransactionsAddresses SET DebitAmount = ?, CreditAmount = ? WHERE TransactionId = ? AND AddressId = ?',
                               [$debitamount, $creditamount, $txaddr->TransactionId, $txaddr->AddressId]);
                echo "Done.\n";
            }
        } catch (\Exception $e) {
            // failed
            print_r($e);
        }

        self::unlock('addrtxamounts');
    }

    private function processtx($tx_hash, $block_ts, $block_data, &$data_error) {
        // Get the raw transaction (Use getrawtx daemon instead (for speed!!!)
        $req = ['method' => 'getrawtransaction', 'params' => [$tx_hash]];
        $response = self::curl_json_post(self::$rpcurl, json_encode($req));
        $json = json_decode($response);
        $tx_result = $json->result;
        $raw_tx = $tx_result;
        $tx_data = self::decode_tx($raw_tx);

        $all_tx_data = $this->txdb_data_from_decoded($tx_data);
        $conn = ConnectionManager::get('default');

        // Create / update addresses
        $addr_id_map = [];
        foreach($all_tx_data['addresses'] as $address => $addrss) {
            $prev_addr = $this->Addresses->find()->select(['Id'])->where(['Address' => $address])->first();
            if (!$prev_addr) {
                $new_addr = [
                    'Address' => $address,
                    'FirstSeen' => $block_ts->format('Y-m-d H:i:s')
                ];
                $entity = $this->Addresses->newEntity($new_addr);
                $res = $this->Addresses->save($entity);
                if (!$res) {
                    $data_error = true;
                } else {
                    $addr_id_map[$address] = $entity->Id;
                }
            } else {
                $addr_id_map[$address] = $prev_addr->Id;
            }
        }

        $addr_id_drcr = []; // debits and credits grouped by address
        $numeric_tx_id = -1;
        if (!$data_error) {
            // Create transaction
            $new_tx = $all_tx_data['tx'];

            $total_tx_value = 0;
            foreach ($all_tx_data['outputs'] as $out) {
                $total_tx_value = bcadd($total_tx_value, $out['Value'], 8);
            }

            if ($block_data) {
                $new_tx['BlockHash'] = $block_data['hash'];
                $new_tx['TransactionTime'] = $block_data['time'];
            }
            $new_tx['TransactionSize'] = ((strlen($raw_tx)) / 2);
            $new_tx['InputCount'] = count($all_tx_data['inputs']);
            $new_tx['OutputCount'] = count($all_tx_data['outputs']);
            $new_tx['Hash'] = $tx_hash;
            $new_tx['Value'] = $total_tx_value;
            $new_tx['Raw'] = $raw_tx;


            $tx_entity = $this->Transactions->newEntity($new_tx);
            $conn->execute('INSERT INTO Transactions (Version, LockTime, BlockHash, TransactionTime, InputCount, OutputCount, TransactionSize, Hash, Value, Raw, Created, Modified) VALUES ' .
                           '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                           [
                            $new_tx['Version'],
                            $new_tx['LockTime'],
                            isset($new_tx['BlockHash']) ? $new_tx['BlockHash'] : null,
                            isset($new_tx['TransactionTime']) ? $new_tx['TransactionTime'] : null,
                            $new_tx['InputCount'],
                            $new_tx['OutputCount'],
                            $new_tx['TransactionSize'],
                            $new_tx['Hash'],
                            $new_tx['Value'],
                            $new_tx['Raw']
                           ]);
            $stmt = $conn->execute('SELECT LAST_INSERT_ID() AS txnId');
            $linsert = $stmt->fetch(\PDO::FETCH_OBJ);
            $tx_entity->Id = $linsert->txnId;
            if ($tx_entity->Id === 0) {
                3;
            } else {
                $numeric_tx_id = $tx_entity->Id;
            }
        }

        if (!$data_error && $numeric_tx_id > 0) {
            // Create the inputs
            $inputs = $all_tx_data['inputs'];
            $outputs = $all_tx_data['outputs'];

            foreach ($inputs as $in) {
                $in['TransactionId'] = $numeric_tx_id;
                $in['TransactionHash'] = $tx_hash;

                if (isset($in['IsCoinbase']) && $in['IsCoinbase'] === 1) {
                    $in_entity = $this->Inputs->newEntity($in);
                    $res = $this->Inputs->save($in_entity);
                    if (!$res) {
                        $data_error = true;
                        break;
                    }
                } else {
                    $in_tx_hash = $in['PrevoutHash'];
                    $in_prevout = $in['PrevoutN'];

                    // Find the corresponding previous output
                    $in_tx = $this->Transactions->find()->select(['Id'])->where(['Hash' => $in_tx_hash])->first();
                    $src_output = null;
                    if ($in_tx) {
                        $stmt = $conn->execute('SELECT Id, Value, Addresses FROM Outputs WHERE TransactionId = ? AND Vout = ?', [$in_tx->Id, $in_prevout]);
                        $src_output = $stmt->fetch(\PDO::FETCH_OBJ);
                        if ($src_output) {
                            $in['Value'] = $src_output->Value;
                            $json_addr = json_decode($src_output->Addresses);
                            $in_addr_id = 0;
                            if (isset($addr_id_map[$json_addr[0]])) {
                                $in['AddressId'] = $addr_id_map[$json_addr[0]];
                            } else {
                                $in_addr = $this->Addresses->find()->select(['Id'])->where(['Address' => $json_addr[0]])->first();
                                if ($in_addr) {
                                    $addr_id_map[$json_addr[0]] = $in_addr->Id;
                                    $in['AddressId'] = $in_addr->Id;
                                }
                            }
                        }
                    }

                    $in_entity = $this->Inputs->newEntity($in);
                    $conn->execute('INSERT INTO Inputs (TransactionId, TransactionHash, AddressId, PrevoutHash, PrevoutN, Sequence, Value, ScriptSigAsm, ScriptSigHex, Created, Modified) ' .
                                   'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                                   [$in['TransactionId'],
                                    $in['TransactionHash'],
                                    isset($in['AddressId']) ? $in['AddressId'] : null,
                                    $in['PrevoutHash'],
                                    $in['PrevoutN'],
                                    $in['Sequence'],
                                    isset($in['Value']) ? $in['Value'] : 0,
                                    $in['ScriptSigAsm'],
                                    $in['ScriptSigHex']
                                    ]);

                    // get last insert id
                    $stmt = $conn->execute('SELECT LAST_INSERT_ID() AS inputId');
                    $linsert = $stmt->fetch(\PDO::FETCH_OBJ);
                    $in_entity->Id = $linsert->inputId;

                    if ($in_entity->Id === 0) {
                        $data_error = true;
                        break;
                    }

                    // Update the src_output spent if successful
                    if ($src_output) {
                        try {
                            $conn->execute('UPDATE Outputs SET IsSpent = 1, SpentByInputId = ? WHERE Id = ?', [$in_entity->Id, $src_output->Id]);
                            $conn->execute('UPDATE Inputs SET PrevoutSpendUpdated = 1 WHERE Id = ?', [$in_entity->Id]);
                        } catch (\Exception $e) {
                            $data_error = true;
                            break;
                        }
                    }

                    if (isset($in['AddressId']) && $in['AddressId'] > 0) {
                        $addr_id = $in['AddressId'];
                        if (!isset($addr_id_drcr[$addr_id])) {
                            $addr_id_drcr[$addr_id] = ['debit' => 0, 'credit' => 0];
                        }
                        $addr_id_drcr[$addr_id]['debit'] = bcadd($addr_id_drcr[$addr_id]['debit'], $in['Value'], 8);

                        try {
                            $conn->execute('INSERT INTO InputsAddresses (InputId, AddressId) VALUES (?, ?) ON DUPLICATE KEY UPDATE InputId = InputId', [$in_entity->Id, $in['AddressId']]);
                            $conn->execute('UPDATE Addresses SET TotalSent = TotalSent + ? WHERE Id = ?', [$in['Value'], $in['AddressId']]);
                            $conn->execute('INSERT INTO TransactionsAddresses (TransactionId, AddressId) VALUES (?, ?) ON DUPLICATE KEY UPDATE TransactionId = TransactionId', [$numeric_tx_id, $in['AddressId']]);
                        } catch (\Exception $e) {
                            $data_error = true;
                            break;
                        }
                    }
                }
            }

            foreach ($outputs as $out) {
                $out['TransactionId'] = $numeric_tx_id;
                $out_entity = $this->Outputs->newEntity($out);

                //$stmt->execute('INSERT INTO Outputs')
                $conn->execute('INSERT INTO Outputs (TransactionId, Vout, Value, Type, ScriptPubKeyAsm, ScriptPubKeyHex, RequiredSignatures, Hash160, Addresses, Created, Modified) '.
                               'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                               [$out['TransactionId'],
                                $out['Vout'],
                                $out['Value'],
                                $out['Type'],
                                $out['ScriptPubKeyAsm'],
                                $out['ScriptPubKeyHex'],
                                $out['RequiredSignatures'],
                                $out['Hash160'],
                                $out['Addresses']
                               ]);

                // get the last insert id
                $stmt = $conn->execute('SELECT LAST_INSERT_ID() AS outputId');
                $linsert = $stmt->fetch(\PDO::FETCH_OBJ);
                $out_entity->Id = $linsert->outputId;

                if ($out_entity->Id === 0) {
                    $data_error = true;
                    break;
                }

                $json_addr = json_decode($out['Addresses']);
                $out_addr_id = 0;
                if (isset($addr_id_map[$json_addr[0]])) {
                    $out_addr_id = $addr_id_map[$json_addr[0]];
                } else {
                    $out_addr = $this->Addresses->find()->select(['Id'])->where(['Address' => $json_addr[0]])->first();
                    if ($out_addr) {
                        $addr_id_map[$json_addr[0]] = $out_addr->Id;
                        $out_addr_id = $out_addr->Id;
                    }
                }

                if ($out_addr_id > 0) {
                    $addr_id = $out_addr_id;
                    if (!isset($addr_id_drcr[$addr_id])) {
                        $addr_id_drcr[$addr_id] = ['debit' => 0, 'credit' => 0];
                    }
                    $addr_id_drcr[$addr_id]['credit'] = bcadd($addr_id_drcr[$addr_id]['credit'], $out['Value'], 8);

                    try {
                        $conn->execute('INSERT INTO OutputsAddresses (OutputId, AddressId) VALUES (?, ?) ON DUPLICATE KEY UPDATE OutputId = OutputId', [$out_entity->Id, $out_addr_id]);
                        $conn->execute('UPDATE Addresses SET TotalReceived = TotalReceived + ? WHERE Id = ?', [$out['Value'], $out_addr_id]);
                        $conn->execute('INSERT INTO TransactionsAddresses (TransactionId, AddressId) VALUES (?, ?) ON DUPLICATE KEY UPDATE TransactionId = TransactionId', [$numeric_tx_id, $out_addr_id]);
                    } catch (\Exception $e) {
                        print_r($e);
                        $data_error = true;
                        break;
                    }
                }

                // create the claim if the asm pub key starts with OP_CLAIM_NAME
                if (strpos($out['ScriptPubKeyAsm'], 'OP_CLAIM_NAME') !== false) {
                    $all_claim_data = $this->_getclaimfortxout($out['ScriptPubKeyAsm'], $tx_hash, $out['Vout'], $block_ts);
                    $claim = $all_claim_data['claim_data'];
                    $claim_stream_data = $all_claim_data['claim_stream_data'];

                    if (!$claim) {
                        continue;
                    }

                    if ($claim['ClaimType'] == 2 && !$claim_stream_data) {
                        echo "***claim stream data missing for streamType claim\n";
                        continue;
                    }

                    $claim_entity = $this->Claims->newEntity($claim);
                    $res = $this->Claims->save($claim_entity);

                    if (!$res) {
                        echo "***claim could not be saved.\n";
                        continue;
                    }

                    if (!$data_error && $claim_stream_data) {
                        $claim_stream_data['Id'] = $claim_entity->Id;
                        $claim_stream_entity = $this->ClaimStreams->newEntity($claim_stream_data);
                        $res = $this->ClaimStreams->save($claim_stream_entity);
                        if (!$res) {
                            echo "***claim stream could not be saved.\n";
                        }
                    }
                }
            }
        }

        // update tx amounts
        if (!$data_error) {
            foreach ($addr_id_drcr as $addr_id => $drcr) {
                try {
                    $conn->execute('UPDATE TransactionsAddresses SET DebitAmount = ?, CreditAmount = ?, TransactionTime = UTC_TIMESTAMP() WHERE TransactionId = ? AND AddressId = ?',
                                   [$drcr['debit'], $drcr['credit'], $numeric_tx_id, $addr_id]);
                } catch (Exception $e) {
                    print_r($e);
                    $data_error = true;
                    break;
                }
            }
        }
    }

    public function parsetxs() {
        set_time_limit(0);

        self::lock('parsetxs');

        // Get the minimum block with no processed transactions
        echo "Parsing transactions...\n";

        $conn = ConnectionManager::get('default');
        //$conn->execute('SET foreign_key_checks = 0');
        //$conn->execute('SET unique_checks = 0');

        $redis = self::_init_redis();
        try {
            $unproc_blocks = $this->Blocks->find()->select(['Id', 'Height', 'Hash', 'TransactionHashes', 'BlockTime'])->where(['TransactionsProcessed' => 0])->order(['Height' => 'asc'])->toArray();
            foreach ($unproc_blocks as $min_block) {
                $tx_hashes = json_decode($min_block->TransactionHashes);
                if ($tx_hashes && is_array($tx_hashes)) {
                    $block_time = $min_block->BlockTime;
                    $block_ts = \DateTime::createFromFormat('U', $block_time);

                    $count = count($tx_hashes);
                    echo "Processing " . $count . " transaction(s) for block $min_block->Height ($min_block->Hash)...\n";

                    $data_error = false;
                    $conn->begin();

                    $idx = 0;
                    foreach ($tx_hashes as $tx_hash) {
                        $idx++;
                        $idx_str = str_pad($idx, strlen($count), '0', STR_PAD_LEFT);
                        echo "[$idx_str/$count] Processing tx hash: $tx_hash... ";

                        $total_diff = 0;
                        $start_ms = round(microtime(true) * 1000);
                        $exist_tx = $this->Transactions->find()->select(['Id'])->where(['Hash' => $tx_hash])->first();
                        $end_ms = round(microtime(true) * 1000);
                        $diff_ms = $end_ms - $start_ms;
                        $total_diff += $diff_ms;
                        echo "findtx took {$diff_ms}ms. ";

                        if ($exist_tx) {
                            echo "Exists. Skipping.\n";
                            continue;
                        }

                        $start_ms = round(microtime(true) * 1000);
                        $this->processtx($tx_hash, $block_ts, ['hash' => $min_block->Hash, 'time' => $min_block->BlockTime], $data_error);
                        $diff_ms = round(microtime(true) * 1000) - $start_ms;
                        $total_diff += $diff_ms;
                        echo "tx took {$diff_ms}ms. Total {$total_diff}ms. ";

                        if (!$data_error && $redis && $redis->sismember(self::mempooltxkey, $tx_hash)) {
                            $redis->srem(self::mempooltxkey, $tx_hash);
                            echo "Removed $tx_hash from redis mempooltx.\n";
                        }

                        echo "Done.\n";
                    }

                    if (!$data_error) {
                        $conn->execute('UPDATE Blocks SET TransactionsProcessed = 1 WHERE Id = ?', [$min_block->Id]);
                    }

                    if ($data_error) {
                        echo "Rolling back!\n";
                        $conn->rollback();
                        throw new \Exception('Data save failed!');
                    } else {
                        echo "Data committed.\n";
                        $conn->commit();
                    }
                }
            }

            // Try to update txs with null BlockHash
            $mempooltx = $this->Transactions->find()->select(['Id', 'Hash'])->where(['BlockHash IS' => null])->order(['Created' => 'asc'])->toArray();
            $idx = 0;
            $count = count($mempooltx);
            foreach ($mempooltx as $tx) {
                $idx++;
                $tx_hash = $tx->Hash;
                $idx_str = ($count > 10 && $idx < 10) ? '0' . $idx : $idx;
                echo "[$idx_str/$count] Processing tx hash: $tx_hash... ";

                $stmt = $conn->execute("SELECT Hash, BlockTime FROM Blocks WHERE TransactionHashes LIKE CONCAT('%', ?, '%') AND Height > ((SELECT MAX(Height) FROM Blocks) - 10000) ORDER BY Height ASC LIMIT 1", [$tx_hash]);
                $block = $stmt->fetch(\PDO::FETCH_OBJ);
                if ($block) {
                    $upd_tx = ['Id' => $tx->Id, 'BlockHash' => $block->Hash, 'TransactionTime' => $block->BlockTime];
                    $upd_entity = $this->Transactions->newEntity($upd_tx);
                    $this->Transactions->save($upd_entity);
                    echo "Done.\n";

                    if ($redis && $redis->sismember(self::mempooltxkey, $tx_hash)) {
                        $redis->srem(self::mempooltxkey, $tx_hash);
                        echo "Removed $tx_hash from redis mempooltx.\n";
                    }
                } else {
                    echo "Block not found.\n";
                }
            }
        } catch (\Exception $e) {
            print_r($e);
        }

        //$conn->execute('SET foreign_key_checks = 1');
        //$conn->execute('SET unique_checks = 1');

        self::unlock('parsetxs');
    }

    public function parsenewblocks() {
        set_time_limit(0);
        self::lock('parsenewblocks');

        echo "Parsing new blocks...\n";
        $redis = self::_init_redis();
        try {
            // Get the best block hash
            $req = ['method' => 'getbestblockhash', 'params' => []];
            $response = self::curl_json_post(self::$rpcurl, json_encode($req));
            $json = json_decode($response);
print_r($response); print_r($json);
            $best_hash = $json->result;

            $req = ['method' => 'getblock', 'params' => [$best_hash]];
            $response = self::curl_json_post(self::$rpcurl, json_encode($req));
            $json = json_decode($response);
            $best_block = $json->result;

            $max_block = $this->Blocks->find()->select(['Hash', 'Height'])->order(['Height' => 'desc'])->first();
            if (!$max_block) {
                self::unlock('parsenewblocks');
                return;
            }

            $min_height = min($max_block->Height, $best_block->height);
            $max_height = max($max_block->Height, $best_block->height);
            $height_diff = $best_block->height - $max_block->Height;

            if ($height_diff <= 0) {
                self::unlock('parsenewblocks');
                return;
            }

            $conn = ConnectionManager::get('default');
            for ($curr_height = $min_height; $curr_height <= $max_height; $curr_height++) {
                // get the block hash
                $req = ['method' => 'getblockhash', 'params' => [$curr_height]];
                $response = self::curl_json_post(self::$rpcurl, json_encode($req));
                $json = json_decode($response);
                $curr_block_hash = $json->result;

                $next_block_hash = null;
                if ($curr_height < $max_height) {
                    $req = ['method' => 'getblockhash', 'params' => [$curr_height + 1]];
                    $response = self::curl_json_post(self::$rpcurl, json_encode($req));
                    $json = json_decode($response);
                    $next_block_hash = $json->result;
                }

                $req = ['method' => 'getblock', 'params' => [$curr_block_hash]];
                $response = self::curl_json_post(self::$rpcurl, json_encode($req));
                $json = json_decode($response);
                $curr_block = $json->result;

                if ($curr_block->confirmations < 0) {
                    continue;
                }

                $next_block = null;
                if ($next_block_hash != null) {
                    $req = ['method' => 'getblock', 'params' => [$next_block_hash]];
                    $response = self::curl_json_post(self::$rpcurl, json_encode($req));
                    $json = json_decode($response);
                    $next_block = $json->result;
                }

                if ($curr_block != null) {
                    $curr_block_ins = $this->blockdb_data_from_json($curr_block);
                    if ($next_block != null && $curr_block_ins['NextBlockHash'] == null) {
                        $curr_block_ins['NextBlockHash'] = $next_block->hash;
                    }

                    $block_data = $curr_block;
                    $block_id = -1;
                    // Make sure the block does not exist before inserting
                    $old_block = $this->Blocks->find()->select(['Id'])->where(['Hash' => $block_data->hash])->first();
                    if (!$old_block) {
                        echo "Inserting block $block_data->height ($block_data->hash)... ";
                        $curr_block_entity = $this->Blocks->newEntity($curr_block_ins);

                        $conn->execute('INSERT INTO Blocks (Bits, Chainwork, Confirmations, Difficulty, Hash, Height, MedianTime, MerkleRoot, NameClaimRoot, Nonce, PreviousBlockHash, NextBlockHash, BlockSize, Target, BlockTime, TransactionHashes, Version, VersionHex, Created, Modified) ' .
                                       'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                                       [
                                        $curr_block_entity->Bits,
                                        $curr_block_entity->Chainwork,
                                        $curr_block_entity->Confirmations,
                                        $curr_block_ins['Difficulty'], // cakephp 3 why?
                                        $curr_block_entity->Hash,
                                        $curr_block_entity->Height,
                                        $curr_block_entity->MedianTime,
                                        $curr_block_entity->MerkleRoot,
                                        $curr_block_entity->NameClaimRoot,
                                        $curr_block_entity->Nonce,
                                        $curr_block_entity->PreviousBlockHash,
                                        $curr_block_entity->NextBlockHash,
                                        $curr_block_entity->BlockSize,
                                        $curr_block_entity->Target,
                                        $curr_block_entity->BlockTime,
                                        $curr_block_entity->TransactionHashes,
                                        $curr_block_entity->Version,
                                        $curr_block_entity->VersionHex,
                                       ]);

                        $stmt = $conn->execute('SELECT LAST_INSERT_ID() AS lBlockId');
                        $linsert = $stmt->fetch(\PDO::FETCH_OBJ);
                        $curr_block_entity->Id = $linsert->lBlockId;
                        $block_id = $curr_block_entity->Id;

                        echo "Done.\n";
                    } else {
                        echo "Updating block $block_data->height ($block_data->hash) with next block hash: " . $curr_block_ins['NextBlockHash'] . " and confirmations: " . $curr_block_ins['Confirmations'] . "... ";
                        $upd_block = ['Id' => $old_block->Id, 'NextBlockHash' => $curr_block_ins['NextBlockHash'], 'Confirmations' => $curr_block_ins['Confirmations']];
                        $upd_entity = $this->Blocks->newEntity($upd_block);
                        $block_id = $old_block->Id;
                        echo "Done.\n";
                    }

                    $txs = $block_data->tx;
                    $data_error = false;
                    foreach ($txs as $tx_hash) {
                        // Check if the transactions exist and then update the BlockHash and TxTime
                        $tx = $this->Transactions->find()->select(['Id'])->where(['Hash' => $tx_hash])->first();
                        if ($tx) {
                            $upd_tx_data = [
                                'Id' => $tx->Id,
                                'BlockHash' => $block_data->hash,
                                'TransactionTime' => $block_data->time
                            ];
                            $upd_tx_entity = $this->Transactions->newEntity($upd_tx_data);
                            $this->Transactions->save($upd_tx_entity);
                            echo "Updated tx $tx_hash with block hash and time $block_data->time.\n";
                        } else {
                            // Doesn't exist, create a new transaction
                            echo "Inserting tx $tx_hash for block height $block_data->height... ";

                            $conn->begin();
                            $block_ts = \DateTime::createFromFormat('U', $block_data->time);
                            $this->processtx($tx_hash, $block_ts, ['hash' => $block_data->hash, 'time' => $block_data->time], $data_error);

                            if ($data_error) {
                                $conn->rollback();
                                echo "Insert failed.\n";
                            } else {
                                $conn->commit();
                                echo "Done.\n";
                            }
                        }

                        // Remove from redis if present
                        if (!$data_error && $redis && $redis->sismember(self::mempooltxkey, $tx_hash)) {
                            $redis->srem(self::mempooltxkey, $tx_hash);
                            echo "Removed $tx_hash from redis mempooltx.\n";
                        }
                    }

                    if (!$data_error && $block_id > -1) {
                        // set TransactionsProcessed to true
                        $conn->execute('UPDATE Blocks SET TransactionsProcessed = 1 WHERE Id = ?', [$block_id]);
                    }
                }
            }
        } catch (\Exception $e) {
            print_r($e);
        }

        self::unlock('parsenewblocks');
    }

    public function forevermempool() {
        self::lock('forevermempool');

        $conn = ConnectionManager::get('default');
        $redis = self::_init_redis();

        while (true) {
            try {
                $data = ['method' => 'getrawmempool', 'params' => []];
                $res = self::curl_json_post(self::$rpcurl, json_encode($data));
                $json = json_decode($res);
                $txs = $json->result;
                $now = new \DateTime('now', new \DateTimeZone('UTC'));
                $data_error = false;

                if (count($txs) === 0) {
                    // If no transactions found, that means there's nothing in the mempool. Clear redis
                    if ($redis) {
                        $redis->del(self::mempooltxkey);
                        echo "Empty rawmempool. Cleared mempool txs from redis.\n";
                    }
                }

                foreach ($txs as $tx_hash) {
                    // Check redis mempool txs
                    if ($redis && $redis->exists(self::mempooltxkey)) {
                        if ($redis->sismember(self::mempooltxkey, $tx_hash)) {
                            echo "Found processed tx hash: $tx_hash. Skipping.\n";
                            continue;
                        }
                    }

                    echo "Processing tx hash: $tx_hash... ";
                    $exist_tx = $this->Transactions->find()->select(['Id'])->where(['Hash' => $tx_hash])->first();
                    if ($exist_tx) {
                        echo "Exists. Skipping.\n";
                        continue;
                    }

                    // Process the tx
                    $conn->begin();
                    $block_ts = new \DateTime('now', new \DateTimeZone('UTC'));
                    $this->processtx($tx_hash, $block_ts, null, $data_error);

                    if ($data_error) {
                        echo "Rolling back!\n";
                        $conn->rollback();
                        throw new \Exception('Data save failed!');
                    } else {
                        echo "Data committed.\n";
                        $conn->commit();

                        // Save to redis to prevent the DB from behing hit again
                        if ($redis) {
                            $redis->sadd(self::mempooltxkey, $tx_hash);
                        }
                    }
                }
            } catch (\Exception $e) {
                echo "Mempool database error. Attempting to reconnect.\n";

                // Final fix for MySQL server has gone away (hopefully)
                try {
                    $conn->disconnect();
                } catch (\Exception $e) {
                    // ignore possible disconnect errors
                }
                
                $conn->connect();
            }

            echo "*******************\n";
            sleep(1);
        }

        self::unlock('forevermempool');
    }

    private function txdb_data_from_decoded($decoded_tx) {
        $tx = [
            'Version' => $decoded_tx['version'],
            'LockTime' => $decoded_tx['locktime']
        ];

        $addresses = [];
        $inputs = [];
        $outputs = [];

        $vin = $decoded_tx['vin'];
        $vout = $decoded_tx['vout'];
        if (is_array($vin)) {
            foreach ($vin as $in) {
                if (isset($in['coinbase'])) {
                    $inputs[] = [
                        'IsCoinbase' => 1,
                        'Coinbase' => $in['coinbase']
                    ];
                } else {
                    $inputs[] = [
                        'PrevoutHash' => $in['txid'],
                        'PrevoutN' => $in['vout'],
                        'ScriptSigAsm' => $in['scriptSig']['asm'],
                        'ScriptSigHex' => $in['scriptSig']['hex'],
                        'Sequence' => $in['sequence']
                    ];
                }
            }

            foreach ($vout as $out) {
                $outputs[] = [
                    'Vout' => $out['vout'],
                    'Value' => bcdiv($out['value'], 100000000, 8),
                    'Type' => isset($out['scriptPubKey']['type']) ? $out['scriptPubKey']['type'] : '',
                    'ScriptPubKeyAsm' => isset($out['scriptPubKey']['asm']) ? $out['scriptPubKey']['asm'] : '',
                    'ScriptPubKeyHex' => isset($out['scriptPubKey']['hex']) ? $out['scriptPubKey']['hex'] : '',
                    'RequiredSignatures' => isset($out['scriptPubKey']['reqSigs']) ? $out['scriptPubKey']['reqSigs'] : '',
                    'Hash160' => isset($out['scriptPubKey']['hash160']) ? $out['scriptPubKey']['hash160'] : '',
                    'Addresses' => isset($out['scriptPubKey']['addresses']) ? json_encode($out['scriptPubKey']['addresses']) : null
                ];

                if (isset($out['scriptPubKey']['addresses'])) {
                    foreach ($out['scriptPubKey']['addresses'] as $address) {
                        $addresses[$address] = $address;
                    }
                }
            }
        }

        return ['tx' => $tx, 'addresses' => $addresses, 'inputs' => $inputs, 'outputs' => $outputs];
    }

    private function blockdb_data_from_json($json_block) {
        return [
            'Bits' => $json_block->bits,
            'Chainwork' => $json_block->chainwork,
            'Confirmations' => $json_block->confirmations,
            'Difficulty' => $json_block->difficulty,
            'Hash' => $json_block->hash,
            'Height' => $json_block->height,
            'MedianTime' => $json_block->mediantime,
            'MerkleRoot' => $json_block->merkleroot,
            'NameClaimRoot' => $json_block->nameclaimroot,
            'Nonce' => $json_block->nonce,
            'PreviousBlockHash' => isset($json_block->previousblockhash) ? $json_block->previousblockhash : null,
            'NextBlockHash' => isset($json_block->nextblockhash) ? $json_block->nextblockhash : null,
            'BlockSize' => $json_block->size,
            'Target' => $json_block->target,
            'BlockTime' => $json_block->time,
            'TransactionHashes' => json_encode($json_block->tx),
            'Version' => $json_block->version,
            'VersionHex' => $json_block->versionHex
        ];
    }

    private static function curl_json_post($url, $data, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        //Log::debug('Request execution completed.');
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

    private static function curl_json_get($url, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        //Log::debug('Request execution completed.');
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

    private static $base58chars = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";

    public static $op_codes = [
        ['OP_0', 0],
        ['OP_PUSHDATA', 76],
        'OP_PUSHDATA2',
        'OP_PUSHDATA4',
        'OP_1NEGATE',
        'OP_RESERVED',
        'OP_1',
        'OP_2',
        'OP_3',
        'OP_4',
        'OP_5',
        'OP_6',
        'OP_7',
        'OP_8', 'OP_9', 'OP_10', 'OP_11', 'OP_12', 'OP_13', 'OP_14', 'OP_15', 'OP_16',
        'OP_NOP', 'OP_VER', 'OP_IF', 'OP_NOTIF', 'OP_VERIF', 'OP_VERNOTIF', 'OP_ELSE', 'OP_ENDIF', 'OP_VERIFY',
        'OP_RETURN', 'OP_TOALTSTACK', 'OP_FROMALTSTACK', 'OP_2DROP', 'OP_2DUP', 'OP_3DUP', 'OP_2OVER', 'OP_2ROT', 'OP_2SWAP',
        'OP_IFDUP', 'OP_DEPTH', 'OP_DROP', 'OP_DUP', 'OP_NIP', 'OP_OVER', 'OP_PICK', 'OP_ROLL', 'OP_ROT',
        'OP_SWAP', 'OP_TUCK', 'OP_CAT', 'OP_SUBSTR', 'OP_LEFT', 'OP_RIGHT', 'OP_SIZE', 'OP_INVERT', 'OP_AND',
        'OP_OR', 'OP_XOR', 'OP_EQUAL', 'OP_EQUALVERIFY', 'OP_RESERVED1', 'OP_RESERVED2', 'OP_1ADD', 'OP_1SUB', 'OP_2MUL',
        'OP_2DIV', 'OP_NEGATE', 'OP_ABS', 'OP_NOT', 'OP_0NOTEQUAL', 'OP_ADD', 'OP_SUB', 'OP_MUL', 'OP_DIV',
        'OP_MOD', 'OP_LSHIFT', 'OP_RSHIFT', 'OP_BOOLAND', 'OP_BOOLOR',
        'OP_NUMEQUAL', 'OP_NUMEQUALVERIFY', 'OP_NUMNOTEQUAL', 'OP_LESSTHAN',
        'OP_GREATERTHAN', 'OP_LESSTHANOREQUAL', 'OP_GREATERTHANOREQUAL', 'OP_MIN', 'OP_MAX',
        'OP_WITHIN', 'OP_RIPEMD160', 'OP_SHA1', 'OP_SHA256', 'OP_HASH160',
        'OP_HASH256', 'OP_CODESEPARATOR', 'OP_CHECKSIG', 'OP_CHECKSIGVERIFY', 'OP_CHECKMULTISIG',
        'OP_CHECKMULTISIGVERIFY', 'OP_NOP1', 'OP_NOP2', 'OP_NOP3', 'OP_NOP4', 'OP_NOP5', 'OP_CLAIM_NAME',
        'OP_SUPPORT_CLAIM', 'OP_UPDATE_CLAIM',
        ['OP_SINGLEBYTE_END', 0xF0],
        ['OP_DOUBLEBYTE_BEGIN', 0xF000],
        'OP_PUBKEY', 'OP_PUBKEYHASH',
        ['OP_INVALIDOPCODE', 0xFFFF]
    ];

    public static $op_code = array(
        '00' => 'OP_0', // or OP_FALSE
        '51' => 'OP_1', // or OP_TRUE
        '61' => 'OP_NOP',
        '6a' => 'OP_RETURN',
        '6d' => 'OP_2DROP',
        '75' => 'OP_DROP',
        '76' => 'OP_DUP',
        '87' => 'OP_EQUAL',
        '88' => 'OP_EQUALVERIFY',
        'a6' => 'OP_RIPEMD160',
        'a7' => 'OP_SHA1',
        'a8' => 'OP_SHA256',
        'a9' => 'OP_HASH160',
        'aa' => 'OP_HASH256',
        'ac' => 'OP_CHECKSIG',
        'ae' => 'OP_CHECKMULTISIG',
        'b5' => 'OP_CLAIM_NAME',
        'b6' => 'OP_SUPPORT_CLAIM',
        'b7' => 'OP_UPDATE_CLAIM'
    );

    /*protected static function hash160_to_bc_address($h160, $addrType = 0) {
        $vh160 = $c . $h160;
        $h = self::_dhash($vh160);

        $addr = $vh160 . substr($h, 0, 4);
        return $addr;
        //return self::base58_encode($addr);
    }*/

    protected static function _dhash($str, $raw = false) {
        return hash('sha256', hash('sha256', $str, true), $raw);
    }

    public static function _get_vint(&$string)
    {
        // Load the next byte, convert to decimal.
        $decimal = hexdec(self::_return_bytes($string, 1));
        // Less than 253: Not encoding extra bytes.
        // More than 253, work out the $number of bytes using the 2^(offset)
        $num_bytes = ($decimal < 253) ? 0 : 2 ^ ($decimal - 253);
        // Num_bytes is 0: Just return the decimal
        // Otherwise, return $num_bytes bytes (order flipped) and converted to decimal
        return ($num_bytes == 0) ? $decimal : hexdec(self::_return_bytes($string, $num_bytes, true));
    }

    public static function hash160($string)
    {
        $bs = @pack("H*", $string);
        return hash("ripemd160", hash("sha256", $bs, true));
    }

    public static function hash256($string)
    {
        $bs = @pack("H*", $string);
        return hash("sha256", hash("sha256", $bs, true));
    }

    public static function hash160_to_address($hash160, $address_version = null)
    {
        $c = '';
        if ($address_version == self::pubKeyAddress[0]) {
            $c = dechex(self::pubKeyAddress[1]);
        } else if ($address_version == self::scriptAddress[0]) {
            $c = dechex(self::scriptAddress[1]);
        }

        $hash160 = $c . $hash160;
        $addr = $hash160;
        return self::base58_encode_checksum($addr);
    }

    public static function base58_encode_checksum($hex)
    {
        $checksum = self::hash256($hex);
        $checksum = substr($checksum, 0, 8);
        $hash = $hex . $checksum;
        return self::base58_encode($hash);
    }

    public static function _decode_script($script)
    {
        $pos = 0;
        $data = array();
        while ($pos < strlen($script)) {
            $code = hexdec(substr($script, $pos, 2)); // hex opcode.
            $pos += 2;
            if ($code < 1) {
                // OP_FALSE
                $push = '0';
            } elseif ($code <= 75) {
                // $code bytes will be pushed to the stack.
                $push = substr($script, $pos, ($code * 2));
                $pos += $code * 2;
            } elseif ($code <= 78) {
                // In this range, 2^($code-76) is the number of bytes to take for the *next* number onto the stack.
                $szsz = pow(2, $code - 75); // decimal number of bytes.
                $sz = hexdec(substr($script, $pos, ($szsz * 2))); // decimal number of bytes to load and push.
                $pos += $szsz;
                $push = substr($script, $pos, ($pos + $sz * 2)); // Load the data starting from the new position.
                $pos += $sz * 2;
            } elseif ($code <= 108/*96*/) {
                // OP_x, where x = $code-80
                $push = ($code - 80);
            } else {
                $push = $code;
            }
            $data[] = $push;
        }

        return implode(" ", $data);
    }

    /*public static function script_getopname($bytes_hex) {
        $index = hexdec($bytes_hex) - 75;
        $op = self::$op_codes[$index];
        if (is_array($op)) {
            if ($bytes_hex == dechex($op[1])) {
                return $op[0];
            }
        }
        if ()

        return str_replace('OP_', '', $op);
    }*/

    public static function get_opcode($opname) {
        $len = count(self::$op_codes);
        for ($i = 0; $i < $len; $i++) {
            $op = self::$op_codes[$i];
            $op = is_array($op) ? $op[0] : $op;
            if ($op === $opname) {
                return $i;
            }
        }

        return null;
    }

    public static function match_decoded($decoded, $to_match) {
        if (strlen($decoded) != strlen($to_match)) {
            return false;
        }

        for ($i = 0; $i < count($decoded); $i++) {
            $pushdata4 = self::get_opcode('OP_PUSHDATA4');
            if ($to_match[$i] == $pushdata4 && ($pushdata4 >= $decoded[$i][0]) > 0) {
                continue;
            }
            if ($to_match[$i] != $decoded[$i][0]) {
                return false;
            }
        }

        return true;
    }

	public static function base58_encode($hex)
    {
        if (strlen($hex) == 0) {
            return '';
        }
        // Convert the hex string to a base10 integer
        $num = gmp_strval(gmp_init($hex, 16), 58);
        // Check that number isn't just 0 - which would be all padding.
        if ($num != '0') {
            $num = strtr($num, '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuv', self::$base58chars);
        } else {
            $num = '';
        }
        // Pad the leading 1's
        $pad = '';
        $n = 0;
        while (substr($hex, $n, 2) == '00') {
            $pad .= '1';
            $n += 2;
        }
        return $pad . $num;
    }

    public static function decode_tx($raw_transaction)
    {
        $math = EccFactory::getAdapter();
        /*$magic_byte = BitcoinLib::magicByte($magic_byte);
        $magic_p2sh_byte = BitcoinLib::magicP2SHByte($magic_p2sh_byte);*/
        $raw_transaction = trim($raw_transaction);
        if (((bool)preg_match('/^[0-9a-fA-F]{2,}$/i', $raw_transaction) !== true)
            || (strlen($raw_transaction)) % 2 !== 0
        ) {
            throw new \InvalidArgumentException("Raw transaction is invalid hex");
        }
        $txHash = hash('sha256', hash('sha256', pack("H*", trim($raw_transaction)), true));
        $txid = self::_flip_byte_order($txHash);
        $info = array();
        $info['txid'] = $txid;
        $info['version'] = $math->hexDec(self::_return_bytes($raw_transaction, 4, true));
        /*if (!in_array($info['version'], array('0', '1'))) {
            throw new \InvalidArgumentException("Invalid transaction version");
        }*/
        $input_count = self::_get_vint($raw_transaction);
        if (!($input_count >= 0 && $input_count <= 4294967296)) {
            throw new \InvalidArgumentException("Invalid input count");
        }
        $info['vin'] = self::_decode_inputs($raw_transaction, $input_count);
        if ($info['vin'] == false) {
            throw new \InvalidArgumentException("No inputs in transaction");
        }
        $output_count = self::_get_vint($raw_transaction);
        if (!($output_count >= 0 && $output_count <= 4294967296)) {
            throw new \InvalidArgumentException("Invalid output count");
        }
        $info['vout'] = self::_decode_outputs($raw_transaction, $output_count);
        $info['locktime'] = $math->hexDec(self::_return_bytes($raw_transaction, 4));
        return $info;
    }

    public static function _decode_inputs(&$raw_transaction, $input_count)
    {
        $inputs = array();
        // Loop until $input count is reached, sequentially removing the
        // leading data from $raw_transaction reference.
        for ($i = 0; $i < $input_count; $i++) {
            // Load the TxID (32bytes) and vout (4bytes)
            $txid = self::_return_bytes($raw_transaction, 32, true);
            $vout = self::_return_bytes($raw_transaction, 4, true);
            // Script is prefixed with a varint that must be decoded.
            $script_length = self::_get_vint($raw_transaction); // decimal number of bytes.
            $script = self::_return_bytes($raw_transaction, $script_length);

            // Build input body depending on whether the TxIn is coinbase.
            if ($txid == '0000000000000000000000000000000000000000000000000000000000000000') {
                $input_body = array('coinbase' => $script);
            } else {
                $input_body = array('txid' => $txid,
                    'vout' => hexdec($vout),
                    'scriptSig' => array('asm' => self::_decode_script($script), 'hex' => $script));
            }
            // Append a sequence number, and finally add the input to the array.
            $input_body['sequence'] = hexdec(self::_return_bytes($raw_transaction, 4));
            $inputs[$i] = $input_body;
        }
        return $inputs;
    }

    public static function _decode_outputs(&$tx, $output_count)
    {
        $math = EccFactory::getAdapter();
        /*$magic_byte = BitcoinLib::magicByte($magic_byte);
        $magic_p2sh_byte = BitcoinLib::magicP2SHByte($magic_p2sh_byte);*/
        $outputs = array();
        for ($i = 0; $i < $output_count; $i++) {
            // Pop 8 bytes (flipped) from the $tx string, convert to decimal,
            // and then convert to Satoshis.
            $satoshis = $math->hexDec(self::_return_bytes($tx, 8, true));
            // Decode the varint for the length of the scriptPubKey
            $script_length = self::_get_vint($tx); // decimal number of bytes
            $script = self::_return_bytes($tx, $script_length);


            try {
                $asm = self::_decode_scriptPubKey($script);
            } catch (\Exception $e) {
                $asm = null;
            }
            // Begin building scriptPubKey
            $scriptPubKey = array(
                'asm' => $asm,
                'hex' => $script
            );

            // Try to decode the scriptPubKey['asm'] to learn the transaction type.
            $txn_info = self::_get_transaction_type($scriptPubKey['asm']);
            if ($txn_info !== false) {
                $scriptPubKey = array_merge($scriptPubKey, $txn_info);
            }
            $outputs[$i] = array(
                'value' => $satoshis,
                'vout' => $i,
                'scriptPubKey' => $scriptPubKey);
        }
        return $outputs;
    }

    public function fixoutputs() {
        $sql = 'SELECT * FROM Outputs WHERE Id NOT IN (SELECT OutputId FROM OutputsAddresses)';

        $conn = ConnectionManager::get('default');
        $stmt = $conn->execute($sql);
        $outs = $stmt->fetchAll(\PDO::FETCH_OBJ);

        foreach ($outs as $out) {
            $txn_info = self::_get_transaction_type($out->ScriptPubKeyAsm);

            $out_data = [
                'Id' => $out->Id,
                'Type' => $txn_info['type'],
                'RequiredSignatures' => $txn_info['reqSigs'],
                'Hash160' => $txn_info['hash160'],
                'Addresses' => json_encode($txn_info['addresses'])
            ];
            $out_entity = $this->Outputs->newEntity($out_data);
            $this->Outputs->save($out_entity);

            // Fix the addresses
            foreach ($txn_info['addresses'] as $address) {
                $prev_addr = $this->Addresses->find()->where(['Address' => $address])->first();
                $addr_id = -1;
                if ($prev_addr) {
                    $addr_id = $prev_addr->Id;
                } else {
                    $dt = new \DateTime($out->Created, new \DateTimeZone('UTC'));
                    $new_addr = [
                        'Address' => $address,
                        'FirstSeen' => $dt->format('Y-m-d H:i:s')
                    ];
                    $new_addr_entity = $this->Addresses->newEntity($new_addr);
                    if ($this->Addresses->save($new_addr_entity)) {
                        $addr_id = $new_addr_entity->Id;
                    }
                }

                if ($addr_id > -1) {
                    $conn->execute('INSERT INTO OutputsAddresses (OutputId, AddressId) VALUES (?, ?) ON DUPLICATE KEY UPDATE OutputId = OutputId', [$out->Id, $addr_id]);
                }
            }

            echo "Fixed output $out->Id with new data: " . print_r($out_data, true);
        }
    }

    public function fixinputs() {
        $sql = 'SELECT * FROM Inputs WHERE IsCoinbase <> 1 AND Id NOT IN (SELECT InputId FROM InputsAddresses)';

        $conn = ConnectionManager::get('default');
        $stmt = $conn->execute($sql);
        $ins = $stmt->fetchAll(\PDO::FETCH_OBJ);

        foreach ($ins as $in) {
            $prev_tx_hash = $in->PrevoutHash;
            $prev_n = $in->PrevoutN;

            // Get the previous transaction
            $prev_tx = $this->Transactions->find()->select(['Id'])->where(['Hash' => $prev_tx_hash])->first();
            if (!$prev_tx) {
                echo "Previous tx for hash $prev_tx_hash not found.\n";
                continue;
            }

            $prev_tx_id = $prev_tx->Id;
            $src_output = $this->Outputs->find()->contain(['OutputAddresses'])->where(['TransactionId' => $prev_tx_id, 'Vout' => $prev_n])->first();
            $in_data = ['Id' => $in->Id];
            if ($src_output) {
                $in_data['Value'] = $src_output->Value;
                $in_data['AddressId'] = $src_output->OutputAddresses[0]->Id;

                $in_entity = $this->Inputs->newEntity($in_data);
                if ($this->Inputs->save($in_entity)) {
                    $conn->execute('INSERT INTO InputsAddresses (InputId, AddressId) VALUES (?, ?) ON DUPLICATE KEY UPDATE InputId = InputId', [$in->Id, $in_data['AddressId']]);
                }
            }

            echo "Fixed input $in->Id with new data: " . print_r($in_data, true);
        }
    }

    public static function _get_transaction_type($data)
    {
        //$magic_byte = BitcoinLib::magicByte($magic_byte);
        //$magic_p2sh_byte = BitcoinLib::magicP2SHByte($magic_p2sh_byte);
        $has_claim = (strpos($data, 'CLAIM') !== false);
        $has_update_claim = (strpos($data, 'UPDATE_CLAIM') !== false);
        $has_op_0 = (strpos($data, 'OP_0') !== false);
        $data = explode(" ", trim($data));
        // Define information about eventual transactions cases, and
        // the position of the hash160 address in the stack.
        $define = array();
        $rule = array();

        // Other standard: pay to pubkey hash
        $define['p2pk'] = array('type' => 'pubkeyhash',
                                'reqSigs' => 1,
                                'data_index_for_hash' => 1);
        $rule['p2pk'] = [
            '0' => '/^[0-9a-f]+$/i',
            '1' => '/^OP_CHECKSIG/'
        ];

        // Pay to script hash
        $define['p2sh'] = array('type' => 'scripthash',
            'reqSigs' => 1,
            'data_index_for_hash' => 1);
        $rule['p2sh'] = array(
            '0' => '/^OP_HASH160/',
            '1' => '/^[0-9a-f]{40}$/i', // pos 1
            '2' => '/^OP_EQUAL/');

        // Non-standard (claim_name and support_claim)
        $define['p2c'] = array('type' => 'nonstandard',
                               'reqSigs' => 1,
                               'data_index_for_hash' => 7);
        $rule['p2c'] = [
            '0' => '/^OP_CLAIM_NAME|OP_SUPPORT_CLAIM/',
            '1' => '/^[0-9a-f]+$/i',
            '2' => '/^[0-9a-f]+$/i',
            '3' => '/^OP_2DROP/',
            '4' => '/^OP_DROP/',
            '5' => '/^OP_DUP/',
            '6' => '/^OP_HASH160/',
            '7' => '/^[0-9a-f]{40}$/i', // pos 7
            '8' => '/^OP_EQUALVERIFY/',
            '9' => '/^OP_CHECKSIG/',
        ];

        // Non-standard (claim_name and support_claim)
        $define['p2c2'] = array('type' => 'nonstandard',
                               'reqSigs' => 1,
                               'data_index_for_hash' => 7);
        $rule['p2c2'] = [
            '0' => '/^OP_CLAIM_NAME|OP_SUPPORT_CLAIM/',
            '1' => '/^OP_0/',
            '2' => '/^[0-9a-f]+$/i',
            '3' => '/^OP_2DROP/',
            '4' => '/^OP_DROP/',
            '5' => '/^OP_DUP/',
            '6' => '/^OP_HASH160/',
            '7' => '/^[0-9a-f]{40}$/i', // pos 8
            '8' => '/^OP_EQUALVERIFY/',
            '9' => '/^OP_CHECKSIG/'
        ];

        // update_claim
        $define['p2uc'] = array('type' => 'nonstandard',
                               'reqSigs' => 1,
                               'data_index_for_hash' => 8);
        $rule['p2uc'] = [
            '0' => '/^OP_UPDATE_CLAIM/',
            '1' => '/^[0-9a-f]+$/i',
            '2' => '/^[0-9a-f]+$/i',
            '3' => '/^[0-9a-f]+$/i',
            '4' => '/^OP_2DROP/',
            '5' => '/^OP_2DROP/',
            '6' => '/^OP_DUP/',
            '7' => '/^OP_HASH160/',
            '8' => '/^[0-9a-f]{40}$/i', // pos 8
            '9' => '/^OP_EQUALVERIFY/',
            '10' => '/^OP_CHECKSIG/'
        ];

        // Standard: pay to pubkey hash
        $define['p2ph'] = array('type' => 'pubkeyhash',
            'reqSigs' => 1,
            'data_index_for_hash' => 2);
        $rule['p2ph'] = array(
            '0' => '/^OP_DUP/',
            '1' => '/^OP_HASH160/',
            '2' => '/^[0-9a-f]{40}$/i', // 2
            '3' => '/^OP_EQUALVERIFY/',
            '4' => '/^OP_CHECKSIG/');

        if ($has_claim) {
            unset($rule['p2ph']);
            unset($rule['p2sh']);

            if ($has_op_0) {
                unset($rule['p2c']);
            } else {
                unset($rule['p2c2']);
            }

            if ($has_update_claim) {
                unset($rule['p2c']);
            } else {
                unset($rule['p2uc']);
            }
        } else {
            unset($rule['p2c']);
            unset($rule['p2c2']);
            unset($rule['p2uc']);
        }

        // Work out how many rules are applied in each case
        $valid = array();
        foreach ($rule as $tx_type => $def) {
            $valid[$tx_type] = count($def);
        }

        // Attempt to validate against each of these rules.
        $matches = [];
        for ($index = 0; $index < count($data); $index++) {
            $test = $data[$index];
            foreach ($rule as $tx_type => $def) {
                if (isset($def[$index])) {
                    preg_match($def[$index], $test, $matches[$tx_type]);
                    if (count($matches[$tx_type]) == 1) {
                        $valid[$tx_type]--;
                        break;
                    }
                }
            }
        }

        // Loop through rules, check if any transaction is a match.
        foreach ($rule as $tx_type => $def) {
            if ($valid[$tx_type] == 0) {
                // Load predefined info for this transaction type if detected.
                $return = $define[$tx_type];
                if ($tx_type === 'p2pk') {
                    $return['hash160'] = self::hash160($data[$define[$tx_type]['data_index_for_hash']]);
                    $return['addresses'][0] = self::hash160_to_address($return['hash160'], self::pubKeyAddress[0]);
                } else {
                    $return['hash160'] = $data[$define[$tx_type]['data_index_for_hash']];
                    $return['addresses'][0] = self::hash160_to_address($return['hash160'], ($tx_type === 'p2sh') ? self::scriptAddress[0] : self::pubKeyAddress[0]); // TODO: Pay to claim transaction?
                }
                unset($return['data_index_for_hash']);
            }
        }
        return (!isset($return)) ? false : $return;
    }

    public static function _decode_scriptPubKey($script, $matchBitcoinCore = false)
    {
        $data = array();
        while (strlen($script) !== 0) {
            $byteHex = self::_return_bytes($script, 1);
            $byteInt = hexdec($byteHex);

            if (isset(self::$op_code[$byteHex])) {
                // This checks if the OPCODE is defined from the list of constants.
                if ($matchBitcoinCore && self::$op_code[$byteHex] == "OP_0") {
                    $data[] = '0';
                } else if ($matchBitcoinCore && self::$op_code[$byteHex] == "OP_1") {
                    $data[] = '1';
                } else {
                    $data[] = self::$op_code[$byteHex];
                }
            } elseif ($byteInt >= 0x01 && $byteInt <= 0x4e) {
                // This checks if the OPCODE falls in the PUSHDATA range
                if ($byteInt == 0x4d) {
                    // OP_PUSHDATA2
                    $byteInt = hexdec(self::_return_bytes($script, 2, true));
                    $data[] = self::_return_bytes($script, $byteInt);
                } else if ($byteInt == 0x4e) {
                    // OP_PUSHDATA4
                    $byteInt = hexdec(self::_return_bytes($script, 4, true));
                    $data[] = self::_return_bytes($script, $byteInt);
                } else if ($byteInt == 0x4c) {
                    $num_bytes = hexdec(self::_return_bytes($script, 1, true));
                    $data[] = self::_return_bytes($script, $num_bytes);
                } else {
                    $data[] = self::_return_bytes($script, $byteInt);
                }
            } elseif ($byteInt >= 0x51 && $byteInt <= 0x60) {
                // This checks if the CODE falls in the OP_X range
                $data[] = $matchBitcoinCore ? ($byteInt - 0x50) : 'OP_' . ($byteInt - 0x50);
            } else {
                throw new \RuntimeException("Failed to decode scriptPubKey");
            }
        }

        return implode(" ", $data);
    }

    public static function lock($process_name) {
        if (!is_dir(TMP . 'lock')) {
            mkdir(TMP . 'lock');
        }
        $lock_file = TMP . 'lock' . DS . $process_name;
        if (file_exists($lock_file)) {
            echo "$process_name is already running.\n";
            exit(0);
        }
        file_put_contents($lock_file, '1');
    }

    public static function unlock($process_name) {
		$lock_file = TMP . 'lock' . DS . $process_name;
		if (file_exists($lock_file)) {
            unlink($lock_file);
        }
		return true;
	}

    public static function _return_bytes(&$string, $byte_count, $reverse = false)
    {
        if (strlen($string) < $byte_count * 2) {
            throw new \InvalidArgumentException("Could not read enough bytes");
        }
        $requested_bytes = substr($string, 0, $byte_count * 2);
        // Overwrite $string, starting $byte_count bytes from the start.
        $string = substr($string, $byte_count * 2);
        // Flip byte order if requested.
        return ($reverse == false) ? $requested_bytes : self::_flip_byte_order($requested_bytes);
    }

    public static function _flip_byte_order($bytes) {
        return implode('', array_reverse(str_split($bytes, 2)));
    }

    public static function _init_redis() {
        $redis = new \Predis\Client(self::redisurl);
        try {
            $redis->info('mem');
        } catch (\Predis\Connection\ConnectionException $e) {
            // redis not available
            $redis = null;
        }

        return $redis;
    }

    /*public function parsehistoryblocks() {
        set_time_limit(0);
        header('Content-type: text/plain');

        $block_hash = null;
        // Get the minimum block hash first
        $minBlock = $this->Blocks->find()->select(['Hash'])->order(['Height' => 'asc'])->first();
        if (!$minBlock) {
            // get the best block
            $req = ['method' => 'status'];
            $response = self::curl_json_post(self::lbryurl, json_encode($req));
            $json = json_decode($response);
            $block_hash = $json->result->blockchain_status->best_blockhash;
        } else {
            $block_hash = $minBlock->Hash;
        }

        echo "Processing block: $block_hash... ";
        $req = ['method' => 'block_show', 'params' => ['blockhash' => $block_hash]];
        $response = self::curl_json_post(self::lbryurl, json_encode($req));
        $json = json_decode($response);
        $block_data = $json->result;

        // Check if the block exists
        $oldBlock = $this->Blocks->find()->select(['Id'])->where(['Hash' => $block_hash])->first();
        if (!$oldBlock) {
            // Block does not exist, create the block
            $newBlock = $this->blockdb_data_from_json($block_data);
            $entity = $this->Blocks->newEntity($newBlock);
            $this->Blocks->save($entity);
        }
        echo "Done.\n";

        $prevBlockHash = isset($block_data->previousblockhash) ? $block_data->previousblockhash : null;
        do {
            $oldBlock = $this->Blocks->find()->select(['Id'])->where(['Hash' => $prevBlockHash])->first();
            $req = ['method' => 'block_show', 'params' => ['blockhash' => $prevBlockHash]];
            $response = self::curl_json_post(self::lbryurl, json_encode($req));
            $json = json_decode($response);
            $block_data = $json->result;
            $prevBlockHash = isset($block_data->previousblockhash) ? $block_data->previousblockhash : null;

            if (!$oldBlock) {
                echo "Inserting block: $block_data->hash... ";
                $newBlock = $this->blockdb_data_from_json($block_data);
                $entity = $this->Blocks->newEntity($newBlock);
                $this->Blocks->save($entity);
            } else {
                echo "Updating block: $block_data->hash with confirmations: $block_data->confirmations... ";
                $updData = ['Id' => $oldBlock->Id, 'Confirmations' => $block_data->confirmations];
                $entity = $this->Blocks->newEntity($newBlock);
                $this->Blocks->save($entity);
            }
            echo "Done.\n";
        } while($prevBlockHash != null && strlen(trim($prevBlockHash)) > 0);

        exit(0);
    }

    public function updatespends() {
        set_time_limit(0);

        self::lock('updatespends');

        try {
            $conn = ConnectionManager::get('default');
            $inputs = $this->Inputs->find()->select(['Id', 'PrevoutHash', 'PrevoutN'])->where(['PrevoutSpendUpdated' => 0, 'IsCoinbase <>' => 1])->limit(500000)->toArray();

            $count = count($inputs);
            $idx = 0;
            echo sprintf("Processing %d inputs.\n", $count);
            foreach ($inputs as $in) {
                $idx++;
                $idx_str = str_pad($idx, strlen($count), '0', STR_PAD_LEFT);

                $tx = $this->Transactions->find()->select(['Id'])->where(['Hash' => $in->PrevoutHash])->first();
                if ($tx) {
                    $data_error = false;

                    $conn->begin();

                    try {
                        // update the corresponding output and set it as spent
                        $conn->execute('UPDATE Outputs SET IsSpent = 1, SpentByInputId = ?, Modified = UTC_TIMESTAMP() WHERE TransactionId = ? AND Vout = ?', [$in->Id, $tx->Id, $in->PrevoutN]);
                    } catch (\Exception $e) {
                        $data_error = true;
                    }

                    if (!$data_error) {
                        // update the input
                        $in_data = ['Id' => $in->Id, 'PrevoutSpendUpdated' => 1];
                        $in_entity = $this->Inputs->newEntity($in_data);
                        $result = $this->Inputs->save($in_entity);

                        if (!$result) {
                            $data_error = true;
                        }
                    }

                    if ($data_error) {
                        echo sprintf("[$idx_str/$count] Could NOT update vout %s for transaction hash %s.\n", $in->PrevoutN, $in->PrevoutHash);
                        $conn->rollback();
                    } else {
                        echo sprintf("[$idx_str/$count] Updated vout %s for transaction hash %s.\n", $in->PrevoutN, $in->PrevoutHash);
                        $conn->commit();
                    }
                } else {
                    echo sprintf("[$idx_str/$count] Transaction NOT found for tx hash %s.\n", $in->PrevoutHash);
                }
            }
        } catch (\Exception $e) {
            print_r($e);
        }

        self::unlock('updatespends');
    }
    */
}

?>
