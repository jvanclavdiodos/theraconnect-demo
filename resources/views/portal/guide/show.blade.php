@extends('layouts.portal')
@section('title', 'User Guide')
@section('content')
@php($actionRoutes = ['appointments' => route('portal.appointments.index'), 'messages' => route('portal.messages.index'), 'assignments' => route('portal.assignments.index'), 'progress' => route('portal.assessments.index'), 'profile' => route('portal.profile.show')])
<div class="mb-4"><h1 class="h3">User Guide</h1><p class="text-body-secondary">Quick answers for using your patient portal.</p></div>
@include('guide._sections')
@endsection
