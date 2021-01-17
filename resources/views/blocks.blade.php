@extends('minimalUI.blank')

@section('icon', 'pe-7s-box2')
@section('title', 'Blocks')
@section('header', 'Blocks')

@section('content')
<div class="row">
  <div class="col-lg-12">
    <div class="main-card mb-3 card">
      <div class="card-body">
          <div class="table-header d-flex justify-content-between mb-2">
              <div class="card-title">Mined Blocks</div>
              <div class="pagination">
                  {{ $blocks->links() }}
              </div>
          </div>
        <table class="mb-0 table table-hover table-striped">
          <thead>
            <tr>
              <th>Height</th>
              <th>Age</th>
              <th>Transactions</th>
              <th>Size</th>
              <th>Difficulty
                <span class="fa fa-question-circle" data-toggle="tooltip" data-placement="top" title="Percentages indicate changes in difficulty from the previous block"></span>
              </th>
              <th>Nonce</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($blocks as $block)
              <tr>
                <th scope="row"><a href="{{ route('block', $block->height) }}">{{ $block->height }}</a></th>
                <td>{{ $block->block_time }}</td>
                <td>{{ $block->transactions }} txs</td>
                <td>{{ $block->block_size }} kB</td>
                <td>
                  @if ($block->difficulty_diff_percent > 0)
                    [<i class="fa fa-caret-up icon-gradient bg-success"></i> +{{ $block->difficulty_diff_percent }}%]
                  @elseif ($block->difficulty_diff_percent < 0)
                    [<i class="fa fa-caret-down icon-gradient bg-danger"></i> {{ $block->difficulty_diff_percent }}%]
                  @endif
                  {{ number_format($block->difficulty) }}
                </td>
                <td>{{ $block->nonce }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

@endsection
