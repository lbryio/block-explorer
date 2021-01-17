<?php

namespace App\Http\Controllers;

use App\Claim;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Transaction;
use App\Input;
use App\Output;
use App\Block;
use App\Address;

class TransactionController extends Controller
{
    public function getTransactions() {
        $transactions = Transaction::select('id', 'hash', 'transaction_time', 'value', 'input_count', 'output_count', 'transaction_size', 'block_hash_id')
            ->where('block_hash_id', '<>', 'MEMPOOL')
            ->orderBy('transaction_time', 'desc')
            ->with(['inputs', 'outputs'])
            ->simplePaginate(25);

        $transactions->transform(function ($item, $key) {
            $item->block_height = Block::where('hash', $item->block_hash_id)->value('height');
            $item->transaction_time = Carbon::parse($item->transaction_time)->diffForHumans(null, false, false, 2);
            $item->transaction_size /= 1000;

            //lets calculate fees!
            $item->fee = 0;
            if($item->inputs[0]->is_coinbase) {
                return $item;
            }
            foreach($item->inputs as $input) {
                $item->fee += $input->value;
            }
            foreach($item->outputs as $output) {
                $item->fee -= $output->value;
            }
            $item->fee = sprintf("%.f", $item->fee);

            return $item;
        });

        return view('transactions', [
            'transactions' => $transactions
        ]);
    }

    public function getTransaction($tx = null) {
        if($tx) {
            $tx = Transaction::where('hash', $tx)->firstOrFail();

            $tx->small_hash = substr($tx->hash, 0, 10).'...'.substr($tx->hash, -10);

            $inputs = $tx->inputs()
                ->leftJoin('address', 'input.input_address_id', 'address.id')
                ->select('input.prevout_hash', 'input.is_coinbase', 'input.value', 'input.script_sig_hex', 'address.address')
                ->orderBy('vin')
                ->get();

            $outputs = $tx->outputs()
                ->leftJoin('input', 'output.spent_by_input_id', 'input.id')
                ->select('output.value', 'output.vout', 'output.type', 'output.script_pub_key_asm', 'output.script_pub_key_hex', 'output.address_list', 'output.is_spent','input.transaction_hash as spent_hash')
                ->orderBy('vout')
                ->get();

            $tx->first_seen_time_ago = Carbon::parse($tx->created_at)->diffForHumans(null, false, false, 2);
            $tx->transaction_size /= 1000;

            if($tx->block_hash_id != 'MEMPOOL') {
                $tx->transaction_time = Carbon::parse($tx->created_time)->format('d M Y  H:i:s');
                $tx->block_height = Block::where('hash', $tx->block_hash_id)->value('height');
                $tx->confirmations = Block::latest()->take(1)->value('height') - $tx->block_height;
                $tx->confirmation_difference = Carbon::parse($tx->created_at)->diffForHumans(Carbon::parse($tx->transaction_time), true, true, 2);

                // calculate transaction fee by inputs and outputs
                $tx->fee = 0;
                if(!$tx->inputs[0]->is_coinbase) {
                    foreach($inputs as $input) {
                        $tx->fee += $input->value;
                    }
                    foreach($outputs as $output) {
                        $tx->fee -= $output->value;
                    }
                    $tx->fee = sprintf("%.f", $tx->fee);
                }
            }

            foreach ($outputs as $output) {
                //output.address_list is an array of address, lets parse
                $output->address_list = ltrim($output->address_list, "[");
                $output->address_list = rtrim($output->address_list, "]");
                $output->address_list = str_replace('"', '', $output->address_list);
                $output->address_list = explode(',', $output->address_list);
                $claim = Claim::where(['transaction_hash_id' => $tx->hash, 'vout' => $output->vout])
                    ->select(['claim_id', 'vout', 'transaction_hash_id'])
                    ->first();
                if($claim !== null) {
                    $output->claim_id = $claim->claim_id;
                }


                //check transaction opcode {OP_DUP | OP_CLAIM_NAME | OP_UPDATE_CLAIM | OP_SUPPORT_CLAIM}
                $output->opcode_friendly = explode(' ', $output->script_pub_key_asm)[0];
                switch($output->opcode_friendly) {
                    case 'OP_DUP':
                        // if standard transaction (type: pubkeyhash) then pass blank opcode to view
                        $output->opcode_friendly = " ";
                        break;
                    case 'OP_CLAIM_NAME':
                        $output->opcode_friendly = "NEW CLAIM";
                        break;
                    case 'OP_UPDATE_CLAIM':
                        $output->opcode_friendly = "UPDATE CLAIM";
                        break;
                    case 'OP_SUPPORT_CLAIM':
                        $output->opcode_friendly = "SUPPORT CLAIM";
                        break;
                }
            }

            return view('transaction', [
                'transaction' => $tx,
                'inputs' => $inputs,
                'outputs' => $outputs
            ]);
        } else {
            return redirect(route('transactions'));
        }
    }

    public function getMempoolTransactions() {
        $transactions = Transaction::select('hash', 'value', 'created_at', 'input_count', 'output_count', 'transaction_size')
            ->where('block_hash_id', 'MEMPOOL')
            ->orderBy('id', 'desc')
            ->simplePaginate(25);

        $transactions->transform(function ($item, $key) {
            $item->transaction_size /= 1000;
            $item->transaction_time = Carbon::parse($item->created_at)->diffForHumans(null, false, false, 2);
            return $item;
        });

        return view('transactions_mempool', [
            'transactions' => $transactions
        ]);
    }
}
