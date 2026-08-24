<?php

namespace App\Http\Controllers;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Support\Branches\CurrentBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()->with('roles:id,name')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'job_title' => $u->job_title,
                'role' => $u->roles->first()?->name,
                'is_active' => (bool) $u->is_active,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'roles' => collect(RoleEnum::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::enum(RoleEnum::class)],
            'password' => ['required', 'confirmed', Password::min(6)],
            'is_active' => ['boolean'],
        ]);

        $user = User::create([
            'branch_id' => CurrentBranch::id(),
            'name' => $data['name'],
            'username' => $data['username'],
            'job_title' => $data['job_title'] ?? null,
            'email' => $data['username'] . '@' . str_replace(' ', '', strtolower(config('app.name'))) . '.local',
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Staff account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::enum(RoleEnum::class)],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'confirmed', Password::min(6)],
        ]);

        $user->fill([
            'name' => $data['name'],
            'job_title' => $data['job_title'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Staff account updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot remove your own account.');
        }

        // Soft-disable rather than delete, to preserve historical references.
        $user->update(['is_active' => false]);

        return back()->with('success', 'Staff account deactivated.');
    }
}
