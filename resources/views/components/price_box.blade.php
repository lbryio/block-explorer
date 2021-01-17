<div class="price-box d-none d-md-inline-block rounded mt-1 ml-n1 text-nowrap">
    <span class="text-dark">LBC: ${{$priceInfo->priceUsd}}</span>
    <span>
        <span data-toggle="tooltip" data-placement="bottom" data-title="Changes in the last 24 hours">
            @if($priceInfo->percentChangeUsd > 0)
                <span class="text-success"> (+{{$priceInfo->percentChangeUsd}}%)</span>
            @else
                <span class="text-danger"> ({{$priceInfo->percentChangeUsd}}%)</span>
            @endif
        </span>
        <span class="text-muted"> | {{$priceInfo->priceBtc}} BTC</span>
    </span>
</div>
