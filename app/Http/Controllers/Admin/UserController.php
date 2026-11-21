<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role'])
            ->orderBy('name')
            ->paginate(25);

        return view('admin.users.index', ['users' => $users]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403);

        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
        ]);

        $user->forceFill(['role' => $data['role']])->save();

        return back()->with('status', 'The account role has been updated.');
    }
}
