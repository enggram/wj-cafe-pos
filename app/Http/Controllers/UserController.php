<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::orderBy('name')->get()->map(fn ($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
                'role'  => $u->role->value,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(6)],
            'role'     => ['required', Rule::enum(UserRole::class)],
        ]);

        User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        return redirect()->back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role'     => ['required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'confirmed', Password::min(6)],
        ]);

        $user->name  = $validated['name'];
        $user->email = $validated['email'];
        $user->role  = $validated['role'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        return redirect()->back()->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'User deleted.');
    }
}
