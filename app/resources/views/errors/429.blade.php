@extends('errors::minimal')

@section('title', __('Too Many Requests'))
@section('code', '429')
@section('message', __('Too Many Requests'))
@section('jp_message', 'アクセスが集中しています。時間をおいて再度お試しください。')
