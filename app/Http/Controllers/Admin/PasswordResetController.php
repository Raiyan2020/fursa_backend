<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Forgot password" for the dashboard, which previously had no recovery path
 * at all — a locked-out admin needed a developer to reset the hash by hand.
 */
class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('dashboard.auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Inactive admins must not be able to recover access.
        $admin = Admin::query()->where('email', $request->input('email'))->first();
        if ($admin && ! $admin->is_active) {
            throw ValidationException::withMessages([
                'email' => __('This account is inactive. Please contact a super admin.'),
            ]);
        }

        $status = $this->broker()->sendResetLink($request->only('email'));

        // Always report success so the form cannot be used to enumerate admins.
        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => __('Please wait before retrying.'),
            ]);
        }

        return back()->with('status', __('If that email belongs to an admin account, a reset link is on its way.'));
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('dashboard.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = $this->broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Admin $admin, string $password) {
                $admin->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($admin));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __('This password reset link is invalid or has expired.'),
            ]);
        }

        return redirect()->route('admin.login')->with('status', __('Your password has been reset. You can sign in now.'));
    }

    protected function broker()
    {
        return Password::broker('admins');
    }
}
