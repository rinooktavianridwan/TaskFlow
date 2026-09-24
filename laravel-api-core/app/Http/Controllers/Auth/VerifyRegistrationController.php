<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VerifyRegistrationController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function store(Request $request): Response
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp_code' => ['required', 'string', 'size:6'],
        ]);

        $pending = PendingRegistration::where('email', $request->string('email')->toString())->first();

        if (! $pending || $pending->isExpired()) {
            throw ValidationException::withMessages([
                'otp_code' => ['Kode OTP tidak valid atau sudah kedaluwarsa. Silakan daftar ulang.'],
            ]);
        }

        if ($pending->attempts >= 5) {
            $pending->delete();

            throw ValidationException::withMessages([
                'otp_code' => ['Terlalu banyak percobaan gagal. Silakan daftar ulang.'],
            ]);
        }

        if (! Hash::check($request->string('otp_code')->toString(), $pending->otp_code)) {
            $pending->increment('attempts');

            throw ValidationException::withMessages([
                'otp_code' => ['Kode OTP salah.'],
            ]);
        }

        $user = User::create([
            'name' => $pending->name,
            'email' => $pending->email,
            'password' => $pending->password, // sudah hashed dari tahap register
            'email_verified_at' => now(), // langsung aktif, sudah terbukti lewat OTP
        ]);

        $pending->delete();

        Auth::login($user);
        $request->session()->regenerate();

        return response()->noContent();
    }
}
