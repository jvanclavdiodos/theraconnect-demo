@extends('errors.layout')

@section('title', '500 — Server Error · ' . config('app.name'))

@section('code', '500')
@section('icon', 'bi bi-exclamation-octagon-fill text-danger')
@section('message', 'Something went wrong on our end. The team has been notified — please try again in a few moments.')
