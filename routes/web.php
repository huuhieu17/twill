<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::name('homepage')->get('/',[PageController::class,'homepage']);
Route::name('posts.index')->get('/posts',[PostController::class,'index']);
Route::name('posts.show')->get('/posts/{slug}',[PostController::class,'show']);
