<?php

class SomeDemoFunctions
{
    public static function getPostsByCategory(int $categoryId, $language = AttributesImportOptions::LANGUAGE_ALL)
    {
        $blogQuery = Blog::leftJoin('languages as l', 'blog.language_id', '=', 'l.id')
            ->leftJoin('attribute_blog as ab', 'ab.blog_id', '=', 'blog.id')
            ->where('blog.category_id', $categoryId)
            ->whereIn('blog.status', [Status::ACTIVE, Status::INACTIVE]);
        if ($language != AttributesImportOptions::LANGUAGE_ALL) {
            $blogQuery = $blogQuery->where('l.id', $language);
        }
        $blogPosts = $blogQuery->select('blog.id', 'blog.title', 'l.name as language_name', DB::raw('count(ab.id) as attributes_count'))
            ->groupBy('blog.id')
            ->orderBy('l.name')
            ->orderBy('blog.title')
            ->get();
        return $blogPosts;
    }

    public function fixPositionsOnCreate(Request $request)
    {
        $type = $request->get('type');
        $position = $request->get('position');
        $this->fixPositions($type, [['>=', $position]], Currency::POSITION_INCREMENT);
    }

    private function fixPositions($type, array $positionConditions = [], string $action = Currency::POSITION_INCREMENT)
    {
        $currencyObj = new Currency;
        $currencyObj->timestamps = false;
        $currencyObj = $currencyObj->where('type', $type);
        foreach ($positionConditions as $condition) {
            $currencyObj = $currencyObj->where('position', $condition[0], $condition[1]);
        }
        $currencyObj->$action('position');
    }

    public function fixPositionsOnUpdate(Request $request, int $currencyId)
    {
        $currency = Currency::find($currencyId);

        if ($currency) {
            $type = $request->get('type');
            $newPosition = $request->get('position');
            $currentPosition = $currency->position;
            if ($type == $currency->type) {
                if ($currentPosition < $newPosition) {
                    $this->fixPositions($type, [['>', $currentPosition], ['<=', $newPosition]], Currency::POSITION_DECREMENT);
                } elseif ($currentPosition > $newPosition) {
                    $this->fixPositions($type, [['<', $currentPosition], ['>=', $newPosition]], Currency::POSITION_INCREMENT);
                }
            } else {
                $this->fixPositions($currency->type, [['>', $currentPosition]], Currency::POSITION_DECREMENT);
                $this->fixPositions($type, [['>=', $newPosition]], Currency::POSITION_INCREMENT);
            }
        }
    }

    public function fixPositionsOnDelete(int $type, int $position)
    {
        $this->fixPositions($type, [['>=', $position]], Currency::POSITION_DECREMENT);
    }

    public static function storeUniqueViewAndGetCount(string $postType, int $postId): int
    {
        if (Setting::get('enable_post_views_counter') && self::mongoConnected()) {
            $uniqueCookie = self::getUniqueCookie();
            $postUniqueView = PostUniqueView::where('device_cookie', $uniqueCookie)
                ->where('post_type', $postType)
                ->where('post_id', $postId)
                ->first();
            if (!$postUniqueView) {
                $data = [
                    'post_type' => $postType,
                    'post_id' => $postId,
                    'device_cookie' => $uniqueCookie
                ];

                PostUniqueView::create($data);
            }
            return self::getUniqueViewsCount($postType, $postId);
        }
        return 0;
    }

    public static function mongoConnected($readFromCache = true)
    {
        if ($readFromCache) {
            return Cache::remember('mongoConnected', 1, function () {
                return self::checkMongoConnection();
            });
        }
        return self::checkMongoConnection();
    }

    /**
     * @return bool
     */
    private static function checkMongoConnection()
    {
        try {
            DB::connection('mongodb')->getMongoClient()->listDatabases();
            return true;
        } catch (Exception $exception) {
            return false;
        }
    }

    private static function getUniqueCookie()
    {
        $cookieName = config('app.name') . self::UNIQUE_COOKIE_NAME_SUFFIX;

        if (!isset($_COOKIE[$cookieName]) || !$_COOKIE[$cookieName]) {
            $cookieValue = md5(uniqid() . str_random(10));
            $baseDomain = RouteHelper::getBaseDomain();
            setcookie($cookieName, $cookieValue, time() + (60 * 60 * 24 * 30 * 12 * 2), '/', $baseDomain);
            return $cookieValue;
        }
        return $_COOKIE[$cookieName];
    }

    public static function getUniqueViewsCount(string $postType, int $postId): int
    {
        return PostUniqueView::where('post_type', $postType)
            ->where('post_id', $postId)
            ->count();
    }

    private function createOutgoingLinkRedirect($outgoingLink)
    {
        switch ($outgoingLink->status) {
            case Status::ACTIVE:
                // @todo need to clarify some things, so temporary leaving with commented codes
                if (// strpos(request()->headers->get('referer'), url('/')) === false &&
                    $outgoingLink->double_meta_refresh == OutgoingLink::DOUBLE_META_REFRESH_YES) {
                    $refreshUrl = strpos($outgoingLink->external_url, 'http://') !== false ?
                        RouteHelper::getMetaRefreshHttpUrl() : route('url.refresh');

                    return response('<meta http-equiv="refresh" content="0;url=' . $refreshUrl. '?l=' .
                        $outgoingLink->external_url . '">');
                }
                return redirect($outgoingLink->external_url);

            case Status::INACTIVE:
                return response()->view('errors.inactive-outgoing-link', ['status' => 'inactive'], 403);

            case Status::DELETED:
                return response()->view('errors.inactive-outgoing-link', ['status' => 'deleted'], 404);
        }
    }

    public static function generateSlug(string $title)
    {
        $separator = '-';
        $removableSymbols = [
            '`', '~', '%', '#', '/', '\\', '!', '#', '$', '%', '^', '&', '*', '(', ')',
            '[', ']', '{', '}', '+', '=', '|', ',', '.', ':', ';', '"', '\'', '’', '?'
        ];
        $clearedTitle = str_replace($removableSymbols, '', $title);
        $fixedTitle = str_replace(['@', '_'], [$separator . 'at' . $separator, $separator], $clearedTitle);
        $fixedTitle = trim($fixedTitle);
        $fixedTitle = mb_strtolower(trim($fixedTitle, $separator));
        $convertedTitle = self::convertCharsToEnglishCounterparts($fixedTitle);
        $replaceResult = preg_replace('/\s+/u', $separator, $convertedTitle);
        $multiSeparatorsFixedResult = preg_replace('/' . $separator . $separator . '+/', '-', $replaceResult);
        $convertedTitle = $multiSeparatorsFixedResult ?? $convertedTitle;
        return $convertedTitle;
    }

    private static function convertCharsToEnglishCounterparts(string $string)
    {
        $normalizeChars = [
            'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Ǻ'=>'A', 'Ḁ'=>'A', 'Ă'=>'A', 'Â'=>'A',
            'Ắ'=>'A', 'Ằ'=>'A', 'Ẵ'=>'A', 'Ẳ'=>'A', 'Ấ'=>'A', 'Ầ'=>'A', 'Ẫ'=>'A', 'Ẩ'=>'A', 'Ǎ'=>'A', 'Ǟ'=>'A',
            'Ȧ'=>'A', 'Ǡ'=>'A', 'Ą'=>'A', 'Ą́'=>'A', 'Ą̃'=>'A', 'Ā'=>'A', 'Ā̀'=>'A', 'Ả'=>'A', 'Ȁ'=>'A', 'A̋'=>'A',
            'Ȃ'=>'A', 'Ạ'=>'A', 'Ặ'=>'A', 'Ậ'=>'A', 'Æ'=>'A', 'Ⱥ'=>'A',
            'Ç'=>'C',
            'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E',
            'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I',
            'Ñ'=>'N', 'Ń'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O',
            'Š'=>'S', 'Ș'=>'S', 'Ț'=>'T',
            'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ů'=>'U', 'Ṵ'=>'U', 'Ṷ'=>'U', 'Ṳ'=>'U', 'Ụ'=>'U', 'Ự'=>'U',
            'Ử'=>'U', 'Ữ'=>'U', 'Ừ'=>'U', 'Ứ'=>'U', 'Ư'=>'U', 'Ȗ'=>'U', 'Ȕ'=>'U', 'Ủ'=>'U', 'Ṻ'=>'U', 'Ū'=>'U',
            'Ų'=>'U', 'Ṹ'=>'U', 'Ũ'=>'U', 'Ű'=>'U', 'Ǖ'=>'U', 'Ǚ'=>'U', 'Ǜ'=>'U', 'Ǘ'=>'U', 'Ǔ'=>'U', 'Ŭ'=>'U',
            'W̊'=>'W', 'Ý'=>'Y', 'Y̊'=>'Y', 'Y̊'=>'Y', 'Ÿ'=>'Y', 'Ž'=>'Z', 'Þ'=>'B', 'ß'=>'Ss',

            'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'ǻ'=>'a', 'ḁ'=>'a', 'ă'=>'a', 'â'=>'a',
            'ắ'=>'a', 'ằ'=>'a', 'ẵ'=>'a', 'ẳ'=>'a', 'ấ'=>'a', 'ầ'=>'a', 'ẫ'=>'a', 'ẩ'=>'a', 'ǎ'=>'a', 'ǟ'=>'a',
            'ȧ'=>'a', 'ǡ'=>'a', 'ą'=>'a', 'ą́'=>'a', 'ą̃'=>'a', 'ā'=>'a', 'ā̀'=>'a', 'ả'=>'a', 'ȁ'=>'a', 'a̋'=>'a',
            'ȃ'=>'a', 'ạ'=>'a', 'ặ'=>'a', 'ậ'=>'a', 'æ'=>'a', 'ⱥ'=>'a', 'ᶏ'=>'a', 'ẚ'=>'a',
            'ç'=>'c', 'č'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e','ě'=>'e',
            'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
            'ñ'=>'n', 'ń'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o',
            'ř'=>'r', 'š'=>'s', 'ș'=>'s', 'ț'=>'t',
            'ù'=>'u','ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ů'=>'u', 'ṵ'=>'u', 'ṷ'=>'u', 'ṳ'=>'u', 'ụ'=>'u', 'ự'=>'u',
            'ử'=>'u', 'ữ'=>'u', 'ừ'=>'u', 'ứ'=>'u', 'ư'=>'u', 'ȗ'=>'u', 'ȕ'=>'u', 'ủ'=>'u', 'ṻ'=>'u', 'ū'=>'u',
            'ų'=>'u', 'ṹ'=>'u', 'ũ'=>'u', 'ű'=>'u', 'ǖ'=>'u', 'ǚ'=>'u', 'ǜ'=>'u', 'ǘ'=>'u', 'ǔ'=>'u', 'ŭ'=>'u',
            'ẘ'=>'w', 'ý'=>'y', 'ý'=>'y', 'ẙ'=>'y', 'ÿ'=>'y', 'ž'=>'z', 'þ'=>'b', 'ƒ'=>'f',
//            'Ð'=>'Dj',
//            'ð'=>'o',
        ];

        return strtr($string, $normalizeChars);
    }

    public static function fixStringSpaces(string $string)
    {
        $stringWithoutMultiSpaces = preg_replace('/\s\s+/', ' ', $string);
        $array = explode(',', $stringWithoutMultiSpaces);
        foreach ($array as $key => $val) {
            $array[$key] = trim($val);
        }
        return implode(', ', $array);
    }

    public static function cleanUnnecessaryTags($string)
    {
        return preg_replace('#<script(.*?)>(.*?)</script>|<frame(.*?)>(.*?)<frame>|<iframe(.*?)>(.*?)</iframe>|<object(.*?)>(.*?)</object>|<embed(.*?)>(.*?)</embed>#is', '', $string);
    }
}
