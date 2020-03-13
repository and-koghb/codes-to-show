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
}
