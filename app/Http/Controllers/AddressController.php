<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Address;
use App\TransactionAddress;


class AddressController extends Controller
{
    public function getAddresses() {
        $genesisBlockAddress = 'bHW58d37s1hBjj3wPBkn5zpCX3F8ZW3uWf';

        $addresses = Address::where('address', '<>', $genesisBlockAddress)
            ->orderBy('balance', 'desc')
            ->simplePaginate(100);

        $addresses->transform(function ($item, $key) {
            // Addresses owned by LBRY
            $lbryAddresses = array('rEqocTgdPdoD8NEbrECTUPfpquJ4zPVCJ8', 'rKaAUDxr24hHNNTQuNtRvNt8SGYJMdLXo3', 'r7hj61jdbGXcsccxw8UmEFCReZoCWLRr7t', 'bRo4FEeqqxY7nWFANsZsuKEWByEgkvz8Qt', 'bU2XUzckfpdEuQNemKvhPT1gexQ3GG3SC2', 'bay3VA6YTQBL4WLobbG7CthmoGeUKXuXkD', 'bLPbiXBp6Vr3NSnsHzDsLNzoy5o36re9Cz', 'bVUrbCK8hcZ5XWti7b9eNxKEBxzc1rr393', 'bZja2VyhAC84a9hMwT8dwTU6rDRXowrjxH', 'bMgqQqYfwzWWYBk5o5dBMXtCndVAoeqy6h', 'bMvUBo1h5WS46ThHtmfmXftz3z33VHL7wc', 'bX6napXtY2nVTBRc8PwULBuGWn2i3SCtrN');

            // Max LBC token supply
            $maxSupply = 1083202000;
            $item->first_seen = Carbon::parse($item->first_seen)->format('d M Y  H:i:s');

            $item->isLbryAddress = in_array($item->address, $lbryAddresses);
            $item->percentageSupply = bcdiv($item->balance, $maxSupply, 8) * 100;
            return $item;
        });

        return view('addresses', [
            'addresses' => $addresses
        ]);
    }

    public function getAddress($address) {

        $address = Address::where('address', $address)->firstOrFail();
        $txs = TransactionAddress::where('address_id', $address->id)
            ->leftJoin('transaction', 'transaction_address.transaction_id', 'transaction.id')
            ->leftJoin('block', 'transaction.block_hash_id', 'block.hash')
            ->select('transaction_address.credit_amount', 'transaction_address.debit_amount', 'transaction.transaction_time', 'transaction.transaction_size', 'transaction.hash', 'transaction.input_count', 'transaction.output_count', 'block.height')
            ->orderBy('height', 'desc')
            ->simplePaginate(25);

        $transaction_amounts = TransactionAddress::where('address_id', $address->id)
            ->leftJoin('transaction', 'transaction_address.transaction_id', 'transaction.id')
            ->select('transaction_address.credit_amount', 'transaction_address.debit_amount')
            ->get();

        $address->total_received = 0;
        $address->total_sent = 0;
        foreach($transaction_amounts as $amount) {
            $transaction_amount = $amount->credit_amount - $amount->debit_amount;
            if($transaction_amount > 0) {
                $address->total_received += $transaction_amount;
            } else {
                $address->total_sent += abs($transaction_amount);
            }
        }

        $txs->transform(function ($item, $key) use (& $address) {
            $item->transaction_time = Carbon::createFromTimestamp($item->transaction_time)->format('d M Y  H:i:s');
            $item->transaction_size /= 1000;
            $item->transaction_amount = $item->credit_amount - $item->debit_amount;

            return $item;
        });

        return view('address', [
            'address' => $address,
            'transactions' => $txs
        ]);
    }
}
