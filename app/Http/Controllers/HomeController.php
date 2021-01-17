<?php

namespace App\Http\Controllers;

use App\Claim;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Block;
use App\Transaction;


class HomeController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $blocks = Block::orderBy('id', 'desc')->take(15)->get(['height', 'block_time', 'transaction_hashes', 'block_size', 'difficulty']);

        $transactions = Transaction::select('id', 'hash', 'transaction_time', 'value')
                                    ->where('block_hash_id', '<>' , 'MEMPOOL')
                                    ->orderBy('id', 'desc')
                                    ->take(15)
                                    ->with(['inputs', 'outputs'])
                                    ->get();

        $total_claims = Claim::where('bid_state', '<>', 'Expired')
            ->where("created_at", ">", Carbon::now()->subDay())
            ->count();

        $claims = Claim::where('bid_state', '<>', 'Expired')
            ->orderBy('id', 'desc')
            ->take(6)
            ->get();

        $now = Carbon::now();

        $blocks->transform(function ($item, $key) use ($now) {
            $item->age = Carbon::createFromTimestamp($item->block_time)->diffForHumans();
            $item->block_size /= 1000;
            $item->transactions = count(explode(',', $item->transaction_hashes));
            return $item;
        });

        $transactions->transform(function ($item, $key) use ($now) {
            $item->age = Carbon::createFromTimestamp($item->transaction_time)->diffForHumans();

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

        return view('home', [
          'blocks' => $blocks,
          'transactions' => $transactions,
          'claims' => $claims,
          'total_claims' => $total_claims
        ]);
    }
}
