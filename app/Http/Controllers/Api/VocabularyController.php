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

}
