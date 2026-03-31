<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\StudentVisaInfo;
use App\Services\ActivityLogService;
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
        $firmOptions = StudentVisaInfo::FIRM_OPTIONS;
        return view('admin.users.create', compact('roles', 'firmOptions'));
    }

    public function edit(User $user)
    {
        $roles = ProjectRole::staffRoles();
        $firmOptions = StudentVisaInfo::FIRM_OPTIONS;
        return view('admin.users.edit', compact('user', 'roles', 'firmOptions'));
    }

    public function store(Request $request)
    {
        $validRoleValues = array_map(fn ($r) => $r->value, ProjectRole::staffRoles());

        $rules = [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'roles' => 'required|array|min:1',
            'roles.*' => 'in:' . implode(',', $validRoleValues),
        ];

        if (in_array(ProjectRole::FIRM_RESPONSIBLE->value, $request->input('roles', []))) {
            $rules['assigned_firm'] = 'required|string|max:255';
        }

        $request->validate($rules);

        $isFirmRole = in_array(ProjectRole::FIRM_RESPONSIBLE->value, $request->input('roles', []));

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'assigned_firm' => $isFirmRole ? $request->assigned_firm : null,
            'telegram_chat_id' => $isFirmRole ? $request->telegram_chat_id : null,
        ]);

        $user->syncRoles($request->roles);
        ActivityLogService::log('create', 'user', "Yangi foydalanuvchi yaratildi: {$user->name}", $user, null, [
            'roles' => $request->roles,
        ]);

        return redirect()->route('admin.users.index')->with('success', "Foydalanuvchi yaratildi");
    }

    public function update(Request $request, User $user)
    {
        $validRoleValues = array_map(fn ($r) => $r->value, ProjectRole::staffRoles());

        $rules = [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'roles' => 'required|array|min:1',
            'roles.*' => 'in:' . implode(',', $validRoleValues),
        ];

        if (in_array(ProjectRole::FIRM_RESPONSIBLE->value, $request->input('roles', []))) {
            $rules['assigned_firm'] = 'required|string|max:255';
        }

        $request->validate($rules);

        $oldRoles = $user->getRoleNames()->toArray();

        $isFirmRole = in_array(ProjectRole::FIRM_RESPONSIBLE->value, $request->input('roles', []));

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'assigned_firm' => $isFirmRole ? $request->assigned_firm : null,
            'telegram_chat_id' => $isFirmRole ? $request->telegram_chat_id : $user->telegram_chat_id,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => bcrypt($request->password)]);
        }

        $user->syncRoles($request->roles);
        ActivityLogService::log('update', 'user', "Foydalanuvchi yangilandi: {$user->name}", $user, [
            'roles' => $oldRoles,
        ], [
            'roles' => $request->roles,
        ]);

        return redirect()->route('admin.users.index')->with('success', "Foydalanuvchi yangilandi");
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully');
    }
}
