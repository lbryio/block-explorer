@extends('minimalUI.blank')

@section('icon', 'pe-7s-culture')
@section('title', 'Accounts')
@section('header', 'Accounts')

@section('content')
<div class="row">
  <div class="col-lg-12">
    <div class="main-card mb-3 card">
      <div class="card-body">
          <div class="table-header d-flex justify-content-between mb-2">
              <div class="card-title">Accounts</div>
              <div class="pagination">
                  {{ $addresses->links() }}
              </div>
          </div>
        <table class="mb-0 table table-hover table-striped">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Address</th>
              <th>Balance (LBC)</th>
              <th>First Seen</th>
              <th>% Max Supply
                <span class="fa fa-question-circle" data-toggle="tooltip" data-placement="top" title="Percentage of LBC max supply (1,083,202,000 LBC)"></span>
              </th>
            </tr>
          </thead>
          <tbody>
            @foreach ($addresses as $address)
              <tr>
                <th scope="row">{{ ($addresses->currentPage() - 1) * 100 + $loop->index + 1 }}</th>
                <td>
                    <a href="{{ route('address', $address->address) }}">{{ $address->address }}</a>
                    @if ($address->isLbryAddress)
                        <span class="lbry-address">
                            <img src="{{ asset('images/logo.svg') }}" height="18px" width="18px" data-toggle="tooltip" title="Address owned by LBRY Inc."/>
                        </span>
                    </span>
                    @endif
                </td>
                <td>{{ number_format($address->balance, 8, '.', ',') }}</td>
                <td>{{ $address->first_seen }}</td>
                <td>{{ $address->percentageSupply }}%</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

@endsection
