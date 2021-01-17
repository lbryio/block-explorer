@extends('minimalUI.blank')

@push('styles')
    <style>
        .position-ref {
            position: relative;
        }
        .code {
            border-right: 2px solid;
            font-size: 26px;
            padding: 0 15px 0 15px;
            text-align: center;
        }
        .message {
            font-size: 18px;
            text-align: center;
        }
        .full-height {
            height: 20vh;
        }
        .flex-center {
            align-items: center;
            display: flex;
            justify-content: center;
        }
    </style>
@endpush

@section('content')
    <div class="app-main__inner">
        <div class="flex-center position-ref full-height">
            <div class="code">
                @yield('code')
            </div>

            <div class="message" style="padding: 10px;">
                @yield('message')
            </div>
        </div>
    </div>
@endsection
