@extends('layouts.site')
@section('content')
    <h3>Danh sách bài viết</h3>

        <section class="blog-posts">
            <div class="row">
                <div class="col-md-12">
                    @forelse($posts as $p)
                    <article class="blog-post my-2">
                        <div class="row">
                            <div class="col-sm-3">
                                <img class="img-fluid" src="{{$p->image('cover','desktop')}}" alt="{{$p->title}}">
                            </div>
                            <div class="col-sm-9">
                                <a href="/posts/{{$p->slug}}">{{$p->title}}</a>
                                <br>
                                <small>{{$p->owner}}</small><br>
                                <small>{{$p->publish_start_date}}</small>
                            </div>
                        </div>
                    </article>
                    @empty
                        Chưa có bài viết nào
                    @endforelse
                </div>
            </div>
        </section>


        </div>

@endsection
