<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ImageStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function index()
    {
        return view('staff.index', ['title' => 'Staff', 'records' => User::orderBy('id')->paginate(10)]);
    }

    public function create()
    {
        return view('staff.form', ['title' => 'Add staff', 'record' => []]);
    }

    public function edit(User $staff)
    {
        return view('staff.form', ['title' => 'Edit staff', 'record' => $staff]);
    }

    public function store(Request $request, ImageStorage $images)
    {
        return $this->save($request, $images);
    }

    public function update(Request $request, User $staff, ImageStorage $images)
    {
        return $this->save($request, $images, $staff);
    }

    private function save(Request $request, ImageStorage $images, ?User $staff = null)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/D', function ($attribute, $value, $fail) use ($staff) {
                $query = User::whereRaw('LOWER(username) = ?', [strtolower($value)]);
                if ($staff) {
                    $query->whereKeyNot($staff->id);
                }
                if ($query->exists()) {
                    $fail('That username is already in use.');
                }
            }],
            'full_name' => ['required', 'string', 'max:100'],
            'password' => [$staff ? 'nullable' : 'required', 'string', 'min:8', 'max:72', 'confirmed'],
            'avatar' => ['nullable', 'file', 'max:2048'],
        ]);
        if (strlen($data['password'] ?? '') > 72) {
            throw ValidationException::withMessages(['password' => 'Use a password up to 72 bytes.']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        } // User's hashed cast handles new passwords.
        unset($data['avatar']);
        try {
            $avatar = $images->save($request->file('avatar'), true);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['avatar' => $e->getMessage()]);
        }
        $old = $staff?->avatar;
        if ($avatar) {
            $data['avatar'] = $avatar;
        }
        try {
            if ($staff) {
                $staff->update($data);
            } else {
                User::create($data);
            }
        } catch (\Throwable $e) {
            if ($avatar) {
                $images->delete($avatar);
            } throw $e;
        }
        if ($avatar && $old) {
            $images->delete($old);
        }

        return redirect()->route('staff.index')->with('success', $staff ? 'Staff account updated.' : 'Staff account added.');
    }

    public function destroy(Request $request, User $staff, ImageStorage $images)
    {
        if ($request->user()->is($staff)) {
            return back()->with('error', 'You cannot delete the account you are signed in with.');
        }
        if ($staff->sales()->exists()) {
            return back()->with('error', 'This staff account has recorded sales and cannot be deleted.');
        }
        $avatar = $staff->avatar;
        $staff->delete();
        $images->delete($avatar);

        return redirect()->route('staff.index')->with('success', 'Staff account deleted.');
    }
}
