<?php

namespace App\Http\Middleware;

use CLosure;
use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

class Price
{
    private $bittrexApiUrl = 'https://api.bittrex.com';
    private $bittrexApiClient;

    function __construct() {
        $this->bittrexApiClient = new Client([
            'base_uri' => $this->bittrexApiUrl,
            'timeout' => 2.0,
            'http_errors' => false
        ]);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Cache::has('priceInfo')) {
            $priceInfo = Cache::get('priceInfo');
        } else {
            $priceInfo = $this->getPrice();
            Cache::put('priceInfo', $priceInfo, $seconds = 60);
        }
        $request->attributes->add(['priceInfo' => $priceInfo]);
        View::share('priceInfo', $priceInfo);
        return $next($request);
    }

    private function getPrice() {
        $bittrexBtcTicker = 'v3/markets/LBC-BTC/ticker';
        $bittrexUsdTicker = 'v3/markets/LBC-USDT/ticker';
        $bittrexUsdSummary = 'v3/markets/LBC-BTC/summary';

        $btcResponse = json_decode($this->get($bittrexBtcTicker));
        $usdResponse= json_decode($this->get($bittrexUsdTicker));
        $usdSummaryResponse= json_decode($this->get($bittrexUsdSummary));

        if ($btcResponse->symbol) {
            $last_price_btc = $btcResponse->lastTradeRate;
            $now = new DateTime('now', new DateTimeZone('UTC'));
            if ($usdResponse->symbol) {
                $last_price_usd = $usdResponse->lastTradeRate;
                $percentChangeUsd = $usdSummaryResponse->percentChange;
                $priceInfo = new \stdClass();
                $priceInfo->priceUsd = number_format($last_price_usd, 4, '.', '');
                $priceInfo->percentChangeUsd = $percentChangeUsd;
                $priceInfo->priceBtc = number_format($last_price_btc, 9, '.', '');
                $priceInfo->time = $now->format('c');

                if ($priceInfo) {
                    return $priceInfo;
                } else {
                    echo "Could not insert price history item. USD: $last_price_usd, BTC: $last_price_btc.\n";
                }
            }
        } else {
            echo "Bittrex request returned an invalid result.\n";
        }
    }

    private function get($url) {
        $response = $this->bittrexApiClient->request('GET', $url);

        if ($response->getStatusCode() != 200) {
            return false;
        }

        return (string) $response->getBody();
    }
}
