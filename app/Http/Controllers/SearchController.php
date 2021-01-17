<?php

namespace App\Http\Controllers;

use App\Claim;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Block;
use App\Transaction;


class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $input = trim($request->get('q'));

        if(preg_match("/^[a-zA-Z0-9]{34}$/",$input)) {
            return redirect(route('address', $input));
        }
        if(preg_match("/^[a-zA-Z0-9]{64}$/",$input)) {
            return redirect(route('transaction', $input));
        }
        if(preg_match("/^[a-zA-Z0-9]{40}$/",$input)) {
            return redirect(route('claim', $input));
        }
        if(preg_match("/^[0-9]{0,10}$/",$input)) {
            return redirect(route('block', $input));
        }
        if(preg_match("/^[0-9A-Za-z \-]{0,100}$/",$input)) {
            $claims = Claim::where('name', '=', $input)
                ->orderBy('effective_amount', 'desc')
                ->simplePaginate(25);

            return view('search_claims', [
                'claims' => $claims,
                'query' => $input
            ]);
        }
    }
}
