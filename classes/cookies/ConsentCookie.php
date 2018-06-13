<?php

namespace OFFLINE\GDPR\Classes\Cookies;

use OFFLINE\GDPR\Models\CookieGroup;
use Symfony\Component\HttpFoundation\Cookie as CookieFoundation;
use Session;
use Illuminate\Support\Facades\Cookie;

class ConsentCookie
{
    const MINUTES_PER_YEAR = 24 * 60 * 365;

    public function set($value)
    {
        $value = $this->appendRequiredCookies($value);

        // Keep the decision for the next request in the session since the cookie
        // will not be available everywhere until the page is reloaded again.
        Session::flash('gdpr_cookie_consent', $value);

        return Cookie::queue(
            'gdpr_cookie_consent',
            $value,
            self::MINUTES_PER_YEAR,           // expire
            '/',                              // path
            null,                             // domain
            $this->isHttps(),                 // secure
            true,                             // httpOnly
            false,                            // raw
            CookieFoundation::SAMESITE_STRICT // sameSite
        );
    }

    public function get()
    {
        return Session::get('gdpr_cookie_consent', Cookie::get('gdpr_cookie_consent'));
    }

    public function hasDeclined()
    {
        return Cookie::get('gdpr_cookie_consent') === false;
    }

    public function isUndecided()
    {
        return Cookie::get('gdpr_cookie_consent') === null;
    }

    protected function isHttps()
    {
        return request()->isSecure();
    }

    public function registerPageView()
    {
        Session::put('gdpr_first_page_view', time());
    }

    public function isFirstPageView(): bool
    {
        return Session::get('gdpr_first_page_view') === null && Cookie::get('gdpr_cookie_consent') === null;
    }

    public function isAllowed($code, $level = 0)
    {
        $consent = $this->get();
        if (count($consent) < 1) {
            return false;
        }

        return array_get($consent, $code, -1) >= $level;
    }

    public function allowedCookieLevel($code, $level = 0)
    {
        $consent = $this->get();
        if (count($consent) < 1) {
            return -1;
        }

        return array_get($consent, $code, -1);
    }

    protected function appendRequiredCookies($value)
    {
        return CookieGroup::with('cookies')
                          ->where('required', true)
                          ->get()
                          ->map->cookies
                          ->flatten()
                          ->pluck('default_level', 'code')
                          ->merge($value);
    }
}