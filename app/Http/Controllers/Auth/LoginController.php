<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class LoginController extends Controller
{
    /**
     * Muestra la vista de login.
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Procesa la solicitud de inicio de sesión compatible con WordPress.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $input = $request->input('email');

        // Buscar por email o username (user_login)
        $user = User::where('user_email', $input)
            ->orWhere('user_login', $input)
            ->first();

        // Verificar contraseña con el verificador de WordPress
        if ($user && User::verifyPassword($request->password, $user->user_pass)) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            
            // Redirigir según rol
            if ($user->role === 0) {
                return redirect()->route('dashboard');
            } else {
                return redirect()->route('inventory.index');
            }
        }

        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    /**
     * Cierra la sesión del usuario.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('login');
    }
}
