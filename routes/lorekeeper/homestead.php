<?php

/*
|--------------------------------------------------------------------------
| Homestead Routes
|--------------------------------------------------------------------------
|
| Routes for logged-in users with a linked account.
|
*/

Route::group(['prefix' => 'homestead', 'namespace' => 'Homestead'], function() {
    Route::get('rooms', 'SpaceController@getIndex')->name('homestead.rooms');
    Route::get('rooms/create', 'SpaceController@getCreate');
    Route::post('rooms/create', 'SpaceController@postCreate');
    Route::get('rooms/{id}/editor', 'HomesteadEditorController@getEditor')->where('id', '[0-9]+');
    Route::post('rooms/{id}/editor', 'HomesteadEditorController@postSave')->where('id', '[0-9]+');
    Route::get('rooms/edit/{id}', 'SpaceController@getEdit')->where('id', '[0-9]+');
    Route::post('rooms/edit/{id}', 'SpaceController@postEdit')->where('id', '[0-9]+');
    Route::get('rooms/delete/{id}', 'SpaceController@getDelete')->where('id', '[0-9]+');
    Route::post('rooms/delete/{id}', 'SpaceController@postDelete')->where('id', '[0-9]+');

    Route::get('houses', 'SpaceController@getIndex')->name('homestead.houses');
    Route::get('houses/create', 'SpaceController@getCreate');
    Route::post('houses/create', 'SpaceController@postCreate');
    Route::get('houses/{id}/editor', 'HomesteadEditorController@getEditor')->where('id', '[0-9]+');
    Route::post('houses/{id}/editor', 'HomesteadEditorController@postSave')->where('id', '[0-9]+');
    Route::get('houses/edit/{id}', 'SpaceController@getEdit')->where('id', '[0-9]+');
    Route::post('houses/edit/{id}', 'SpaceController@postEdit')->where('id', '[0-9]+');
    Route::get('houses/delete/{id}', 'SpaceController@getDelete')->where('id', '[0-9]+');
    Route::post('houses/delete/{id}', 'SpaceController@postDelete')->where('id', '[0-9]+');
});

Route::group(['prefix' => 'favorites', 'namespace' => 'Homestead'], function() {
    Route::get('/', 'FavoriteController@getIndex');
    Route::post('toggle', 'FavoriteController@postToggle');
});
