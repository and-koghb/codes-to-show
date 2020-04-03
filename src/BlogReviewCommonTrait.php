<?php

trait BlogReviewCommonTrait
{
    public static function getSubmitted($id)
    {
        return self::whereId($id)
                    ->where('status', Status::SUBMITTED)
                    ->whereNotNull('submitted_id')
                    ->first();
    }

    public static function getSubmittedOriginal($submittedId)
    {
        return self::whereId($submittedId)
                    ->where('status', '!=', Status::SUBMITTED)
                    ->whereNull('submitted_id')
                    ->first();
    }

    public function checkSubmittedStillUniqueness()
    {
        $query = $this->whereNotIn('id', [$this->id, $this->submitted_id])
                        ->where('language_id', $this->language_id)
                        ->where(function ($query) {
                            $query->where('title', $this->title)
                                ->orWhere('slug', $this->slug);
                        });
        if (self::getTableName() == Review::getTableName()) {
            $query = $query->where('type_id', $this->type_id);
        }

        return $query->count() == 0;
    }
}
