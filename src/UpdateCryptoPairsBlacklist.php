<?php

class UpdateCryptoPairsBlacklist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cryptopairsblacklist:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update cryptopairs blacklist for existing exchanges';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $exchanges = $this->getExchanges();

        $currencyPriceService = new CurrencyPriceService;
        foreach ($exchanges as $exchange) {
            $currencyPriceService->getPairsBlacklist(ucfirst($exchange), true);
            $this->info(date('Y-m-d H:i:s') . ' Checked blacklist for ' . $exchange . '.');
        }
    }

    private function getExchanges()
    {
        $exchangeCodes = $this->getExchangeCodes();
        $exchageSlugs = $this->getExchangeSlugs();
        $exchanges = array_unique(array_merge($exchageSlugs, $exchangeCodes));
        sort($exchanges);
        return $exchanges;
    }

    private function getExchangeCodes()
    {
        $exchangeCodes = AttributeValue::join('attribute_review', 'attribute_review.id', 'attribute_values.attribute_review_id')
                                        ->join('attributes', 'attributes.id', 'attribute_review.attribute_id')
                                        ->join('reviews', 'reviews.id', 'attribute_review.review_id')
                                        ->join('review_types', 'review_types.id', 'reviews.type_id')
                                        ->select('attribute_values.value')
                                        ->where('attributes.title', 'Code')
                                        ->where('review_types.slug', Review::SLUG_EXCHANGES)
                                        ->where('reviews.status', Status::ACTIVE)
                                        ->groupBy('attribute_values.value')
                                        ->orderBy('attribute_values.value')
                                        ->get()
                                        ->pluck('value')
                                        ->toArray();
        return $exchangeCodes;
    }

    private function getExchangeSlugs()
    {
        $exchageSlugs = Review::join('review_types', 'review_types.id', 'reviews.type_id')
                                ->where('review_types.slug', Review::SLUG_EXCHANGES)
                                ->where('reviews.status', Status::ACTIVE)
                                ->select('reviews.slug')
                                ->groupBy('reviews.slug')
                                ->orderBy('reviews.slug')
                                ->get()
                                ->pluck('slug')
                                ->toArray();
        return $exchageSlugs;
    }
}
