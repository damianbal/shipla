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

    $options = $request->input('options');

    // sort if order_field is provided
    if(isset($options['order_field'])) {
        
        // if order_field is set to sort by created at then we need to prepend 'meta'
        if($options['order_field'] == 'created_at') {
            usort($data, function ($a, $b) use($options) {
                return $a['meta']['created_at'] - $b['meta']['created_at'];
            });
        }
        // sort by provided field
        else {
            usort($data, function ($a, $b) use($options) {
                return $a[$options['order_field']] - $b[$options['order_field']];
            });
        }


        // reverse the order
        if($options['order_direction'] == 'DESC') {
            $data = array_reverse($data);
        }

        // check if page_index option is set
        if(isset($options['page_index'])) {
            // get items for that page 
            $page_items = [];

            $page_index = $options['page_index'];

            $per_page = $container->items_per_page;

            for($i = $page_index * $per_page; $i < ($page_index * $per_page) + $per_page; $i++) {
                if(isset($data[$i])) {
                    $page_items[] = $data[$i];
                }
            }

            // return data
            return response()->json($page_items);
        }

        return response()->json($data);
    }

    // return all items without ordering them
    return response()->json( $data );
});

/**
 * Update fields in item
 */
Route::middleware('auth:api')->patch('/containers/{ref}/items/{id}', function(Request $request, $ref, $id) {
    $container = DB::selectOne("SELECT * FROM containers WHERE ref = ?", [$ref]);

    if($container == null) {
        return response()->json(['success' => false, 'message' => 'Container does not exist!']);
    }

    // get the new data
    $data = $request->input('data');

    // get all the items in json as array
    $items = json_decode($container->data, true);

    // store the meta info
    $meta = $items[$id]["meta"];

    // update the fields
    foreach ($data as $key => $value) {
        $items[$id][$key] = $value;
    }

    // set meta info back to item, this won't let change it by client-side
    $items[$id]["meta"] = $meta;

    // convert back to string
    $items_str = json_encode($items);

    // update (only if it is our item or we are owners of that container)
    $uid = $items[$id]["meta"]["uid"];

    // if item is not users or signed in user is not container owner then respond with failure
    if($uid != $request->user()->id || $request->user()->id != $container->user_id) {
        return response()->json(['success' => false, 'message' => 'Unathoarized!']);
    }

    // if everything is ok then update that item
    DB::table('containers')->where('ref', $ref)->update([
        'data' => $items_str
    ]);

    // return
    return response()->json( [ 'success' => true ] );
});

/**
 * Post new item to container
 */
Route::post('/containers/{ref}/items', function(Request $request, $ref) {

    $meta = [
        'created_at' => Carbon::now()->toDateTimeString(),
        'ref' => md5(microtime())
    ];

    // if user is signed in store uid
    if(auth('api')->user())
    {
        $meta['uid'] = auth('api')->user()->id;
    }

    // get containers and items in that container
    $container = DB::selectOne("SELECT * FROM containers WHERE ref = ?", [$ref]);
    $items = json_decode($container->data, true);

    // if we are required to be signed in to add data to that container and we are not then return not 
    // authoraized response
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
 * Set new item in container by ID
 */
Route::middleware('auth:api')->post('/containers/{ref}/items/{id}', function (Request $request, $ref, $id) {
    $container = DB::selectOne("SELECT * FROM containers WHERE ref = ?", [$ref]);

    if($container == null) {
        return response()->json(['success' => false, 'message' => 'Container does not exist!']);
    }

    // get the new data
    $data = $request->input('data');

    // get all the items in json as array
    $items = json_decode($container->data, true);

    // store the meta info
    $meta = $items[$id]["meta"];

    // set new data 
    $items[$id] = $data;

    // set meta info back to item
    $items[$id]["meta"] = $meta;

    // convert back to string
    $items_str = json_encode($items);

    $uid = $items[$id]["meta"]["uid"];

    if($uid != $request->user()->id || $request->user()->id != $container->user_id) {
        return response()->json(['success' => false, 'message' => 'Unathoarized!']);
    }

    // update 
    DB::table('containers')->where('ref', $ref)->update([
        'data' => $items_str
    ]);

    // return
    return response()->json( [ 'success' => true ] );
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