@extends('minimalUI.blank')

@section('icon', 'pe-7s-notebook')
@section('title', 'Content Stats')
@section('header', 'Content Stats')

@section('content')
    <div class="row">
        <div class="col-md-6 col-xl-4">
            <div class="card mb-3 widget-content bg-premium-dark">
                <div class="widget-content-wrapper text-white w-100">
                    <div class="widget-content-left">
                        <div class="widget-heading">Network Hashrate</div>
                    </div>
                    <div class="widget-content-right">
                        <div class="widget-numbers text-white"><span>392766.87 GH/s</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <div class="main-card mb-3 card">
                <div class="card-body">
                    <h5 class="card-title">Top Claims</h5>
                    <table class="mb-0 table table-hover">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Amount</th>
                            <th>ID</th>
                            <th>Size</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($top_claims as $claim)
                            <tr>
                                <td scope="row"><a href="{{ route('claim', $claim->claim_id) }}">{{ $claim->name }}</a></td>
                                <td>{{ number_format($claim->effective_amount / 100000000, 8, '.', ',') }}</td>
                                <td>OK</td>
                                <td>OK</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4 mb-lg-0">
            <div class="main-card mb-3 card">
                <div class="card-body">
                    <h5 class="card-title">Top Channels</h5>
                    <table class="mb-0 table table-hover">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Amount</th>
                            <th>Value</th>
                            <th>Fee</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($top_channels as $channel)
                            <tr>
                                <td scope="row"><a href="{{ route('claim', $channel->claim_id) }}">{{ $channel->name }}</a></td>
                                <td>{{ number_format($channel->effective_amount / 100000000, 8, '.', ',') }}</td>
                                <td>OK</td>
                                <td>OK</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
