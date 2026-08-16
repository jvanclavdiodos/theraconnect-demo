@extends('layouts.portal')
@section('title', 'User Guide')
@section('content')
@php($actionRoutes = ['appointments' => route('portal.appointments.index'), 'messages' => route('portal.messages.index'), 'assignments' => route('portal.assignments.index'), 'progress' => route('portal.assessments.index'), 'notifications' => route('portal.notifications.index'), 'profile' => route('portal.profile.show')])
<div class="mb-4"><h1 class="tc-page-title">Patient User Guide</h1><p class="tc-page-sub">Step-by-step instructions for appointments, assignments, messages, progress, and account tasks.</p></div>
@include('guide._sections')
@endsection
