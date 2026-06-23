@extends('layouts.portal')

@section('title', 'Help Assistant — ' . config('app.name'))

@section('breadcrumbs')
    <li class="breadcrumb-item active">Help Assistant</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h1 class="tc-page-title mb-1">Help Assistant</h1>
        <p class="tc-page-sub">Ask about appointments, assignments, or general clinic info.</p>

        <div class="card shadow-sm">
            <div class="card-body" style="min-height: 200px;">
                @if(session('chat'))
                    @php $chat = session('chat'); @endphp
                    <div class="d-flex justify-content-end mb-2">
                        <div class="p-2 px-3 rounded-3 bg-primary text-white" style="max-width:75%;">{{ $chat['question'] }}</div>
                    </div>
                    <div class="d-flex justify-content-start mb-2">
                        <div class="p-2 px-3 rounded-3 bg-light" style="max-width:75%;">
                            <i class="bi bi-robot me-1 text-secondary"></i>{{ $chat['answer']['reply'] ?? "I'm not sure how to help with that yet." }}
                        </div>
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-robot fs-1"></i>
                        <p class="mt-2 mb-0">Hi! How can I help you today?</p>
                    </div>
                @endif
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('portal.chatbot.message') }}" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="message" class="form-control @error('message') is-invalid @enderror"
                           placeholder="Type your question…" maxlength="1000" required autofocus>
                    <button class="btn btn-primary"><i class="bi bi-send"></i></button>
                </form>
                @error('message')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
@endsection
