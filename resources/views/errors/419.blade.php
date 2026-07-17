@extends('errors.layout')

@section('title', '419 — Page Expired · ' . config('app.name'))

@section('code', '419')
@section('icon', 'bi bi-clock-history text-warning')
@section('message', 'Your session has expired. Return to a fresh page and try again.')
