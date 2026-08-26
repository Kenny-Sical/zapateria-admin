@extends('layouts.app')

@section('title', 'Editar Usuario - Lizz Glamour')
@section('header_title', 'Editar Usuario')

@section('content')
<style>
    .form-card {
        max-width: 600px;
        margin: 0 auto;
        padding: 2rem;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text-secondary);
    }
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-family: inherit;
        font-size: 1rem;
        transition: all 0.2s ease;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-soft);
    }
    .btn-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-primary {
        background-color: var(--primary-color);
        color: #fff;
    }
    .btn-primary:hover {
        background-color: var(--primary-hover);
    }
    .btn-secondary {
        background-color: #f3f4f6;
        color: #4b5563;
    }
    .btn-secondary:hover {
        background-color: #e5e7eb;
    }
    .text-danger {
        color: #D64545;
        font-size: 0.875rem;
        margin-top: 0.25rem;
        display: block;
    }
</style>

<div class="card form-card">
    <h3 style="margin-bottom: 1.5rem; color: var(--text-primary); font-weight: 600;">
        <i class='bx bx-edit' style="color: var(--primary-color); vertical-align: middle; font-size: 1.5rem; margin-right: 0.5rem;"></i>
        Editar Usuario: {{ $user->name }}
    </h3>

    <form action="{{ route('users.update', $user->id) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="form-group">
            <label for="name">Nombre Completo <span style="color: #D64545;">*</span></label>
            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
            @error('name')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        <div class="form-group">
            <label for="email">Correo Electrónico <span style="color: #D64545;">*</span></label>
            <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
            @error('email')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        <div class="form-group">
            <label for="password">Contraseña (opcional)</label>
            <input type="password" id="password" name="password" class="form-control" minlength="8">
            <small style="color: var(--text-secondary);">Dejar en blanco para mantener la contraseña actual.</small>
            @error('password')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        <div class="form-group">
            <label for="role">Rol de Usuario <span style="color: #D64545;">*</span></label>
            <select id="role" name="role" class="form-control" required @if($user->id === auth()->id()) disabled title="No puedes cambiar tu propio rol" @endif>
                <option value="1" {{ old('role', $user->role) == 1 ? 'selected' : '' }}>Editor (Solo Inventario)</option>
                <option value="0" {{ old('role', $user->role) == 0 ? 'selected' : '' }}>SuperAdmin (Acceso Total)</option>
            </select>
            @if($user->id === auth()->id())
                <input type="hidden" name="role" value="{{ $user->role }}">
            @endif
            @error('role')<span class="text-danger">{{ $message }}</span>@enderror
        </div>

        <div class="btn-actions">
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
        </div>
    </form>
</div>
@endsection
