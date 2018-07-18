<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * TODO: 
 * -> seperate logic to controllers
 * -> 
 */

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
 * Create user
 */
Route::post('/sign-up', 'AuthController@signUp');

/**
 * Post new container
 */
Route::middleware('auth:api')->post('/containers', function(Request $request) {

    $title = $request->input('title');
    $data  = $request->input('data');
    $ref = md5(microtime());
    $uid = $request->user()->id;

    $result = DB::table('containers')->insert([
        'title' => $title,
        'data' => "[]", 
        'ref' => $ref,
        'user_id' => $uid
    ]);

    if($result) {
        return response()->json(['success' => true, 'ref' => $ref]);
    }

    return response()->json((['success' => false]));
});

/**
 * Returns all containers
 */
/*
Route::get('/containers', function (Request $request) {

    $containers= DB::select("SELECT * FROM containers");

    return response()->json($containers);
});
*/

/**
 * Return users containers
 */
Route::middleware('auth:api')->get('/user/containers', function (Request $request) {
    $containers = DB::table('containers')->where('user_id', $request->user()->id)->get();

    return response()->json($containers);
});

/**
 * Return one container by reference
 */
Route::get('/containers/{ref}', function (Request $request, $ref) {

    $container = DB::selectOne("SELECT * FROM containers WHERE ref = ?", [$ref]);

    if($container == null) {
        return response()->json([]);
    }

    return response()->json( $container );
});


/**
 * Update container
 */
Route::post('/containers/{ref}/update', function (Request $request, $ref) {
    DB::table('containers')->where('ref', '=', $ref)->update($request->all());

    return response()->json([]);
});

/**
 * Returns all items in container
 */
Route::get('/containers/{ref}/items', function(Request $request, $ref) {
    $container = DB::selectOne("SELECT * FROM containers WHERE ref = ?", [$ref]);

    if($container == null) {
        return response()->json([]);
    }

    $data = json_decode($container->data, true);

    return response()->json( $data );
});

/**
 * Post new item to container
 */
Route::post('/containers/{ref}/items', function(Request $request, $ref) {

    $meta = [
        'created_at' => Carbon::now()->toDateTimeString(),
    ];

    // if user is signed in store uid
    if(auth('api')->user())
    {
        $meta['uid'] = auth('api')->user()->id;
    }

    // get containers and items in that container
    $container = DB::selectOne("SELECT * FROM containers WHERE ref = ?", [$ref]);
    $items = json_decode($container->data, true);

    if($container->requires_auth && !auth('api')->user()) 
    {
        return ['success' => false, 'message' => 'You are not authoarized!'];
    }

    // get new data
    $data = $request->input('data');

    // add meta information
    $data['meta'] = $meta;

    // update data
    $items[] = $data;

    // convert back to string and update it 
    $items_str = json_encode($items);

    DB::table('containers')->where('ref', $ref)->update([
        'data' => $items_str
    ]);

    return [
        'success' => true, 
        'message' => 'Item added to container',
    ];
});

/**
 * Return item in container by ID
 */
Route::get('/containers/{ref}/items/{id}', function (Request $request, $ref, $id) {
    $container = DB::selectOne("SELECT * FROM containers WHERE ref = ?", [$ref]);

    if($container == null) {
        return response()->json([]);
    }

    $data = json_decode($container->data, true);

    return response()->json( $data[$id] );
});

/**
 * Delete container
 */
Route::middleware('auth:api')->delete('/containers/{ref}', function(Request $request, $ref) {

    $container = DB::table('containers')->where('ref', $ref)->get();

    // check if container belongs to user
    if($container[0]->user_id == $request->user()->id) 
    {
        DB::table('containers')->where('ref', $ref)->delete();

        return ['success' => true, 'message' => 'Container removed!'];
    }

    return ['success' => false, 'message' => 'You are not authoarized to remove that container!'];
});