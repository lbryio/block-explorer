@extends('minimalUI.blank')

@section('icon', 'pe-7s-search')
@section('title', 'Search Claims')
@section('header', 'Search Claims')

@section('content')
    <div class="row">
        <div class="col-lg-12">
            <div class="main-card mb-3 card">
                <div class="card-body">
                    <div class="table-header d-flex justify-content-between mb-2">
                        <div class="card-title">Search · {{$query}}</div>
                        <div class="pagination">
                            {{ $claims->links() }}
                        </div>
                    </div>
                    <div class="mb-0 table table-hover table-striped">
                        <div class="d-flex flex-wrap col-lg-12 p-0">
                            @foreach ($claims as $claim)
                                <div class="col-xs-12 col-md-4 mb-3 px-2 py-0">
                                    @include('components.claim_box', array(['claim' => $claim]))
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
