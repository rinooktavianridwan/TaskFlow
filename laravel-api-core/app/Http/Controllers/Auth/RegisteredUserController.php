<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SendRegistrationOtpJob;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    /**
     * Terima data registrasi, simpan sementara, kirim OTP.
     * Akun BELUM dibuat di tabel users pada tahap ini.
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $otpCode = (string)random_int(100000, 999999);

        $pending = PendingRegistration::updateOrCreate(
            ['email' => $request->string('email')->toString()],
            [
                'name'       => $request->string('name')->toString(),
                'password'   => Hash::make($request->string('password')->toString()),
                'otp_code'   => Hash::make($otpCode),
                'attempts'   => 0,
                'expires_at' => Carbon::now()->addMinutes(10),
            ],
        );

        SendRegistrationOtpJob::dispatch($pending->name, $pending->email, $otpCode);

        return response()->json([
            'message' => 'Kode OTP telah dikirim ke email kamu. Berlaku 10 menit.',
            'email'   => $pending->email,
        ], 202);
    }
}
