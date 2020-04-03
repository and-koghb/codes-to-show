<?php

class StoreReview extends FormRequest
{
    public function authorize()
    {
        return Auth::user()->hasPermission('create-reviews');
    }

    public function rules()
    {
        $maxFileSize = config('filesystems.images.review.max_file_size');
        $minWidth = config('filesystems.images.blog.min_width');
        $minHeight = config('filesystems.images.blog.min_height');
        $maxWidth = config('filesystems.images.blog.max_width');
        $maxHeight = config('filesystems.images.blog.max_height');

        $maxPosition = $this->getMaxPosition();
        $highlightedTypesString = $this->getHighlightedTypesString();
        $statusesString = $this->getAvailableStatusesString();

        $titleSlugRules = ['required', 'min:3', 'max:2000'];

        $review = Review::find($this->request->get('id'));
        $uniqueRule = Rule::unique('reviews')->where(function ($query) use ($review) {
            $query = $query->where('id', '!=', $this->request->get('id'))
                            ->where('language_id', $this->request->get('language_id'))
                            ->where('type_id', $this->request->get('type_id'))
                            ->whereIn('status', [Status::ACTIVE, Status::INACTIVE]);
            if ($review) {
                $query = $query->where('id', '!=', $review->submitted_id);
            }
            return $query;
        });
        $titleSlugRules[] = $uniqueRule;

        return [
            'title' => $titleSlugRules,
            'slug' => $titleSlugRules,
            'icon' => 'mimes:jpg,jpeg,png,gif|max:' . $maxFileSize . '|dimensions:min_width=' . $minWidth . ',min_height=' . $minHeight . ',max_width=' . $maxWidth . ',max_height=' . $maxHeight,
            'content' => 'required|min:3|max:65534',
            'type_id' => 'required|numeric|exists:review_types,id',
            'language_id' => 'required|numeric|exists:languages,id',
            'position' => 'int|min:1|max:' . $maxPosition,
            'is_highlighted' => 'in:' . $highlightedTypesString,
            'status' => 'in:' . $statusesString
        ];
    }

    public function messages()
    {
        $minWidth = config('filesystems.images.blog.min_width');
        $minHeight = config('filesystems.images.blog.min_height');
        $maxWidth = config('filesystems.images.blog.max_width');
        $maxHeight = config('filesystems.images.blog.max_height');

        return [
            'icon.dimensions' => 'The Image has invalid dimensions, it should be: min-' . $minWidth . 'x' . $minHeight . ', max-' . $maxWidth . 'x' . $maxHeight,
        ];
    }

    private function getMaxPosition()
    {
        $existingReviewsCount = Review::where('language_id', $this->request->get('language_id'))
                                        ->where('type_id', $this->request->get('type_id'))
                                        ->whereIn('status', [Status::ACTIVE, Status::INACTIVE])
                                        ->count();
        if ($this->request->has('id')) {
            $review = Review::find($this->request->get('id'));
        }
        if (
            !isset($review) || !$review
            || $review->language_id != $this->request->get('language_id')
            || $review->type_id != $this->request->get('type_id')
            || ($review->status == Status::SUBMITTED && !$review->submitted_id)
        ) {
            $existingReviewsCount ++;
        }

        return $existingReviewsCount;
    }

    private function getHighlightedTypesString()
    {
        $typesArr = Review::getHighlightedTypes();
        return implode(',', array_keys($typesArr));
    }

    private function getAvailableStatusesString()
    {
        $statusesArr = Review::getChangableStatuses();
        return implode(',', array_keys($statusesArr));
    }
}
