@extends('layouts.portal')

@section('title', 'Joy — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Joy</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center gap-2 mb-1">
            <img src="{{ asset('img/joy-avatar.svg') }}" alt="Joy" width="36" height="36" style="border-radius:10px;">
            <h1 class="tc-page-title mb-0">Joy</h1>
        </div>
        <p class="tc-page-sub">Your TheraConnect assistant — ask about appointments, assignments, or general clinic info.</p>

        <div class="card shadow-sm">
            <div class="card-body" style="min-height: 200px;">
                @if(session('chat'))
                    @php $chat = session('chat'); @endphp
                    <div class="d-flex justify-content-end mb-2">
                        <div class="p-2 px-3 rounded-3 bg-primary text-white" style="max-width:75%;">{{ $chat['question'] }}</div>
                    </div>
                    <div class="d-flex justify-content-start align-items-end gap-2 mb-2">
                        <img src="{{ asset('img/joy-avatar.svg') }}" alt="Joy" width="28" height="28" style="border-radius:8px;flex:0 0 auto;">
                        <div class="p-2 px-3 rounded-3 bg-light" style="max-width:75%;">
                            {{ $chat['answer']['reply'] ?? "I'm not sure how to help with that yet." }}
                        </div>
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <img src="{{ asset('img/joy-avatar.svg') }}" alt="Joy" width="56" height="56" style="border-radius:16px;">
                        <p class="mt-3 mb-0 fw-semibold text-body">Hi, I'm Joy!</p>
                        <p class="mb-0">Your TheraConnect assistant. How can I help you today?</p>
                    </div>
                @endif
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('portal.chatbot.message') }}" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="message" class="form-control @error('message') is-invalid @enderror"
                           placeholder="Message Joy…" maxlength="1000" required autofocus>
                    <button class="btn btn-primary"><i class="bi bi-send"></i></button>
                </form>
                @error('message')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
@endsection
