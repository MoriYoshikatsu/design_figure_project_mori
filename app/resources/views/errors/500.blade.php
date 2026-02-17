@extends('errors::minimal')

@section('title', __('Server Error'))
@section('code', '500')
@section('message', __('Server Error'))
@section('jp_message', 'サーバー内部で問題が発生しました。時間をおいて再度お試しください。')
