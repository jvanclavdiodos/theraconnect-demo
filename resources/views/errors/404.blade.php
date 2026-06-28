@extends('errors.layout')

@section('title', '404 — Not Found · ' . config('app.name'))

@section('code', '404')
@section('icon', 'bi bi-compass text-primary')
@section('message', 'The page you\'re looking for doesn\'t exist or may have been moved.')
