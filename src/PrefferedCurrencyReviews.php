<?php

class PrefferedCurrencyReviews extends Criteria
{
    private $currencyId;

    public function __construct(int $currencyId)
    {
        $this->currencyId = $currencyId;
    }

    public function apply($model, Repository $repository)
    {
        $currency = Currency::find($this->currencyId);
        if (!$currency) {
            return $model;
        }
        return $model->join('attribute_review as atrev3', 'atrev3.review_id', 'reviews.id')
                    ->join('attributes as at3', 'atrev3.attribute_id', 'at3.id')
                    ->join('attribute_values as atval3', 'atrev3.id', 'atval3.attribute_review_id')
                    ->where('at3.use_in_preferences', true)
                    ->whereIn('at3.type', [AttributeType::CURRENCY, AttributeType::CURRENCIES])
                    ->where(function ($query) use ($currency) {
                        $query->where(function ($query1) use ($currency) {
                            $query1->where('atval3.value', 'like', '%' . $currency->title . '%')
                                    ->where(function ($query11) {
                                        $query11->where('atval3.option', '!=', AttributeType::MULTISELECT_EXCLUDE)
                                                ->orWhereRaw('atval3.option is null');
                                    });
                        })
                        ->orWhere(function ($query2) use ($currency) {
                            $query2->where('atval3.value', 'not like', '%' . $currency->title . '%')
                                    ->where('atval3.option', AttributeType::MULTISELECT_EXCLUDE);
                        });
                    })
                    ->select('reviews.*')
                    ->groupBy('reviews.id');
    }
}
