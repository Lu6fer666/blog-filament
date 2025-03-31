<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{

    public function index()
    {
        return view('posts.index', ['posts' => Post::latest()->get()]);
    }

    public function create()
    {
        return view('posts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required',
            'content' => 'required'
        ]);

        $validated['user_id'] = auth()->id();

        Post::create($validated);
        return redirect()->route('posts.index');
    }

    public function show(Post $post)
    {
        return view('posts.show', compact('post'));
    }

    public function edit(Post $post)
    {
        if (auth()->id() !== $post->user_id) abort(403);
        return view('posts.edit', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        if (auth()->id() !== $post->user_id) abort(403);

        $validated = $request->validate([
            'title' => 'required',
            'content' => 'required'
        ]);

        $post->update($validated);
        return redirect()->route('posts.index');
    }

    public function destroy(Post $post)
    {
        if (auth()->id() !== $post->user_id) abort(403);
        $post->delete();
        return redirect()->route('posts.index');
    }
}
