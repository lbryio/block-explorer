@extends('minimalUI.blank')

@push('styles')
    <style>
        .card-image {
            height: 180px;
        }
        .card-image img {
            object-fit: cover;
        }
        .label {
            border-radius: 4px;
            color: white;
            padding-right: 1%;
            padding-left: 1%;
        }
        .nsfw-label {
            background-color: red;
        }
        .card-footer {
            color: white;
        }
        .lbry-tv-button {
            background: green;
        }
        .lbry-app-button {
            background: #1e88e5;
        }
    </style>
@endpush

@section('icon', 'pe-7s-airplay')
@section('title', 'Claim '.$claim->name)
@section('header', 'Claim · '.$claim->name)
@section('description')
    <div>
        {{$claim->claim_id}} @include('components.copy_to_clipboard_button', array('text' => $claim->claim_id, 'id' => 'claimHashClipboardHeader'))
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-5 mb-4 mb-lg-0">
            <div class="main-card mb-3 card">
                <div class="card-header d-flex justify-content-between">
                    <span>Overview</span>
                    @if ($claim->is_nsfw)
                        <span class="label nsfw-label">NSFW</span>
                    @endif
                </div>
                @if (!$claim->is_nsfw && strlen(trim($claim->thumbnail_url)) > 0)
                <div class="card-image">
                    <img class="img-fluid h-100 w-100" src="{{ htmlspecialchars($claim->thumbnail_url) }}" alt="" />
                </div>
                @endif
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-12 mb-2">
                            <div class="text-primary">Name</div>
                            {{ $claim->name }}
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 mb-2">
                            <div class="text-primary">Type</div>
                            @if ($claim->type == "stream")
                                Stream
                            @elseif ($claim->type == "channel")
                                Channel
                            @elseif ($claim->type == "claimreference")
                                Claim Reference
                            @elseif ($claim->type == "claimlist")
                                Claim List
                            @endif
                        </div>
                        <div class="col-lg-6 mb-2">
                            <div class="text-primary">Status</div>
                            @if ($claim->transaction_time == 0)
                                <i>Pending</i>
                            @else
                                {{ $claim->bid_state }}
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12 mb-2">
                            @if ($claim->transaction_time != 0)
                                <div class="text-primary">
                                    Timestamp
                                    <span class="fa fa-question-circle" data-toggle="tooltip" data-placement="top" title="Date and time at which the claim transaction was mined"></span>
                                </div>
                                <div>
                                    {{ $claim->claim_time }} ({{ $claim->claim_timestamp }})
                                </div>
                            @else
                                <div class="text-primary">
                                    Time First Seen
                                    <span class="fa fa-question-circle" data-toggle="tooltip" data-placement="top" title="Time when the claim transaction was first seen in the network pool"></span>
                                </div>
                                <div>
                                    <i class="fas fa-spinner fa-spin"></i>
                                    {{ $claim->first_seen_time_ago }}
                                    ({{ $claim->created_at }})
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12 mb-2">
                            <div class="text-primary">Transaction</div>
                            <a class="" href="{{ route('transaction', $claim->transaction_hash_id) }}">{{ $claim->transaction_hash_id }}</a>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12 mb-2">
                            <div class="text-primary">Address</div>
                            <a class="" href="{{ route('address', $claim->claim_address) }}">{{ $claim->claim_address }}</a>
                        </div>
                    </div>
                    @if ($claim->type == "stream")
                        <div class="row">
                            <div class="col-lg-12 mb-2">
                                <div class="text-primary">Channel</div>
                                @if ($claim->publisher_id)
                                    <a href="{{route("claim", $claim->publisher_id)}}">{{ $claim->publisher_name }}</a>
                                @else
                                    <i>No channel</i>
                                @endif
                            </div>
                        </div>
                    @endif
                    <div class="row">
                        <div class="col-lg-6 mb-2">
                            <div class="text-primary">Price</div>
                            @if ($claim->fee_currency == null)
                                <span>{{ $claim->fee }} LBC</span>
                            @elseif ($claim->fee_currency == "LBC")
                                <span>{{ $claim->fee/100000000 }} LBC</span>
                            @else
                                <span>{{ $claim->fee }} {{ $claim->fee_currency }}</span>
                            @endif
                        </div>
                        <div class="col-lg-6 mb-2">
                            <div class="text-primary">
                                Effective amount
                                <span class="fa fa-question-circle" data-toggle="tooltip" data-placement="top" title="The sum of the amount of an active claim and all of its active supports"></span>
                            </div>
                            <span>{{ $claim->effective_amount/100000000 }} LBC</span>
                        </div>
                    </div>
                    @if ($claim->type === "stream")
                        <div class="collapse" id="collapsePanel">
                            <div class="row">
                                <div class="col-lg-12 mb-2">
                                    <div class="text-primary">Publisher signature</div>
                                    {{ substr($claim->publisher_sig, 0, 40) }}... @include('components.copy_to_clipboard_button', array('text' => $claim->publisher_sig, 'id' => "claimPublisherSigClipboard"))
                                </div>
                            </div>
                            @if ($claim->sd_hash)
                                <div class="row">
                                    <div class="col-lg-12 mb-2">
                                        <div class="text-primary">SD hash</div>
                                        {{ substr($claim->sd_hash, 0, 40) }}... @include('components.copy_to_clipboard_button', array('text' => $claim->sd_hash, 'id' => "claimSdHashClipboard"))
                                    </div>
                                </div>
                            @endif
                            @if ($claim->source_hash)
                                <div class="row">
                                    <div class="col-lg-12 mb-2">
                                        <div class="text-primary">Source hash</div>
                                        {{ substr($claim->source_hash, 0, 40) }}... @include('components.copy_to_clipboard_button', array('text' => $claim->source_hash, 'id' => "claimSourceHashClipboard"))
                                    </div>
                                </div>
                            @endif
                        </div>
                        <span>
                            <a class="d-block collapsed text-decoration-none" id="collapseLink" data-toggle="collapse" href="#collapsePanel" role="button" aria-expanded="false" aria-controls="collapse">
                                <span class="d-flex">
                                    Click to see&nbsp;
                                    <span class="card-arrow-more">more</span>
                                    <span class="card-arrow-less">less</span>
                                    <span class="card-btn-arrow ml-2 d-flex align-items-center">
                                        <span class="fas fa-arrow-up small"></span>
                                    </span>
                                </span>
                            </a>
                        </span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-7 mb-4 mb-lg-0">
            <div class="main-card mb-3 card p-0">
                <div class="card-header">Metadata</div>
                <div class="card-body px-3 py-3">
                    <div class="row">
                        <div class="col-lg-12 mb-2">
                            <div class="text-primary">Title</div>
                            @if ($claim->title)
                                {{ $claim->title }}
                            @else
                                <i>No title</i>
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 mb-2">
                            <div class="text-primary">Media Type</div>
                            @if ($claim->source_media_type)
                                {{ $claim->source_media_type }}
                            @else
                                <i>No media type</i>
                            @endif
                        </div>
                        @if ($claim->source_size)
                            <div class="col-lg-6 mb-2">
                                <div class="text-primary">Source size</div>
                                @if ($claim->source_size)
                                    <span>{{ $claim->source_size }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="row">
                        <div class="col-lg-12 mb-2">
                            <div class="text-primary">Description</div>
                            @if ($claim->description)
                                {{ \Illuminate\Support\Str::limit($claim->description, 200, $end='...') }}
                            @else
                                <i>No description</i>
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-6 mb-2">
                            <div class="text-primary">Language</div>
                            @if ($claim->language)
                                {{ $claim->language }}
                            @else
                                <i>No language</i>
                            @endif
                        </div>
                        <div class="col-lg-6 mb-2">
                            <div class="text-primary">License</div>
                            @if ($claim->license)
                                @if ($claim->license_url)
                                    <a href="{{ $claim->license_url }}" target="_blank">{{ $claim->license }}</a>
                                @else
                                    {{ $claim->license }}
                                @endif
                            @else
                                <i>No license</i>
                            @endif
                        </div>
                    </div>
                    @if ($claim->frame_width and $claim->frame_height)
                        <div class="row">
                            <div class="col-lg-6 mb-2">
                                <div class="text-primary">Frame size</div>
                                @if ($claim->frame_width)
                                    {{ $claim->frame_width }} x {{ $claim->frame_height }}
                                @else
                                    <i>No width</i>
                                @endif
                            </div>
                        </div>
                    @endif
                    @if ($claim->duration or $claim->audio_duration)
                        <div class="row">
                            <div class="col-lg-6 mb-2">
                                <div class="text-primary">Duration</div>
                                @if ($claim->duration)
                                    {{ $claim->duration }}
                                @elseif ($claim->audio_duration)
                                    {{ $claim->audio_duration }}
                                @endif
                            </div>
                        </div>
                    @endif
                    @if ($claim->latitude or $claim->longitude)
                        <div class="row">
                            <div class="col-lg-6 mb-2">
                                <div class="text-primary">Latitude</div>
                                @if ($claim->latitude)
                                    {{ $claim->latitude }}
                                @else
                                    <i>No latitude</i>
                                @endif
                            </div>
                            <div class="col-lg-6 mb-2">
                                <div class="text-primary">Longitude</div>
                                @if ($claim->longitude)
                                    {{ $claim->longitude }}
                                @else
                                    <i>No longitude</i>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                <div class="card-footer">
                    <div class="col-lg-12 d-flex align-items-center justify-content-end">
                        @if ($claim->transaction_time == 0)
                            <i class="text-muted mr-3">Transaction pending</i>
                        @elseif ($claim->bid_state == "Spent")
                            <i class="text-muted mr-3">Claim spent</i>
                        @elseif ($claim->bid_state == "Expired")
                            <i class="text-muted mr-3">Claim expired</i>
                        @endif
                        <a class="lbry-tv-button px-2 py-2 text-reset text-decoration-none btn-link {{ ($claim->transaction_time == 0 or in_array($claim->bid_state, array('Expired', 'Spent'))) ? "disabled opacity-2" : "" }}"
                           href="{{'https://lbry.tv/' . $claim->name . ':' . $claim->claim_id}}"
                           target="_blank">
                            Open in lbry.tv
                        </a>
                        <a class="lbry-app-button px-2 py-2 ml-3 text-reset text-decoration-none btn-link {{ ($claim->transaction_time == 0 or in_array($claim->bid_state, array('Expired', 'Spent'))) ? "disabled opacity-2" : "" }}"
                           href="{{'lbry://' . $claim->name . ':' . $claim->claim_id}}">
                            Open in LBRY Desktop
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
