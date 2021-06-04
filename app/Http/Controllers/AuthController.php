<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(){
        $this->middleware('auth:api', ['except' => ['login', 'register', 'test']]);
    }//end __construct()

    public function login(Request $request){
        $validator = Validator::make(
            $request->all(),
            [
                'email'    => 'required|email',
                'password' => 'required|string|min:6',
            ]
        );

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $token_validity = (24 * 60);

        $this->guard()->factory()->setTTL($token_validity);

        if (!$token = $this->guard()->attempt($validator->validated())) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        // if (!$token = Auth::guard()->Auth::attempt($validator->validated())) {
        //     return response()->json(['error' => 'Unauthorized'], 401);
        // }

        return $this->respondWithToken($token);

    }//end login()

    public function register(Request $request){
        $validator = Validator::make(
            $request->all(),
            [
                'name'     => 'required|string|between:2,100',
                'email'    => 'required|email|unique:users',
                'password' => 'required|confirmed|min:6',
                'password_confirmation' => 'required'
            ]
        );

        if ($validator->fails()) {
            return response()->json(
                [$validator->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $user = User::create(
            array_merge(
                $validator->validated(),
                ['password' => bcrypt($request->password)]
            )
        );

        return response()->json(['message' => 'User created successfully', 'user' => $user]);

    }//end register()

    public function test(Request $request){
        // $users = DB::table('users')->select('id','name','email')->get();
        $todos = $this->guard()->todos()->get(['id', 'title', 'body', 'completed', 'created_by']);
        return response()->json($todos->toArray());
        // $users = DB::table('users')->select('id','name','email')->get();
        // return response()->json(['message' => 'Users returned', 'users' => $users]);
    }

    public function logout()
    {
        Auth::logout();

        return response()->json(['message' => 'User logged out successfully']);

    }//end logout()


    public function profile()
    {
        return response()->json($this->guard()->user());

    }//end profile()


    public function refresh()
    {
        return $this->respondWithToken($this->guard()->refresh());

    }//end refresh()


    protected function respondWithToken($token){
        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'token_validity' => $this->guard()->factory()->getTTL() * 60,
        ]);
    }

    protected function guard(){
        return Auth::guard();
    }
    
}
