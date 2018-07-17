<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


/** 
 * Return user which is signed in with token
 */
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

/**
 * Auth the user
 */
Route::post('/sign-in', 'AuthController@signIn');

/**
 * Post new collection
 */
Route::post('/containers', function(Request $request) {

    $title = $request->input('title');
    $data  = $request->input('data');
    $ref = md5(microtime());
    $uid = 1; // for test

    $result = DB::table('containers')->insert([
        'title' => $title,
        'data' => $data, 
        'ref' => $ref,
        'user_id' => $uid
    ]);

    if($result) {
        return response()->json(['success' => true, 'ref' => $ref]);
    }

    return response()->json((['success' => false]));
});

/**
 * Returns all collections
 */
Route::get('/containers', function (Request $request) {

    $collections = DB::select("SELECT * FROM containers");

    return response()->json($collections);
});

/**
 * Return one collection by reference
 */
Route::get('/containers/{ref}', function (Request $request, $ref) {

    $collection = DB::selectOne("SELECT * FROM containers WHERE ref = ?", [$ref]);

    if($collection == null) {
        return response()->json([]);
    }

    return response()->json( $collection );
});

/**
 * Update container
 */
Route::post('/containers/{ref}/update', function (Request $request, $ref) {
    DB::table('containers')->where('ref', '=', $ref)->update($request->all());

    return response()->json([]);
});

