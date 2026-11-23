@extends('layouts.app')
@section('titulo','Nova passagem')
@push('head')
@include('passagens._form_venda_estilos')
@endpush
@section('conteudo')
@include('passagens._form_venda')
@endsection
@push('scripts')
@include('passagens._form_venda_scripts')
@endpush
