@extends('errors::minimal')

@section('title', __('Forbidden'))
@section('code', '403')
@section('message', __($exception->getMessage() ?: 'Forbidden'))
@section('jp_message', 'このページへのアクセスは許可されていません。')
@section('jp_message', 'アクセス権限については管理者（社長）へ直接ご相談ください。')
