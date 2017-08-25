<?php

define('TMP', '/tmp/');
define('DS', '/');

class BlockSyncThread extends \Thread {
    private $_startHeight;
    private $_endHeight;
    private $_maxHeight;

    public function __construct($startBlock, $endBlock, $maxHeight) {
        $this->_startHeight = $startBlock;
        $this->_endHeight = $endBlock;
        $this->_maxHeight = $maxHeight;
    }

    public function run() {
        $conn = new \PDO("mysql:host=localhost;dbname=lbry", 'lbry-admin', '46D861aX#!yQ');
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $data_error = false;
        $conn->beginTransaction();
        for ($curr_height = $this->_startHeight; $curr_height <= $this->_endHeight; $curr_height++) {
            $idx_str = str_pad($curr_height, strlen($this->_maxHeight), '0', STR_PAD_LEFT);

            // get the block hash
            $req = ['method' => 'getblockhash', 'params' => [$curr_height]];
            $response = BlockStuff::curl_json_post(BlockStuff::rpcurl, json_encode($req));
            $json = json_decode($response);
            $curr_block_hash = $json->result;

            $req = ['method' => 'getblock', 'params' => [$curr_block_hash]];
            $response = BlockStuff::curl_json_post(BlockStuff::rpcurl, json_encode($req));
            $json = json_decode($response);
            $curr_block = $json->result;

            $stmt = $conn->prepare('UPDATE Blocks SET Confirmations = ? WHERE Height = ?');
            try {
                $stmt->execute([$curr_block->confirmations, $curr_height]);
                echo "[$idx_str/$this->_maxHeight] Updated block height: $curr_height with confirmations $curr_block->confirmations.\n";
            } catch (Exception $e) {
                $data_error = true;
            }
        }

        if ($data_error) {
            echo "Rolling back changes.\n";
            $conn->rollBack();
            return;
        }

        echo "Committing data.\n";
        $conn->commit();
    }
}

class BlockStuff {
    const rpcurl = 'http://lrpc:lrpc@127.0.0.1:9245';
    public static function blocksync() {
        self::lock('blocksync');

        $conn = new \PDO("mysql:host=localhost;dbname=lbry", 'lbry-admin', '46D861aX#!yQ');

        $stmt = $conn->prepare('SELECT Height FROM Blocks ORDER BY Height DESC LIMIT 1');
        $stmt->execute([]);
        $max_block = $stmt->fetch(PDO::FETCH_OBJ);
        if ($max_block) {
            $chunk_limit = 2;
            $curr_height = 0;
            $chunks = floor($max_block->Height / $chunk_limit);
            $threads = [];
            for ($i = 0; $i < $chunk_limit; $i++) {
                $start = $curr_height;
                $end = ($i == ($chunk_limit - 1)) ? $max_block->Height : $start + $chunks;
                $curr_height += $chunks + 1;
                $thread = new BlockSyncThread($start, $end, $max_block->Height);
                $threads[] = $thread;

                $thread->start();
            }

            for ($i = 0; $i < count($threads); $i++) {
                $threads[$i]->join();
            }
        }

        self::unlock('blocksync');
    }

    public static function curl_json_post($url, $data, $headers = []) {
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

BlockStuff::blocksync();
