<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('display_name', 'like', '%' . $search . '%')
                  ->orWhere('user_login', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('email')) {
            $query->where('user_email', 'like', '%' . $request->email . '%');
        }

        $users = $query->orderBy('ID', 'desc')->get();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:100|unique:wp_users,user_email',
            'password' => 'required|string|min:8',
            'role' => 'required|integer|in:0,1',
        ]);

        $baseLogin = Str::slug($request->name, '');
        if (empty($baseLogin)) {
            $baseLogin = explode('@', $request->email)[0];
        }

        $login = $baseLogin;
        $i = 1;
        while (User::where('user_login', $login)->exists()) {
            $login = $baseLogin . $i++;
        }

        $user = User::create([
            'user_login' => $login,
            'user_pass' => User::hashPassword($request->password),
            'user_nicename' => $login,
            'user_email' => $request->email,
            'user_url' => '',
            'user_registered' => now(),
            'user_activation_key' => '',
            'user_status' => 0,
            'display_name' => $request->name,
        ]);

        $user->setRole($request->role);

        // Guardar metadatos estándar de WordPress
        DB::table('wp_usermeta')->insert([
            ['user_id' => $user->ID, 'meta_key' => 'nickname', 'meta_value' => $login],
            ['user_id' => $user->ID, 'meta_key' => 'first_name', 'meta_value' => $request->name],
            ['user_id' => $user->ID, 'meta_key' => 'last_name', 'meta_value' => ''],
            ['user_id' => $user->ID, 'meta_key' => 'rich_editing', 'meta_value' => 'true'],
            ['user_id' => $user->ID, 'meta_key' => 'admin_color', 'meta_value' => 'modern'],
        ]);

        return redirect()->route('users.index')->with('success', 'Usuario creado exitosamente.');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:100|unique:wp_users,user_email,' . $user->ID . ',ID',
            'role' => 'required|integer|in:0,1',
        ]);

        $data = [
            'display_name' => $request->name,
            'user_email' => $request->email,
        ];

        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:8']);
            $data['user_pass'] = User::hashPassword($request->password);
        }

        $user->update($data);
        $user->setRole($request->role);

        return redirect()->route('users.index')->with('success', 'Usuario actualizado exitosamente.');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        if ($user->ID === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'No puedes eliminar tu propio usuario.');
        }

        DB::table('wp_usermeta')->where('user_id', $user->ID)->delete();
        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado exitosamente.');
    }
}
