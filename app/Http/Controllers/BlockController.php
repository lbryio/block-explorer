<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Block;
use App\Transaction;

class BlockController extends Controller
{

  public function getBlocks() {
    $blocks = Block::select('height', 'block_time', 'transaction_hashes', 'block_size', 'difficulty', 'nonce')->orderBy('id', 'desc')->simplePaginate(25);

    $previous_block_difficulty = 0;  // used to calculate difficulty variation from the previous and current block

    //reversing done because need to access block in chronological order in order to calculate difficulty diff
    $blocks->reverse()->transform(function ($item, $key) use (& $previous_block_difficulty) {
        $item->block_time = Carbon::parse($item->block_time)->diffForHumans(null, false, false, 2);
        $item->block_size /= 1000;
        $item->transactions = count(explode(',', $item->transaction_hashes));

        if($previous_block_difficulty != 0) {  //if here : this is not the oldest block in the page
          //calculating difficulty diff percentage between previous and current block
          $item->difficulty_diff_percent = number_format((($item->difficulty - $previous_block_difficulty) / $previous_block_difficulty * 100), 1);
        }
        $previous_block_difficulty = $item->difficulty;

        return $item;
    });

    return view('blocks', [
      'blocks' => $blocks
    ]);
  }

    public function getBlock($height = null) {
        if($height) {
            $block = Block::where('height', $height)->firstOrFail();
            $transactions = $block->transactions()->get(['hash', 'value', 'input_count', 'output_count', 'fee', 'transaction_size']);

            $block->small_hash = substr($block->hash, 0, 10).'...'.substr($block->hash, -10);

            $block->block_size /= 1000;
            $block->block_time = Carbon::createFromTimestamp($block->block_time)->format('d M Y  H:i:s');
            $block->block_timestamp = Carbon::parse($block->block_time)->diffForHumans(null, false, false, 2);

            $block->confirmations = Block::latest()->take(1)->value('height') - $block->height;

            $transactions->transform(function ($item, $key) {
                $item->transaction_size /= 1000;
                return $item;
            });

            return view('block', [
                'block' => $block,
                'transactions' => $transactions
            ]);

        } else {
            return redirect('/blocks');
        }
    }
}
