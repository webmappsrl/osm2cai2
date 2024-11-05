<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /** * Signup and get a JWT 
     *
     *  @param Request $request 
     *  @return JsonResponse 
     */
    public function signup(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|email|unique:users,email',
                'password' => 'required',
                'name' => 'required|string|max:255',
            ],
            [
                'email.email' => 'Il campo email deve essere un indirizzo email valido.',
                'email.unique' => 'Un utente è già stato registrato con questa email.',
                'email.required' => 'Il campo email è obbligatorio.',
                'password.required' => 'Il campo password è obbligatorio.',
                'name.required' => 'Il campo nome è obbligatorio.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
                'code' => 400
            ], 400);
        }

        $credentials = $request->only(['email', 'password', 'name']);


        try {
            $user = $this->createUser($credentials);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'code' => 400
            ], 400);
        }

        if ($token = auth('api')->attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            return $this->loginResponse($token);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|email',
                'password' => 'required|min:6',
            ],
            [
                'email.required' => 'Il campo email é obbligatorio.',
                'email.email' => 'Il campo email deve essere un indirizzo email valido.',
                'password.required' => 'Il campo password è obbligatorio.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
                'code' => 401
            ], 401);
        }

        $credentials = $request->only(['email', 'password']);


        //check if email exists
        $user = User::where('email', $credentials['email'])->first();
        if (!$user) {
            return response()->json([
                'error' => 'L\'email inserita non è corretta. Per favore, riprova.',
                'code' => 401
            ], 401);
        }


        // Check if password is correct
        if (!auth('api')->attempt($credentials)) {
            return response()->json([
                'error' => 'La password inserita non è corretta. Per favore, riprova.',
                'code' => 401
            ], 401);
        }

        if (($request->input('referrer') != null) && ($request->input('referrer') != $user->referrer)) {
            $user->referrer = $request->input('referrer');
            $user->save();
        }

        $token = auth('api')->attempt($credentials);

        return $this->loginResponse($token);
    }


    /**
     * Delete the authenticated user.
     *
     * This function deletes the user that is currently authenticated via the API.
     *
     * @return JsonResponse The JSON response containing a success message if the user was deleted successfully,
     *                      or an error message and code if the deletion failed.
     */
    public function delete(): JsonResponse
    {
        try {
            // Get the authenticated user from the API
            $user = auth('api')->user();

            // Logout the user from the API and delete the user
            auth('api')->logout();
            $user->delete();
        } catch (Exception $e) {
            // If an exception occurs, return a JSON response with the error message and code
            return response()->json([
                'error' => $e->getMessage(),
                'code' => 400
            ], 400);
        }

        // If the user was deleted successfully, return a JSON response with a success message
        return response()->json(['success' => 'Account utente cancellato con successo.']);
    }

    /**
     * Get the authenticated User.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Utente non autenticato.'], 401);
        }

        $result = $user->toArray();

        unset($result['referrer'], $result['password']);

        return response()->json($result);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'Logout effettuato con successo.']);
    }

    /**
     * Refresh a token.
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        try {
            return $this->respondWithToken(auth('api')->refresh());
        } catch (Exception $e) {
            return response()->json(['error' => 'Impossibile aggiornare il token.'], 401);
        }
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     * @return JsonResponse
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }

    /**
     * Generate the login response with user data and token.
     *
     * @param string $token
     * @return JsonResponse
     */
    protected function loginResponse(string $token): JsonResponse
    {
        $tokenArray = $this->respondWithToken($token);
        return response()->json(array_merge($this->me()->getData(true), $tokenArray->getData(true)));
    }

    /**
     * Create a new user and handle partnerships
     *
     * @param array $data
     * @return User
     * @throws Exception
     */
    private function createUser(array $data): User
    {
        $user = new User();
        $user->fill([
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'name' => $data['name'],
            'email_verified_at' => now(),
        ]);

        try {
            $user->save();
        } catch (Exception $e) {
            throw new Exception('Errore durante il salvataggio dell\'utente. Per favore, riprova.');
        }

        return $user;
    }
}
