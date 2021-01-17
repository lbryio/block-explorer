<div class="claimBox" id="claim_{{$claim->id}}">
    <div class="claimBoxHeader">
        <div class="claimTags px-2 d-flex">
            @if ($claim->getContentTag())
                <div class="contentTag claimTag text-uppercase px-2 py-1 mr-2">{{ $claim->getContentTag() }}</div>
            @endif
            @if ($claim->bid_state == 'Controlling')
                <div class="bidStateTag claimTag px-2 py-1 mr-2">Controlling</div>
            @endif
            @if ($claim->transaction_time == 0)
                <div class="pendingTag claimTag px-2 py-1 mr-2">Pending</div>
            @endif
            @if ($claim->is_nsfw)
                <div class="nsfwTag claimTag px-2 py-1 mr-2">NSFW</div>
            @endif
        </div>
        <a href="{{ route('claim', $claim->claim_id) }}">
            <div class="claimImage">
                @if (!$claim->is_nsfw && strlen(trim($claim->thumbnail_url)) > 0)
                    <img class="claimThumbnail img-fluid h-100 w-100" src="{{ htmlspecialchars($claim->thumbnail_url) }}" alt="" />
                @else
                    <div class="claimDefaultImage d-flex align-items-center justify-content-center h-100">
                        <img src="{{ asset('images/logo.svg') }}" title="LBRY Explorer" height="50" width="50" />
                    </div>
                @endif
            </div>
        </a>
    </div>
    <div class="claimBoxBody px-2 py-3">
        <a class="text-reset" href="{{ route('claim', $claim->claim_id) }}">
            <div class="claimTitle pr-4">
                @if ($claim->title)
                    {{$claim->title}}
                @elseif ($claim->name)
                    {{$claim->name}}
                @else
                    <i>No Title</i>
                @endif
            </div>
        </a>
    </div>
    <div class="claimBoxFooter px-2">
        <a class="text-reset text-decoration-none" href="{{ route('transaction', $claim->transaction_hash_id) }}">
            <div class="claimTransactionLink d-flex align-items-center justify-content-center py-2">
                View transaction
            </div>
        </a>
    </div>
</div>
