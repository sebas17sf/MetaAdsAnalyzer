<?php

namespace App\Http\Controllers\Login;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $apiKey = $request->header('API_KEY');
        $expectedApiKey = env('API_KEY');
        if ($apiKey !== $expectedApiKey) {
            return response()->json(['error' => 'API Key inválida.'], 401);
        }

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('Email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->PasswordHash)) {
            return response()->json(['error' => 'Credenciales inválidas.'], 401);
        }

        if (!$user->IsActive) {
            return response()->json(['error' => 'La cuenta está desactivada.'], 403);
        }

        if (!$user->EmailConfirmed) {
            return response()->json(['error' => 'El correo electrónico no está confirmado.'], 403);
        }

        // Genera y guarda el token
        $token = Str::random(60);
        $user->ApiToken = $token;
        $user->save();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->Id,
                'email' => $user->Email,
                'username' => $user->Username,
                'role' => $user->Role,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $apiKey = $request->header('API_KEY');
        $expectedApiKey = env('API_KEY');
        if ($apiKey !== $expectedApiKey) {
            return response()->json(['error' => 'API Key inválida.'], 401);
        }

        $token = $request->input('Authorization');
        if (!$token || !Str::startsWith($token, 'Bearer ')) {
            return response()->json(['error' => 'Token de acceso no proporcionado.'], 401);
        }

        $token = Str::after($token, 'Bearer ');
        $user = User::where('ApiToken', $token)->first();

        if (!$user) {
            return response()->json(['error' => 'Token de acceso inválido.'], 401);
        }

        $user->ApiToken = null;
        $user->LastLogin = now();
        $user->save();

        return response()->json(['message' => 'Cierre de sesión exitoso.']);
    }


}
