<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Sign in user, return token and user info
     *
     * @param Request $request
     * @return void
     */
    public function signIn(Request $request)
    {
        // validate request
        $validator = Validator::make($request->all(), [
            'email' => 'required|min:3|email',
            'password' => 'required'
        ]);

        if($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Invalid inputs'
            ];
        }

        $email = $request->input('email');
        $password = $request->input('password');

        if(Auth::attempt(['email' => $email, 'password' => $password]))
        {
            // create token
            $token = Auth::user()->createToken('Shiplet')->accessToken;

            return [
                'success' => true,
                'message' => 'You are signed in!',
                'user' => Auth::user(),
                'token' => $token
            ];
        }

        return [
            'success' => false, 
            'message' => 'Could not sign in!',
            'user' => null, 
            'token' => null
        ];
    }

    /**
     * Sign out the user
     *
     * @return void
     */
    public function signOut(Request $request)
    {
        $request->user()->token()->revoke();

        Auth::guard('api')->logout();

        $request->session()->flush();
        $request->session()->regenerate();

        return [
            'success' => true,
            'message' => 'You are signed out!'
        ];
    }
}
