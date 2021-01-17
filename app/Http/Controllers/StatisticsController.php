<?php

namespace App\Http\Controllers;

use App\Claim;


class StatisticsController extends Controller
{
    public function getMiningStats() {
        return view('statistics_mining', [
        ]);
    }

    public function getContentStats() {
        $top_claims = Claim::select('name', 'effective_amount', 'claim_id')
            ->where('claim_type', '=', '1')
            ->take(10)
            ->get();

        $top_channels = Claim::select('name', 'effective_amount', 'claim_id')
            ->where('claim_type', '=', '2')
            ->take(10)
            ->get();

        return view('statistics_content', [
            'top_claims' => $top_claims,
            'top_channels' => $top_channels
        ]);
    }
}
