<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    public function getUser(Request $request)
    {
        $apiKey = $request->header('API_KEY');
        $expectedApiKey = env('API_KEY');
        if ($apiKey !== $expectedApiKey) {
            return response()->json(['error' => 'API Key inválida.'], 401);
        }

        $users = User::select([
            'Username',
            'Email',
            'Role',
            'CreatedAt',
            'LastLogin',
            'IsActive',
            'Id',
        ])->get();

        return response()->json(['users' => $users]);
    }

    public function createUser(Request $request)
    {
        $apiKey = $request->header('API_KEY');
        $expectedApiKey = env('API_KEY');
        if ($apiKey !== $expectedApiKey) {
            return response()->json(['error' => 'API Key inválida.'], 401);
        }

        $email = $request->input('Email');
        if (User::where('Email', $email)->exists()) {
            return response()->json(['error' => 'Correo ya usado.'], 409);
        }

        DB::beginTransaction();
        try {
            $data = $request->only(['Username', 'Email', 'Password', 'Role', 'IsActive']);
            $data['PasswordHash'] = Hash::make($data['Password']);
            $data['ApiToken'] = null;
            $data['Role'] = $request->input('Role', 'Usuario');
            $data['CreatedAt'] = now();
            $data['IsActive'] = $request->input('IsActive', true);

            $user = User::create($data);

            DB::commit();
            return response()->json(['user' => $user], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear el usuario.', 'message' => $e->getMessage()], 500);
        }
    }

    public function editUser(Request $request, $id)
    {
        $apiKey = $request->header('API_KEY');
        $expectedApiKey = env('API_KEY');
        if ($apiKey !== $expectedApiKey) {
            return response()->json(['error' => 'API Key inválida.'], 401);
        }
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado.'], 404);
        }

        DB::beginTransaction();
        try {
            $data = $request->only(['Username', 'Email', 'Password', 'Role', 'IsActive']);
            if (isset($data['Password'])) {
                $data['PasswordHash'] = Hash::make($data['Password']);
                unset($data['Password']);
            }
            $user->update($data);
            DB::commit();
            return response()->json(['user' => $user]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al editar el usuario.', 'message' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $request, $id)
    {
        $apiKey = $request->header('API_KEY');
        $expectedApiKey = env('API_KEY');
        if ($apiKey !== $expectedApiKey) {
            return response()->json(['error' => 'API Key inválida.'], 401);
        }
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado.'], 404);
        }

        DB::beginTransaction();
        try {
            $user->delete();
            DB::commit();
            return response()->json(['message' => 'Usuario eliminado.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al eliminar el usuario.', 'message' => $e->getMessage()], 500);
        }

    }

}
