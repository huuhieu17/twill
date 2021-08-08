@extends('layouts.site')
@section('content')
    <section class="post-content">
        <div class="row">
            <div class="col">
                <h2>{{$post->title}}</h2>
                <p style="color: #8F938F">{{$post->owner}} - {{$post->publish_start_date}}</p>
                <img class="img-fluid" src="{{$post->image('cover','mobile',['w'=>900,'fit'=>null])}}" alt="">
                {!! $post->content !!}
            </div>
        </div>
    </section>
@endsection

