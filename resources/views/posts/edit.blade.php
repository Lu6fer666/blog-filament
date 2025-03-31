@extends('welcome')

@section('content')
    <h1>Modifier le post</h1>

    <form method="POST" action="{{ route('posts.update', $post) }}">
        @csrf @method('PUT')
        <input name="title" value="{{ $post->title }}" class="border p-2 w-full my-2" required>
        <textarea name="content" class="border p-2 w-full my-2" required>{{ $post->content }}</textarea>
        <button type="submit" class="bg-yellow-500 text-white p-2">Modifier</button>
    </form>
@endsection
