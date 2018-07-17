<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

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
     * Store new user, returns user info and token to sign in
     */
    public function signUp(Request $request) 
    {
        $validator = Validator::make($request->all(), [
            'email' => 'min:3|email|required|unique:users',
            'name' => 'min:3',
            'password' => 'min:3'
        ]);

        if($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Invalid inputs',
                'failed' => $validator->failed()
            ];
        }

        $user = User::create([
            'email' => $request->input('email'),
            'name' => $request->input('name', 'User'),
            'password' => Hash::make($request->input('password'))
        ]);

        // create access token
        $token = User::find($user->id)->createToken('Shiplet')->accessToken;

        return [
            'user' => $user, 
            'token' => $token,
            'message' => 'Your account has been created!',
            'success' => true
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
