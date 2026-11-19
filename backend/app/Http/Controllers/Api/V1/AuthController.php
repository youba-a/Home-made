<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function csrf(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->profileResponse($request->user());
    }

    public function register(Request $request): JsonResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'commune_id' => ['nullable', 'integer', 'exists:communes,id'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed'],
        ], [
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'email.email' => 'Saisis une adresse email valide.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'first_name.required' => 'Indique ton prénom.',
            'last_name.required' => 'Indique ton nom.',
        ]);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'commune_id' => $data['commune_id'] ?? null,
            'email' => mb_strtolower(trim($data['email'])),
            'password' => $data['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->profileResponse($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => mb_strtolower(trim($data['email'])), 'password' => $data['password']])) {
            throw ValidationException::withMessages(['email' => 'Email ou mot de passe incorrect.']);
        }

        $request->session()->regenerate();

        return $this->profileResponse($request->user());
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'commune_id' => ['sometimes', 'nullable', 'integer', 'exists:communes,id'],
        ], [
            'first_name.required' => 'Indique ton prénom.',
            'last_name.required' => 'Indique ton nom.',
            'commune_id.exists' => 'Choisis une commune disponible.',
        ]);

        $user = $request->user();
        $user->update($data);

        return $this->profileResponse($user);
    }

    private function profileResponse(?User $user, int $status = 200): JsonResponse
    {
        $data = $user === null ? null : [
            ...$user->load('commune:id,nom,code_postal')->only([
                'id', 'first_name', 'last_name', 'email', 'commune_id', 'commune',
            ]),
            'display_name' => $user->displayName(),
        ];

        return response()->json(['data' => $data], $status);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => null]);
    }
}
