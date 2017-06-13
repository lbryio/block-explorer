<?php

namespace App\Shell;

use Cake\Console\ConsoleOutput;
use Cake\Console\Shell;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Mdanter\Ecc\EccFactory;

class AuxShell extends Shell {

    const pubKeyAddress = [0, 85];

    const scriptAddress = [5, 122];

    const rpcurl = 'http://lrpc:lrpc@127.0.0.1:9245';

    const tagrcptaddress = 'bLockNgmfvnnnZw7bM6SPz6hk5BVzhevEp';

    public function initialize() {
        parent::initialize();
        $this->loadModel('Addresses');
        $this->loadModel('Inputs');
        $this->loadModel('Outputs');
        $this->loadModel('Transactions');
        $this->loadModel('TagAddressRequests');
    }

    public function main() {
        echo "No arguments specified.\n";
    }

    public function verifytags() {
        self::lock('auxverify');

        $conn = ConnectionManager::get('default');
        $requests = $this->TagAddressRequests->find()->where(['IsVerified <>' => 1])->toArray();
        foreach ($requests as $req) {
            echo "Verifying tag for $req->Address, amount: $req->VerificationAmount... ";

            $req_date = $req->Created;
            $src_addr = $req->Address;
            $dst_addr = self::tagrcptaddress;

            // find a transaction with the corresponding inputs created on or after the date
            // look for the address ids
            $address = $this->Addresses->find()->select(['Id'])->where(['Address' => $src_addr])->first();
            $veri_address = $this->Addresses->find()->select(['Id'])->where(['Address' => $dst_addr])->first(); // TODO: Redis cache?
            if (!$address || !$veri_address) {
                echo "could not find source nor verification addresses. Skipping.\n";
                continue;
            }

            $src_addr_id = $address->Id;
            $dst_addr_id = $veri_address->Id;

            // find the inputs for the source address that were created after $req->Created - 1 hour
            $req_date->sub(new \DateInterval('PT1H'));
            $stmt = $conn->execute('SELECT DISTINCT I.TransactionId FROM Inputs I ' .
                                   'RIGHT JOIN (SELECT IIA.InputId FROM InputsAddresses IIA WHERE IIA.AddressId = ?) IA ON IA.InputId = I.Id ' .
                                   'JOIN Transactions T ON T.Id = I.TransactionId ' .
                                   'LEFT JOIN Blocks B ON B.Hash = T.BlockHash ' .
                                   'WHERE B.Confirmations > 0 AND DATE(I.Created) >= ?', [$src_addr_id, $req_date->format('Y-m-d')]);
            $tx_inputs = $stmt->fetchAll(\PDO::FETCH_OBJ);


            $param_values = [$dst_addr_id];
            $params = [];
            foreach ($tx_inputs as $in) {
                $params[] = '?';
                $param_values[] = $in->TransactionId;
            }

            $num_inputs = count($tx_inputs);
            echo "***found $num_inputs inputs from address $src_addr.\n";

            if ($num_inputs == 0) {
                continue;
            }

            try {
                // check the outputs with the dst address
                $total_amount = 0;
                $stmt = $conn->execute(sprintf('SELECT O.Value FROM Outputs O ' .
                                               'RIGHT JOIN (SELECT IOA.OutputId, IOA.AddressId FROM OutputsAddresses IOA WHERE IOA.AddressId = ?) OA ON OA.OutputId = O.Id ' .
                                               'WHERE O.TransactionId IN (%s)', implode(', ', $params)), $param_values);
                $tx_outputs = $stmt->fetchAll(\PDO::FETCH_OBJ);
                foreach ($tx_outputs as $out) {
                    echo "***found output to verification address with value " . $out->Value . "\n";
                    $total_amount = bcadd($total_amount, $out->Value, 8);
                }

                if ($total_amount >= $req->VerificationAmount) {
                    $conn->begin();
                    echo "***$total_amount is gte verification amount: $req->VerificationAmount.\n";

                    // Update the tag in the DB
                    $conn->execute('UPDATE Addresses SET Tag = ?, TagUrl = ? WHERE Address = ?', [$req->Tag, $req->TagUrl, $src_addr]);

                    // Set the request as verified
                    $conn->execute('UPDATE TagAddressRequests SET IsVerified = 1 WHERE Id = ?', [$req->Id]);

                    $conn->commit();
                    echo "Data committed.\n";
                } else {
                    echo "***$total_amount is NOT up to verification amount: $req->VerificationAmount.\n";
                }
            } catch (\Exception $e) {
                print_r($e);
                echo "Rolling back.\n";
                $conn->rollback();
            }
        }

        self::unlock('auxverify');
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
}

?>
