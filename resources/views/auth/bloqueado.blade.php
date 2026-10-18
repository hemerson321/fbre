@extends('layouts.app')
@section('titulo','Acesso bloqueado')
@section('conteudo')
<div class="card p-8 max-w-lg mx-auto text-center">
  <div class="text-5xl mb-3">🔒</div>
  <h1 class="text-xl font-bold mb-2">Acesso temporariamente bloqueado</h1>
  <p class="text-slate-600">O contrato desta empresa está vencido ou o acesso foi suspenso. Entre em contato com a GERATEC para regularizar.</p>
  <form method="post" action="{{ route('logout') }}" class="mt-6">@csrf<button class="btn btn-ghost">Sair</button></form>
</div>
@endsection
