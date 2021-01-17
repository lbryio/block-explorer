<?php

namespace App\Http\Controllers;

use App\Claim;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClaimController extends Controller
{
    public function getClaims() {
        $claims = Claim::orderBy('id', 'desc')
            ->simplePaginate(25);

        return view('claims', [
            'claims' => $claims
        ]);
    }

    public function getClaim($claim = null) {
        if($claim) {
            $claim = DB::table('claim as C')
                ->select('C.*', 'P.name as publisher_name')
                ->where('C.claim_id', $claim)
                ->leftJoin('claim AS P', 'P.claim_id', '=', 'C.publisher_id')
                ->first();

            $claim->first_seen_time_ago = Carbon::parse($claim->created_at)->diffForHumans(null, false, false, 2);
            $claim->claim_time = Carbon::createFromTimestamp($claim->transaction_time)->format('d M Y  H:i:s');
            $claim->claim_timestamp = Carbon::parse($claim->transaction_time)->diffForHumans(null, false, false, 2);
            $claim->source_size = $this->formatBytes($claim->source_size);

            return view('claim', [
                'claim' => $claim
            ]);
        } else {
            return redirect(route('claims'));
        }
    }

    /**
     * Format bytes to kb, mb, gb, tb
     *
     * @param  integer $size
     * @param  integer $precision
     * @return integer
     */
    public function formatBytes($size, $precision = 2)
    {
        if ($size > 0) {
            $size = (int) $size;
            $base = log($size) / log(1024);
            $suffixes = array(' bytes', ' KB', ' MB', ' GB', ' TB');

            return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
        } else {
            return $size;
        }
    }
}
