<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\Request;

class AdminSongController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->string('keyword')->toString();

        $songs = Song::query()
            ->when($keyword, function ($query) use ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                        ->orWhere('jiriki_rank', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('id')
            ->get();

        return view('songs.index', [
            'songs' => $songs,
            'totalCount' => $songs->count(),
        ]);
    }

    public function edit(Song $song)
    {
        return view('songs.edit', compact('song'));
    }

    public function update(Request $request, Song $song)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'jiriki_rank' => 'required|string|max:20',
        ]);

        $song->update($data);

        return redirect()->route('songs.index')->with('success', 'Song updated successfully.');
    }
}
