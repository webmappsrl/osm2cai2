<?php

namespace App\Http\Controllers;

use App\Models\CasUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Nova\Nova;

/**
 * Controller with main logic of cas user login/registration
 * used in the route /nova/cas-login with middleware'cai.cas'
 */
class CasLoginController extends Controller
{

    /**
     * Login and/or register cas user
     *
     * @return void
     */
    public function casLogin()
    {
        /**
         * Get user attributes from cas
         */
        $userAttributes = cas()->getAttributes();

        /**
         * Create an email validator
         */
        $userEmail = $userAttributes['uid'];
        $validator = Validator::make(['email' => $userEmail], [
            'email' => 'required|email'
        ]);
        $userEmail = Str::lower($userEmail);

        /**
         * Check if mail is valid
         */
        if ($validator->fails()) {
            return redirect()->intended(Nova::path())->withErrors("Your username must be a valid email to use this function");
        }

        /**
         * Prepare CasUser model fillable attributes
         */
        $casUserFill = [
            'uid' => $userEmail
        ];

        /**
         * Already exists a CasUser with these attributes?
         */
        $casUser = CasUser::where($casUserFill);
        if ($casUser->exists()) { // yes, get it
            $casUser = $casUser->first();
            //get the User
            $user = $casUser->user;
        } // yes, get it
        else { // no, create a new CasUser and maybe an User

            /**
             * Already exists an User with this email?
             */
            $userQuery = User::where('email', $userEmail);
            $userExists = $userQuery->exists();
            if ($userExists) { //yes, get it
                $user = $userQuery->first();
            } else { //no, create a new User
                $newUserFill = [
                    'email' => $userEmail,
                    'name' => $userAttributes['firstname'] . ' ' . $userAttributes['lastname'],
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(100)) //generate a random password with 100 characters
                ];
                $user = User::firstOrCreate($newUserFill);
            }

            //create a new CasUser
            $casUserFill = array_merge($casUserFill, [
                'user_uuid' => $userAttributes['userUuid'],
                'cas_id' => $userAttributes['id'],
                'firstname' => $userAttributes['firstname'],
                'lastname' => $userAttributes['lastname']
            ]);
            $casUser = new CasUser($casUserFill);
            //associate User to CasUser (belongsTo)
            $casUser->user()->associate($user);
        } // no, create a new one

        //update CasUser roles on each login
        $casUser->roles = $userAttributes['roles'];
        $casUser->save();

        //inject User on current session, login it
        $this->guard()->login($user);

        //go to nova dashboard
        return redirect()->intended(Nova::path());
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard(config('nova.guard'));
    }
}
