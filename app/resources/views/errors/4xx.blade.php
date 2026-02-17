@extends('errors::minimal')

@section('title', __('Error'))
@section('code', (string) $exception->getStatusCode())
@section('message', __($exception->getMessage() ?: 'Client Error'))
@section('jp_message', 'リクエスト内容に問題があります。内容をご確認ください。')
