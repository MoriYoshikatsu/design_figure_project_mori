@extends('errors::minimal')

@section('title', __('Service Unavailable'))
@section('code', '503')
@section('message', __('Service Unavailable'))
@section('jp_message', '現在はサービスを利用できません。しばらくしてから再度お試しください。')
