<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vocabulary;
use Illuminate\Http\Request;

class VocabularyController extends Controller
{
    public function index(Request $request)
    {
        $query = Vocabulary::query();

        if ($request->search) {
            $query->where('word', 'like', '%' . $request->search . '%');
        }

        $vocabularies = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $vocabularies
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'word' => 'required|string|max:255',
            'meaning' => 'required|string',
            'example' => 'nullable|string',
            'pronunciation' => 'nullable|string',
            'part_of_speech' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')
                ->store('vocabulary', 'public');
        }

        $vocabulary = Vocabulary::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Vocabulary created successfully',
            'data' => $vocabulary
        ]);
    }

    public function show($id)
    {
        $vocabulary = Vocabulary::find($id);

        if (!$vocabulary) {
            return response()->json([
                'success' => false,
                'message' => 'Vocabulary not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $vocabulary
        ]);
    }

    

}
