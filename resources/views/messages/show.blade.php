@extends('layouts.app')
@section('realtime-resources', 'messages')
@section('realtime-conversation', $conversation->id)
@section('title', 'Conversation - ' . config('app.name'))
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('messages.index') }}">Messages</a></li>
    <li class="breadcrumb-item active">{{ $conversation->patient->user->name }}</li>
@endsection
@section('content')
    @include('messages._workspace')
@endsection
