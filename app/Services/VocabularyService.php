<?php

namespace App\Services;

use App\Common\Pagination;
use App\Models\Vocabulary;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class VocabularyService
{
    /**
     * @return array{items: array<int, Vocabulary>, pagination: array<string, int>}
     */
    public function index(Request $request): array
    {
        $query = Vocabulary::query();

        if ($request->search) {
            $query->where('word', 'like', '%'.$request->search.'%');
        }

        $vocabularies = $query->oldest()->paginate(Pagination::perPage($request));

        return [
            'items' => $vocabularies->items(),
            'pagination' => [
                'current_page' => $vocabularies->currentPage(),
                'per_page' => $vocabularies->perPage(),
                'total' => $vocabularies->total(),
                'last_page' => $vocabularies->lastPage(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function store(array $validated, ?UploadedFile $image = null): Vocabulary
    {
        if ($image) {
            $validated['image'] = $image->store('vocabulary', 'public');
        }

        return Vocabulary::create($validated);
    }

    public function find(int $id): ?Vocabulary
    {
        return Vocabulary::find($id);
    }

    public function findOrFail(int $id): Vocabulary
    {
        return Vocabulary::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Vocabulary $vocabulary, array $validated, ?UploadedFile $image = null): Vocabulary
    {
        if ($image) {
            if ($vocabulary->image) {
                Storage::disk('public')->delete($vocabulary->image);
            }

            $validated['image'] = $image->store('vocabulary', 'public');
        }

        $vocabulary->update($validated);

        return $vocabulary;
    }

    public function destroy(Vocabulary $vocabulary): void
    {
        if ($vocabulary->image) {
            Storage::disk('public')->delete($vocabulary->image);
        }

        $vocabulary->delete();
    }

    /**
     * @return array{items: array<int, Vocabulary>, pagination: array<string, int>}
     */
    public function vocabularies(Request $request): array
    {
        $perPage = Pagination::perPage($request);

        $vocabularies = Vocabulary::where('status', 1)
            ->oldest()
            ->paginate($perPage);

        return [
            'items' => $vocabularies->items(),
            'pagination' => [
                'current_page' => $vocabularies->currentPage(),
                'per_page' => $vocabularies->perPage(),
                'total' => $vocabularies->total(),
                'last_page' => $vocabularies->lastPage(),
            ],
        ];
    }
}
