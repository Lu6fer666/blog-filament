@extends('welcome')

@section('content')
    <h1>{{ $post->title }}</h1>
    <p>{{ $post->content }}</p>

    @if(auth()->id() === $post->user_id)
        <a href="{{ route('posts.edit', $post) }}" class="bg-blue-500 text-white p-2 mt-4 rounded-md">Modifier</a>

        <form method="POST" action="{{ route('posts.destroy', $post) }}">
            @csrf @method('DELETE')
            <button class="bg-red-500 text-white p-2 mt-4 rounded-md">Supprimer</button>
        </form>
    @endif

@endsection
