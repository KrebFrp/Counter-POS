<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\User;
use App\Services\SaveImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        return view('staff.index', ['staff' => User::latest('id')->paginate(12)]);
    }

    public function create(): View
    {
        return view('staff.form', ['user' => new User]);
    }

    public function store(StoreStaffRequest $request, SaveImage $images): RedirectResponse
    {
        $user = $images->handle($request->file('avatar'), 'avatars', true, fn ($path) => User::create(
            $request->safe()->only('username', 'full_name', 'role') + ['password' => Hash::make($request->validated('password')), 'avatar' => $path]
        ));

        return redirect()->route('staff.show', $user)->with('success', 'Staff account created successfully.');
    }

    public function show(User $user): View
    {
        return view('staff.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('staff.form', compact('user'));
    }

    public function update(UpdateStaffRequest $request, User $user, SaveImage $images): RedirectResponse
    {
        if ($request->user()->id === $user->id && $request->validated('role', $user->role) !== $user->role) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role. Ask another administrator.']);
        }

        $images->handle($request->file('avatar'), 'avatars', true, function ($path) use ($request, $user, $images) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $locked->fill($request->safe()->only('username', 'full_name', 'role'));
            if ($request->filled('password')) {
                $locked->password = Hash::make($request->validated('password'));
            }
            if ($path) {
                $images->removeAfterCommit($locked->avatar);
                $locked->avatar = $path;
            }
            $locked->save();

            return $locked;
        });

        // Keep this session valid when its owner changes their own password.
        if ($request->user()->id === $user->id && $request->filled('password')) {
            Auth::setUser($user->fresh());
            $request->session()->put('password_hash_web', Auth::user()->password);
        }

        return redirect()->route('staff.show', $user)->with('success', 'Staff account updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            throw ValidationException::withMessages(['account' => 'You cannot remove your own staff account.']);
        }

        DB::transaction(fn () => User::query()->lockForUpdate()->findOrFail($user->id)->delete(), 3);

        return redirect()->route('staff.index')->with('success', 'Staff account removed. Access has been revoked.');
    }
}
