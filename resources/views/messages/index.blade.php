@extends('layouts.app')
@section('realtime-resources', 'messages')
@section('title', 'Messages - ' . config('app.name'))
@section('breadcrumbs')<li class="breadcrumb-item active">Messages</li>@endsection
@section('content')
    @include('messages._workspace', ['conversation' => null])
@endsection
