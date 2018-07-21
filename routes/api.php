<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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
Route::middleware('auth:api')->post('/containers/{ref}/update', function (Request $request, $ref) {

    $container = DB::table('containers')->where('ref', '=', $ref)->get()[0];

    if($container == null) {
        return ['success' => false, 'message' => 'Container not found!'];
    }

    if($container->user_id != $request->user()->id) {
        return ['success' => false, 'message' => 'You are not authoarized!'];
    }
    
    DB::table('containers')->where('ref', '=', $ref)->update($request->all());

    return response()->json(['success' => true, 'message' => 'Container updated!']);
});

/**
 * Return all items
 * Query params:
 *  -> sort_by (field to be sorted by), default is created_at
 *  -> sort (sorting order), default id desc
 * -> page (index og page), if not passed all the results are retunred
 */
Route::get('/containers/{ref}/items', function(Request $request, $ref) {
    $container = DB::selectOne("SELECT * FROM containers WHERE ref = ?", [$ref]);

    if($container == null) {
        return ['success' => false, 'message' => 'Container does not exist!'];
    }

    // decode data from json string to array
    $data = json_decode($container->data, true);

    $sort_by = $request->input('sort_by', 'created_at'); 
    $sort   = $request->input('sort', 'DESC');

    // sort
    usort($data, function($a, $b) use ($sort_by, $sort) {
        // if it is created_at then we need to handle it bit different
        if($sort_by == 'created_at') 
        {
            return strtotime($a['meta']['created_at']) - strtotime($b['meta']['created_at']);
        } 
        // if it is a string compare lengths
        else if(!is_numeric($a[$sort_by]))
        {
            return strcmp($a[$sort_by], $b[$sort_by]);
        }
        else 
        {
            return $a[$sort_by] - $b[$sort_by];
        }
    });

    // reverse?
    if($sort == 'DESC') {
        $data = array_reverse($data);
    }

    // pagination
    if($request->has('page')) {
        $page_items = [];
        $page_index = $request->input('page', 0);

        $page_items = [];

        $per_page = $container->items_per_page;

        for($i = $page_index * $per_page; $i < ($page_index * $per_page) + $per_page; $i++) {
            if(isset($data[$i])) {
                $page_items[] = $data[$i];
            }
        }

        // return items for page
        return response()->json($page_items);
    }

    // return sorted data
    return $data; 
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

/**
 * Delete item
 */
Route::middleware('auth:api')->delete('/containers/{ref}/items/{id}', function(Request $request, $ref, $id) {

    $container = DB::table('containers')->where('ref', $ref)->get()[0];

    if($container == null) {
        return ['success' => false, 'message' => 'Container doesnt exist!'];
    }

    $items = json_decode($container->data, true);

    $item = $items[$id];

    // if item doesnt have meta.uid then only container owner can remove it
    if(!isset($item['meta']['uid'])) {
        if($request->user()->id != $container->user_id) {
            return [
                'success' => false, 
                'message' => 'You are not authoarized!'
            ];
        }
    }
    else {
        if($request->user()->id != $container->user_id || $item['meta']['uid'] != $request->user()->id) {
            return [
                'success' => false, 
                'message' => 'You are not authoarized!'
            ];
        }
    }

    // removes item directly on $items
    array_splice($items, $id, 1);

    $items_str = json_encode($items);

    DB::table('containers')->where('ref', $ref)->update([
        'data' => $items_str
    ]);

    return ['success' => true, 'message' => 'Item removed!'];
});

