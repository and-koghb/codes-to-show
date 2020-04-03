<?php

class BonusAttributeSeeder extends VerboseSeeder
{
    private $attributeService;

    private $bonusAttribute;
    private $bonusAttrTitle;
    private $referenceAttrTitle;
    private $bonusNameDetail;
    private $bonusUrlDetail;

    public function __construct(AttributeService $attributeService)
    {
        parent::__construct();

        $this->attributeService = $attributeService;

        $this->bonusAttrTitle = ucfirst(ReviewService::BONUS);
        $this->referenceAttrTitle = ucfirst(ReviewService::BONUS_REFERENCE);
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->createOrUpdateBonusAttribute();
        $this->assignBonusToGamblingAndForex();
        $this->deleteReferenceAttrKeepingValues();
    }

    private function createOrUpdateBonusAttribute()
    {
        $this->bonusAttribute = Attribute::where(DB::raw('LOWER(title)'), ReviewService::BONUS)
            ->where('post_type', AttributePostType::REVIEW)
            ->first();

        $rightAttributeExisted = true;

        if (!$this->bonusAttribute) {
            $rightAttributeExisted = false;
            $this->createBonusAttribute();
        } elseif ($this->bonusAttribute->type != AttributeType::DETAILED) {
            $rightAttributeExisted = false;
            $this->updateBonusAttribute();
        }

        if (!$rightAttributeExisted) {
            $this->createOrUpdateBonusAttrDetails();
        }
    }

    private function createBonusAttribute()
    {
        $priority = Attribute::where('post_type', AttributePostType::REVIEW)->count() + 1;
        $this->bonusAttribute = Attribute::create([
            'title' => $this->bonusAttrTitle,
            'slug' => Common::generateSlug(ReviewService::BONUS),
            'post_type' => AttributePostType::REVIEW,
            'type' => AttributeType::DETAILED,
            'priority' => $priority,
            'display_priority' => 1,
            'compare' => 1,
            'format' => 0,
            'limit' => 1,
            'required' => 0,
            'show_in_filters' => 0,
            'status' => Status::ACTIVE,
        ]);

        $this->log('Created attribute ' . $this->bonusAttrTitle . ' with detailed type.');
    }

    private function updateBonusAttribute()
    {
        $this->bonusAttribute->type = AttributeType::DETAILED;
        $this->bonusAttribute->status = Status::ACTIVE;
        $this->bonusAttribute->limit = 1;
        $this->bonusAttribute->save();

        $this->log('Changed ' . $this->bonusAttrTitle . ' attribute type to detailed.');
    }

    private function createOrUpdateBonusAttrDetails()
    {
        $nameDetailTitle = ReviewService::BONUS_NAME;
        $urlDetailTitle = ReviewService::BONUS_GET_URL;
        AttributeDetail::where('attribute_id', $this->bonusAttribute->id)
            ->where('title', '!=', $nameDetailTitle)
            ->where('title', '!=', $urlDetailTitle)
            ->delete();

        $this->createOrUpdateNameDetail($nameDetailTitle);
        $this->createOrUpdateUrlDetail($urlDetailTitle);
    }

    private function createOrUpdateNameDetail(string $nameDetailTitle)
    {
        $bonusNameDetail = AttributeDetail::where('title', $nameDetailTitle)
            ->where('attribute_id', $this->bonusAttribute->id)
            ->first();
        if (!$bonusNameDetail) {
            $this->createNameDetail($nameDetailTitle);
        } else {
            $this->updateNameDetail($bonusNameDetail);
        }
    }

    private function createNameDetail(string $nameDetailTitle)
    {
        $this->bonusNameDetail = AttributeDetail::create([
            'title' => $nameDetailTitle,
            'attribute_id' => $this->bonusAttribute->id,
            'position' => 1,
            'status' => Status::ACTIVE,
        ]);
        $this->log('Created detail ' . $nameDetailTitle . ' for attribute ' . $this->bonusAttrTitle . '.');
    }

    private function updateNameDetail(AttributeDetail $bonusNameDetail)
    {
        $bonusNameDetail->position = 1;
        $bonusNameDetail->status = Status::ACTIVE;
        $bonusNameDetail->save();
        $this->bonusNameDetail = $bonusNameDetail;
        $this->log('Updated detail ' . $bonusNameDetail->title . ' of attribute ' . $this->bonusAttrTitle . '.');
    }

    private function createOrUpdateUrlDetail(string $urlDetailTitle)
    {
        $bonusUrlDetail = AttributeDetail::where('title', $urlDetailTitle)
            ->where('attribute_id', $this->bonusAttribute->id)
            ->first();
        if (!$bonusUrlDetail) {
            $this->createUrlDetail($urlDetailTitle);
        } else {
            $this->updateUrlDetail($bonusUrlDetail);
        }
    }

    private function createUrlDetail(string $urlDetailTitle)
    {
        $this->bonusUrlDetail = AttributeDetail::create([
            'title' => $urlDetailTitle,
            'attribute_id' => $this->bonusAttribute->id,
            'position' => 2,
            'status' => Status::ACTIVE,
        ]);
        $this->log('Created detail ' . $urlDetailTitle . ' for attribute ' . $this->bonusAttrTitle . '.');
    }

    private function updateUrlDetail(AttributeDetail $bonusUrlDetail)
    {
        $bonusUrlDetail->position = 2;
        $bonusUrlDetail->status = Status::ACTIVE;
        $bonusUrlDetail->save();
        $this->bonusUrlDetail = $bonusUrlDetail;
        $this->log('Updated detail ' . $bonusUrlDetail->title . ' of attribute ' . $this->bonusAttrTitle . '.');
    }

    private function assignBonusToGamblingAndForex()
    {
        $this->assignBonusToReviewType(Gambling::REVIEW_TYPE_SLUG);
        $this->assignBonusToReviewType(ForexBroker::REVIEW_TYPE_SLUG);
    }

    private function assignBonusToReviewType(string $typeSlug)
    {
        $reviewType = ReviewType::where('slug', $typeSlug)->first();
        if ($reviewType) {
            $reviewTypeId = $reviewType->id;
            $attributeAssigned = ReviewTypeAttribute::where('attribute_id', $this->bonusAttribute->id)
                ->where('review_type_id', $reviewTypeId)
                ->first();
            if (!$attributeAssigned) {
                ReviewTypeAttribute::create([
                    'attribute_id' => $this->bonusAttribute->id,
                    'review_type_id' => $reviewTypeId
                ]);
                $this->log('Created relation between category ' . $reviewType->name . ' and attribute ' . $this->bonusAttrTitle . '.');
            }
        }
    }

    private function deleteReferenceAttrKeepingValues()
    {
        $bonusReferenceAttribute = Attribute::where('title', ReviewService::BONUS_REFERENCE)
            ->where('post_type', AttributePostType::REVIEW)
            ->first();
        if ($bonusReferenceAttribute) {
            $this->moveOrCopyReferenceAttrValues($bonusReferenceAttribute);
            $this->deleteReferenceAttribute($bonusReferenceAttribute);
            $this->createDetailEmptyValues();
        }
    }

    private function moveOrCopyReferenceAttrValues(Attribute $bonusReferenceAttribute)
    {
        $referenceAttributeValues = AttributeValue::join('attribute_review', 'attribute_review.id', 'attribute_values.attribute_review_id')
            ->where('attribute_review.attribute_id', $bonusReferenceAttribute->id)
            ->select('attribute_values.value', 'attribute_values.updated_at', DB::raw('attribute_review.review_id as review_id'))
            ->get();
        foreach ($referenceAttributeValues as $referenceAttributeValue) {
            $existingBonusValue = AttributeValue::join('attribute_review', 'attribute_review.id', 'attribute_values.attribute_review_id')
                ->where('attribute_review.review_id', $referenceAttributeValue->review_id)
                ->where('attribute_review.attribute_id', $this->bonusAttribute->id)
                ->select('attribute_values.*')
                ->first();
            if (!$existingBonusValue) {
                $this->moveReferenceAttributeValue($referenceAttributeValue);
            } elseif ($existingBonusValue->updated_at < $referenceAttributeValue->updated_at) {
                $this->copyReferenceAttributeValue($referenceAttributeValue, $existingBonusValue);
            }
        }
    }

    private function moveReferenceAttributeValue(AttributeValue $referenceAttributeValue)
    {
        AttributeReview::where('attribute_id', $this->bonusAttribute->id)
            ->where('review_id', $referenceAttributeValue->review_id)
            ->delete();
        $attributeReview = AttributeReview::create([
            'attribute_id' => $this->bonusAttribute->id,
            'review_id' => $referenceAttributeValue->review_id,
        ]);
        AttributeValue::create([
            'attribute_review_id' => $attributeReview->id,
            'value' => $referenceAttributeValue->value,
        ]);

        $logText = 'Moved attribute value from attribute ' . $this->referenceAttrTitle
            . ' to attribute ' . $this->bonusAttrTitle . ' without detail values.';
        $this->log($logText);
    }

    private function copyReferenceAttributeValue(AttributeValue $referenceAttributeValue, AttributeValue $existingBonusValue)
    {
        $existingBonusValue->value = $referenceAttributeValue->value;
        $existingBonusValue->save();

        $logText = 'Updated attribute value for attribute ' . $this->bonusAttrTitle
            . ' to value of attribute ' . $this->referenceAttrTitle . ' without detail values.';
        $this->log($logText);
    }

    private function deleteReferenceAttribute(Attribute $bonusReferenceAttribute)
    {
        $priority = $bonusReferenceAttribute->priority;
        if ($bonusReferenceAttribute->delete()) {
            $this->attributeService->fixPrioritiesOnDelete($priority);
            $this->log('Deleted attribute ' . $this->referenceAttrTitle . ' with all details and unnecessary values.');
        }
    }

    private function createDetailEmptyValues()
    {
        $bonusValues = AttributeValue::join('attribute_review', 'attribute_review.id', 'attribute_values.attribute_review_id')
            ->where('attribute_review.attribute_id', $this->bonusAttribute->id)
            ->select('attribute_values.*')
            ->get();
        foreach ($bonusValues as $bonusValue) {
            $bonusNameDetailValue = AttributeDetailValue::where('attribute_value_id', $bonusValue->id)
                ->where('detail_id', $this->bonusNameDetail->id)
                ->first();
            if (!$bonusNameDetailValue) {
                AttributeDetailValue::create([
                    'attribute_value_id' => $bonusValue->id,
                    'detail_id' => $this->bonusNameDetail->id,
                    'value' => '',
                ]);
            }
            $bonusUrlDetailValue = AttributeDetailValue::where('attribute_value_id', $bonusValue->id)
                ->where('detail_id', $this->bonusUrlDetail->id)
                ->first();
            if (!$bonusUrlDetailValue) {
                AttributeDetailValue::create([
                    'attribute_value_id' => $bonusValue->id,
                    'detail_id' => $this->bonusUrlDetail->id,
                    'value' => '',
                ]);
            }
        }
    }
}
