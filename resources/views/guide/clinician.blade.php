@extends('layouts.app')
@section('title', 'User Guide')
@section('content')
@php($actionRoutes = ['appointments' => route('appointments.index'), 'patients' => route('patients.index'), 'assignments' => route('assignments.index'), 'messages' => route('messages.index'), 'dashboard' => route('dashboard')])
<div class="mb-4"><h1 class="tc-page-title">Clinician User Guide</h1><p class="tc-page-sub">Step-by-step instructions for everyday care coordination workflows.</p></div>
@include('guide._sections')
@endsection
