@extends('welcome')

@section('content')
    <h1>Créer un nouveau post</h1>

    <form method="POST" action="{{ route('posts.store') }}">
        @csrf
        <input name="title" placeholder="Titre" class="border p-2 w-full my-2" required>
        <textarea name="content" placeholder="Contenu" class="border p-2 w-full my-2" required></textarea>
        <button type="submit" class="bg-green-500 text-white p-2">Créer</button>
    </form>
@endsection
