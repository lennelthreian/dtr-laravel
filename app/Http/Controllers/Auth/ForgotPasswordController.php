<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showForm()
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
        ]);

        $user = User::where('username', $request->username)->first();

        if ($user) {
            $existing = PasswordResetRequest::where('user_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if (!$existing) {
                PasswordResetRequest::create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                ]);
            }
        }

        return redirect()->route('password.request.submitted');
    }

    public function submitted()
    {
        return view('auth.forgot-password-submitted');
    }
}
