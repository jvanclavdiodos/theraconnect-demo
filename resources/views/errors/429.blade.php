@extends('errors.layout')

@section('title', '429 — Too Many Requests · ' . config('app.name'))

@section('code', '429')
@section('icon', 'bi bi-stopwatch text-warning')
@section('message', 'You\'re making requests too quickly. Please wait a moment and try again.')
