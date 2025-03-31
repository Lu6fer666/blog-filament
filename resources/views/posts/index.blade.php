@extends('welcome')

@section('content')
    <h1 class="text-3xl">Tous les posts</h1>
    <a href="{{ route('posts.create') }}" class="bg-blue-500 text-white p-2">Créer un nouveau post</a>

    @foreach($posts as $post)
        <div class="bg-white p-4 my-2 shadow">
            <h2 class="text-2xl">{{ $post->title }}</h2>
            <p class="text-gray-600">{{ $post->content }}</p>
            <a class="underline" href="{{ route('posts.show', $post) }}">Lire plus</a>
        </div>
    @endforeach
@endsection
