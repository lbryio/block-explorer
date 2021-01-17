@extends('minimalUI.blank')

@section('icon', 'pe-7s-diamond')
@section('title', 'Mempool Transactions')
@section('header', 'Mempool Transactions')

@section('content')
<div class="row">
  <div class="col-lg-12">
    <div class="main-card mb-3 card">
      <div class="card-body">
          <div class="table-header d-flex justify-content-between mb-2">
              <div class="card-title">Mempool transactions</div>
              <div class="pagination">
                  {{ $transactions->links() }}
              </div>
          </div>
        <table class="mb-0 table table-hover table-striped">
          <thead>
            <tr>
              <th>Hash</th>
              <th>First seen</th>
              <th>Value</th>
              <th>Inputs</th>
              <th>Outputs</th>
              <th>Size</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($transactions as $transaction)
              <tr>
                <th scope="row"><a href="{{ route('transaction', $transaction->hash) }}">{{ substr($transaction->hash, 0, 20) }}..</a></th>
                <td>{{ $transaction->transaction_time }}</td>
                <td>{{ $transaction->value }} LBC</td>
                <td>{{ $transaction->input_count }}</td>
                <td>{{ $transaction->output_count }}</td>
                <td>{{ $transaction->transaction_size }} kB</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

@endsection
