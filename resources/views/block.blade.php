@extends('minimalUI.blank')

@push('styles')
<style>
  .my-custom-scrollbar {
    position: relative;
    height: 495px;
    overflow: auto;
  }

  .table-wrapper-scroll-y {
    display: block;
  }
</style>
@endpush

@section('icon', 'pe-7s-box2')
@section('title', 'Block #'.$block->height)
@section('header', 'Block #'.$block->height)
@section('description')
    <div>{{$block->hash}} @include('components.copy_to_clipboard_button', array('text' => $block->hash, 'id' => 'blockHashClipboardHeader'))</div>
@endsection

@section('content')
<div class="row">
  <div class="col-lg-12">
    <a class="mb-2 mr-2 btn-transition btn btn-sm btn-outline-primary float-left" href="{{ route('blocks', $block->height - 1) }}">« Previous Block</a>
    <a class="mb-2 mr-2 btn-transition btn btn-sm btn-outline-primary float-right"
      @if ($block->confirmations == 0)
        data-toggle="tooltip" title="" data-placement="top" data-original-title="This is the last mined block!" href="#"
      @else
        href="{{ route('block', $block->height + 1) }}"
      @endif
      >Next Block »</a>
  </div>
</div>

<div class="row">
  <div class="col-lg-5 mb-4 mb-lg-0">
    <div class="main-card mb-3 card">
      <div class="card-header">Overview</div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-12 mb-2">
                    <div class="text-primary">Timestamp</div>
                    {{ $block->block_time }} UTC ({{ $block->block_timestamp }})
                </div>
            </div>
          <div class="row">
            <div class="col-lg-6 mb-2">
              <div class="text-primary">Block Size</div>
                {{ $block->block_size }} kB
            </div>
              <div class="col-lg-6 mb-2">
                  <div class="text-primary">Transactions</div>
                  {{ count($transactions) }}
              </div>
          </div>
          <div class="row">
            <div class="col-lg-6 mb-2">
              <div class="text-primary">Bits</div>
                {{ $block->bits }}
            </div>
            <div class="col-lg-6 mb-2">
              <div class="text-primary">Confirmations</div>
                {{ $block->confirmations }}
            </div>
          </div>
          <div class="row">
            <div class="col-lg-6 mb-2">
              <div class="text-primary">Difficulty</div>
                {{ number_format($block->difficulty) }}
            </div>
            <div class="col-lg-6 mb-2">
              <div class="text-primary">Nonce</div>
                {{ $block->nonce }}
            </div>
          </div>
          <div class="row">
            <div class="col-lg-12 mb-2">
              <div class="text-primary">Hash</div>
                <span>{{ $block->hash }}</span>@include('components.copy_to_clipboard_button', array('text' => $block->hash, 'id' => "blockHashCLipboard"))
            </div>
          </div>
            <div class="collapse" id="collapsePanel">
                <div class="row">
                    <div class="col-lg-12 mb-2">
                        <div class="text-primary">Version</div>
                        {{ $block->version }}
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-12 mb-2">
                        <div class="text-primary">Chainwork</div>
                        <span>{{ $block->chainwork }}</span>@include('components.copy_to_clipboard_button', array('text' => $block->chainwork, 'id' => "blockChainworkClipboard"))
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-12 mb-2">
                        <div class="text-primary">Merkle Root</div>
                        <span>{{ $block->merkle_root }}</span>@include('components.copy_to_clipboard_button', array('text' => $block->merkle_root, 'id' => "blockMerkleRootClipboard"))
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-12 mb-2">
                        <div class="text-primary">Name Claim Root</div>
                        <span>{{ $block->name_claim_root }}</span>@include('components.copy_to_clipboard_button', array('text' => $block->name_claim_root, 'id' => "blockNameClaimRootClipboard"))
                    </div>
                </div>
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
        </div>
    </div>
  </div>
  <div class="col-lg-7 mb-4 mb-lg-0">
    <div class="main-card mb-3 card">
        <div class="card-body table-wrapper-scroll-y my-custom-scrollbar">
          <h5 class="card-title">Block Transactions</h5>
            <table class="mb-0 table table-hover">
                <thead>
                <tr>
                    <th>Hash</th>
                    <th>Inputs</th>
                    <th>Outputs</th>
                    <th>Value</th>
                    <th>Size</th>
                    <th>Fee</th>
                </tr>
                </thead>
                <tbody>
                  @foreach ($transactions as $transaction)
                    <tr>
                      <td scope="row"><a href="{{ route('transaction', $transaction->hash) }}">{{ substr($transaction->hash, 0, 10) }}..</a></td>
                      <td>{{ $transaction->input_count }}</td>
                      <td>{{ $transaction->output_count }}</td>
                      <td>{{ $transaction->value }} LBC</td>
                      <td>{{ $transaction->transaction_size }} kB</td>
                      <td>{{ $transaction->fee }} LBC</td>
                    </tr>
                  @endforeach
                </tbody>
            </table>
        </div>
    </div>
    </div>
  </div>



@endsection
