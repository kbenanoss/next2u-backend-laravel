<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Str;
use Vonage\Client;
use Vonage\Client\Credentials\Basic as VonageCredentials;


class AuthController extends Controller
{
//    protected $vonageClient;
//
//    public function __construct()
//    {
//        $credentials = new VonageCredentials(env('VONAGE_API_KEY'), env('VONAGE_API_SECRET'));
//        $this->vonageClient = new Client($credentials);
//    }

    public function register(Request $request)
    {
        try {
            // Validate input data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|string|exists:roles,name',
                'mobile_number' => 'required|string|max:15|unique:users',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Fetch role from the database
            $role = Role::where('name', $request->input('role'))->first();

            // Create a new user with hashed password
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'mobile_number' => $request->input('mobile_number'),
            ]);

            // Attach role to the user
            $user->roles()->attach($role->id);

            // Generate OTP and set expiration time
            $otp = mt_rand(1000, 9999);
            $user->otp = $otp;
            $user->otp_expires_at = Carbon::now()->addMinutes(10);
            $user->save();

            // Send OTP via email
            Mail::to($user->email)->send(new OtpMail($otp));

            // Remove Vonage SMS sending

            return response()->json([
                'user' => $user,
                'message' => 'Registration successful. Please check your email for the OTP.',
            ], 201);

        } catch (\Throwable $th) {
            // Detailed logging for debugging purposes
            Log::error('Registration failed: ', ['error' => $th->getMessage(), 'request' => $request->all()]);

            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }


//    public function register(Request $request)
//    {
//        try {
//            $validator = Validator::make($request->all(), [
//                'name' => 'required|string|max:255',
//                'email' => 'required|string|email|max:255|unique:users',
//                'password' => 'required|string|min:8|confirmed',
//                'role' => 'required|string|exists:roles,name',
//                'mobile_number' => 'required|string|max:15|unique:users',
//            ]);
//
//            if ($validator->fails()) {
//                return response()->json(['errors' => $validator->errors()], 422);
//            }
//
//            $role = Role::where('name', $request->input('role'))->first();
//            $user = User::create([
//                'name' => $request->input('name'),
//                'email' => $request->input('email'),
//                'password' => Hash::make($request->input('password')),
//                'mobile_number' => $request->input('mobile_number'),
//            ]);
//
//            $user->roles()->attach($role->id);
//
//            // Generate OTP
//            $otp = mt_rand(1000, 9999);
//            $user->otp = $otp;
//            $user->otp_expires_at = Carbon::now()->addMinutes(10); // OTP expires in 10 minutes
//            $user->save();
//
//            // Send OTP via email
//            Mail::to($user->email)->send(new OtpMail($otp));
//
//            // Send OTP via SMS using Vonage
//            $basic  = new \Vonage\Client\Credentials\Basic("your_api_key", "your_api_secret");
//            $client = new \Vonage\Client($basic);
//
//            $response = $client->sms()->send(
//                new \Vonage\SMS\Message\SMS($user->mobile_number, 'MyApp', 'Your OTP is: ' . $otp)
//            );
//
//            $message = $response->current();
//
//            if ($message->getStatus() != 0) {
//                throw new \Exception("SMS sending failed: " . $message->getStatus());
//            }
//
//            return response()->json([
//                'user' => $user,
//                'message' => 'Registration successful. Please check your email and SMS for the OTP.',
//            ], 201);
//        } catch (\Throwable $th) {
//            return response()->json([
//                'status' => false,
//                'message' => $th->getMessage(),
//            ], 500);
//        }
//    }


    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'nullable|string|email',
                'phone' => 'nullable|string',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $credentials = $request->only('email', 'phone', 'password');

            $user = User::where(function ($query) use ($credentials) {
                if (isset($credentials['email'])) {
                    $query->where('email', $credentials['email']);
                } elseif (isset($credentials['phone'])) {
                    $query->where('mobile_number', $credentials['phone']);
                }
            })->first();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            $token = $user->createToken('API Token')->plainTextToken;

            return response()->json(['token' => $token, 'status' => true, 'message' => 'User logged in successfully'], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required_without:mobile_number|string|email',
            'mobile_number' => 'required_without:email|string|max:15',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find the user by email or mobile number
        $user = User::where(function($query) use ($request) {
            if ($request->filled('email')) {
                $query->where('email', $request->input('email'));
            }
            if ($request->filled('mobile_number')) {
                $query->orWhere('mobile_number', $request->input('mobile_number'));
            }
        })->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->otp !== $request->input('otp')) {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        if (Carbon::now()->gt($user->otp_expires_at)) {
            return response()->json(['message' => 'Expired OTP'], 400);
        }

        // OTP is correct and valid, clear the OTP fields and issue a token
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        $token = $user->createToken('Personal Access Token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'message' => 'OTP verified successfully',
        ], 200);
    }

//    public function verifyOtp(Request $request)
//    {
//        $validator = Validator::make($request->all(), [
//            'email' => 'required_without:mobile_number|string|email',
//            'mobile_number' => 'required_without:email|string|max:15',
//            'otp' => 'required|string',
//        ]);
//
//        if ($validator->fails()) {
//            return response()->json(['errors' => $validator->errors()], 422);
//        }
//
//        // Find the user by email or mobile number
//        $user = User::where('email', $request->input('email'))
//            ->orWhere('mobile_number', $request->input('mobile_number'))
//            ->first();
//
//        // Check if the user exists, the OTP is correct, and the OTP is not expired
//        if (!$user) {
//            return response()->json(['message' => 'User not found'], 404);
//        }
//
//        if ($user->otp !== $request->input('otp')) {
//            return response()->json(['message' => 'Invalid OTP'], 400);
//        }
//
//        if (Carbon::now()->gt($user->otp_expires_at)) {
//            return response()->json(['message' => 'Expired OTP'], 400);
//        }
//
//        // OTP is correct and valid, so clear the OTP fields and issue a token
//        $user->otp = null;
//        $user->otp_expires_at = null;
//        $user->save();
//
//        $token = $user->createToken('Personal Access Token')->plainTextToken;
//
//        return response()->json([
//            'token' => $token,
//            'message' => 'OTP verified successfully',
//        ], 200);
//    }


    protected function sendSms($phone, $otp)
    {
        $message = "Your OTP is $otp";
        $this->nexmoClient->sms()->send([
            'to' => $phone,
            'from' => env('NEXMO_FROM'),
            'text' => $message
        ]);
    }


    // Fetch all roles
    public function getRoles()
    {
        try {
            // Fetch roles from the database
            $roles = Role::all(); // Adjust this to match your roles table or method

            return response()->json($roles);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch roles'], 500);
        }
    }
    public function profile()
    {
        $userData = auth()->user();
        return response()->json([
            'id' => $userData->id,
            'status' => true,
            'message' => 'Profile Infos',
            'data' => [
                'name' => $userData->name,
                'email' => $userData->email,
                'mobile_number' => $userData->mobile_number, // Include mobile number
            ],
        ], 200);
    }


//    public function logout(Request $request)
//    {
//        $request->user()->currentAccessToken()->delete();
//        return response()->json(['message' => 'Logged out'], 200);
//    }

    public function logout(Request $request)
    {
        $user = auth()->user();
        $user->tokens()->delete(); // Revoke all tokens

        return response()->json(['message' => 'Logged out']);
    }


    public function verifyEmail(EmailVerificationRequest $request)
    {
        $request->fulfill();
        return response()->json(['status' => true, 'message' => 'Email verified successfully!'], 200);
    }

    public function resendVerificationEmail(Request $request)
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['status' => true, 'message' => 'Email already verified.'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['status' => true, 'message' => 'Verification email sent!'], 200);
    }

    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $response = Password::sendResetLink($request->only('email'));

        return $response == Password::RESET_LINK_SENT
            ? response()->json(['status' => true, 'message' => 'Reset link sent!'])
            : response()->json(['status' => false, 'message' => 'Unable to send reset link.'], 500);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|string|email',
            'password' => 'required|string|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $response = Password::reset($request->only('email', 'password', 'token'), function ($user, $password) {
            $user->password = Hash::make($password);
            $user->save();
            event(new PasswordReset($user));
        });

        return $response == Password::PASSWORD_RESET
            ? response()->json(['status' => true, 'message' => 'Password reset successfully!'])
            : response()->json(['status' => false, 'message' => 'Failed to reset password.'], 500);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['status' => false, 'message' => 'Current password is incorrect.'], 400);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return response()->json(['status' => true, 'message' => 'Password changed successfully!'], 200);
    }
}
