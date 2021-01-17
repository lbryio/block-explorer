@extends('minimalUI.blank')

@push('styles')
<style>
  .my-custom-scrollbar {
    position: relative;
    overflow: auto;
  }
  .table-wrapper-scroll-y {
    display: block;
  }
</style>
@endpush

@section('icon', 'pe-7s-wallet')
@section('title', 'Address '.$address->address)
@section('header', 'Address')
@section('description')
    <div>{{$address->address}} @include('components.copy_to_clipboard_button', array('text' => $address->address, 'id' => 'addressClipboardHeader'))</div>
@endsection

@section('content')

<div class="row">
  <div class="col-lg-12 mb-4 mb-lg-0">
    <div class="main-card mb-3 card">
      <div class="row">
        <div class="col-md-3">
          <div class="pt-0 pb-0 card-body">
            <ul class="list-group list-group-flush">
              <li class="list-group-item px-1">
                <div class="widget-content p-0">
                  <div class="widget-content-outer">
                    <div class="widget-content-wrapper">
                      <div class="widget-content-left">
                        <div class="text-primary">Balance</div>
                      </div>
                      <div class="ml-3">
                        <span class="">{{ $address->balance }} LBC</span>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
        <div class="col-md-3">
          <div class="pt-0 pb-0 card-body">
            <ul class="list-group list-group-flush">
              <li class="list-group-item px-1">
                <div class="widget-content p-0">
                  <div class="widget-content-outer">
                    <div class="widget-content-wrapper">
                      <div class="widget-content-left">
                        <div class="text-primary">Value</div>
                      </div>
                      <div class="ml-3">
                        <span class="">${{ $address->balance * $priceInfo->priceUsd }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
        <div class="col-md-3">
          <div class="pt-0 pb-0 card-body">
            <ul class="list-group list-group-flush">
              <li class="list-group-item px-1">
                <div class="widget-content p-0">
                  <div class="widget-content-outer">
                    <div class="widget-content-wrapper">
                      <div class="widget-content-left">
                        <div class="text-primary">Received</div>
                      </div>
                      <div class="ml-3">
                        <div class="">{{ $address->total_received }} LBC</div>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
        <div class="col-md-3">
          <div class="pt-0 pb-0 card-body">
            <ul class="list-group list-group-flush">
              <li class="list-group-item px-1">
                <div class="widget-content p-0">
                  <div class="widget-content-outer">
                    <div class="widget-content-wrapper">
                      <div class="widget-content-left">
                        <div class="text-primary">Sent</div>
                      </div>
                      <div class="ml-3">
                        <div class="">{{ $address->total_sent }} LBC</div>
                      </div>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-lg-12">
    <div class="main-card mb-3 card">
      <div class="card-body table-wrapper-scroll-y my-custom-scrollbar">
          <div class="table-header d-flex justify-content-between mb-2">
              <div class="card-title">Transactions</div>
              <div class="pagination">
                  {{ $transactions->links() }}
              </div>
          </div>
        <table class="mb-0 table table-hover table-striped">
          <thead>
            <tr>
              <th>Height</th>
              <th>Transaction Hash</th>
              <th>Timestamp</th>
              <th>Inputs</th>
              <th>Outputs</th>
              <th>Size</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($transactions as $transaction)
              <tr>
                <th scope="row"><a href="{{ route('block', $transaction->height) }}">{{ $transaction->height }}</a></th>
                <td><a href="{{ route('transaction', $transaction->hash) }}">{{ substr($transaction->hash, 0, 15) }}..</a></td>
                <td>{{ $transaction->transaction_time }} UTC</td>
                <td>{{ $transaction->input_count }}</td>
                <td>{{ $transaction->output_count }}</td>
                <td>{{ $transaction->transaction_size }} kB</td>
                @if ($transaction->transaction_amount > 0)
                  <td class="text-success font-weight-bold">{{ $transaction->transaction_amount }} LBC</td>
                @else
                  <td class="text-primary font-weight-bold">{{ $transaction->transaction_amount }} LBC</td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


@endsection
