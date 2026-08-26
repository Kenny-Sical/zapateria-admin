@extends('layouts.app')

@section('title', 'Dashboard - Lizz Glamour')
@section('header_title', 'Visión General')

@section('content')
    <div class="card">
        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">Bienvenido al Dashboard</h2>
        <p style="color: var(--text-secondary); font-size: 0.95rem;">
            Aquí podrás visualizar los accesos rápidos y estadísticas clave del negocio. <br>
            Usa el menú lateral para navegar hacia el módulo de <strong>Inventario</strong> y otros apartados.
        </p>
    </div>
@endsection
