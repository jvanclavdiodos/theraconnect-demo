@extends('errors.layout')

@section('title', '403 — Forbidden · ' . config('app.name'))

@section('code', '403')
@section('icon', 'bi bi-shield-lock-fill text-warning')
@section('message', 'You don\'t have permission to view this page. If you believe this is a mistake, please contact your administrator.')
