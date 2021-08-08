<?php

namespace App\Http\Controllers;
Use App\Repositories\PostRepository;
use Illuminate\Http\Request;

class PostController extends Controller
{
    //
    /**
     * PostController constructor.
     */
    public function __construct(PostRepository $repository)
    {
        $this->repository = $repository;
    }
     public function prepareFieldsBeforeCreate($field){
        $field['layout'] = 'regular';
        return parent::prepareFieldsBeforeCreate($field);
     }
    public function show($slug){
        $post = $this->repository->forSlug($slug);
        abort_unless($post,404,'Post ');
        return view('posts.show',compact('post'));
    }
    public function index(){
        return view('posts.index',['posts'=> $this->repository->allPost()]);
    }
}
