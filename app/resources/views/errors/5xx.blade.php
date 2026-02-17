@extends('errors::minimal')

@section('title', __('Error'))
@section('code', (string) $exception->getStatusCode())
@section('message', __($exception->getMessage() ?: 'Server Error'))
@section('jp_message', 'サーバー側で問題が発生しました。時間をおいて再度お試しください。')
