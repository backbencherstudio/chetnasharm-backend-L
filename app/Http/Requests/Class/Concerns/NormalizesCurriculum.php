<?php

namespace App\Http\Requests\Class\Concerns;

trait NormalizesCurriculum
{
    /** Accept curriculum as an array or JSON string. */
    protected function prepareForValidation(): void
    {
        $curriculum = $this->input('curriculum');

        if (! is_string($curriculum)) {
            return;
        }

        $decoded = json_decode($curriculum, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $this->merge(['curriculum' => $decoded]);
        }
    }

    /**
     * Clear curriculum validation messages using human-readable item numbers.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'curriculum.array' => 'Curriculum must be a list of modules.',
            'curriculum.*.title.required' => 'Curriculum item #:position needs a title.',
            'curriculum.*.title.string' => 'Curriculum item #:position title must be text.',
            'curriculum.*.title.max' => 'Curriculum item #:position title may not be greater than :max characters.',
            'curriculum.*.keypoints.required' => 'Curriculum item #:position needs at least one keypoint.',
            'curriculum.*.keypoints.array' => 'Curriculum item #:position keypoints must be a list.',
            'curriculum.*.keypoints.min' => 'Curriculum item #:position needs at least one keypoint.',
            'curriculum.*.keypoints.*.required' => 'Curriculum item #:position has an empty keypoint at position #:second-position.',
            'curriculum.*.keypoints.*.string' => 'Curriculum item #:position keypoint #:second-position must be text.',
            'curriculum.*.keypoints.*.max' => 'Curriculum item #:position keypoint #:second-position may not be greater than :max characters.',
        ];
    }
}
