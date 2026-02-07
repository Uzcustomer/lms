<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $roles = ProjectRole::staffRoles();
        return view('admin.users.create', compact('roles'));
    }

    public function edit(User $user)
    {
        $roles = ProjectRole::staffRoles();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function store(Request $request)
    {
        $validRoleValues = array_map(fn ($r) => $r->value, ProjectRole::staffRoles());

        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'roles' => 'required|array|min:1',
            'roles.*' => 'in:' . implode(',', $validRoleValues),
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        $user->syncRoles($request->roles);

        return redirect()->route('admin.users.index')->with('success', "Foydalanuvchi yaratildi");
    }

    public function update(Request $request, User $user)
    {
        $validRoleValues = array_map(fn ($r) => $r->value, ProjectRole::staffRoles());

        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'roles' => 'required|array|min:1',
            'roles.*' => 'in:' . implode(',', $validRoleValues),
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => bcrypt($request->password)]);
        }

        $user->syncRoles($request->roles);

        return redirect()->route('admin.users.index')->with('success', "Foydalanuvchi yangilandi");
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully');
    }
}
