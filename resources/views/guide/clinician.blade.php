@extends('layouts.app')
@section('title', 'User Guide')
@section('content')
@php($actionRoutes = ['appointments' => route('appointments.index'), 'patients' => route('patients.index'), 'assignments' => route('assignments.index'), 'messages' => route('messages.index')])
<div class="mb-4"><h1 class="h3">Clinician User Guide</h1><p class="text-body-secondary">Quick reference for everyday TheraConnect workflows.</p></div>
@include('guide._sections')
@endsection
