<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shared = parent::share($request);

        $supportedLocales = ['en', 'fa'];
        $defaultLocale = 'en';

        if ($request->filled('lang')) {
            $lang = $request->get('lang');
            if (in_array($lang, $supportedLocales)) {
                app()->setLocale($lang);
                // Save language preference in a cookie for 1 year
                cookie()->queue(cookie('lang', $lang, 60 * 24 * 365));
            } else {
                app()->setLocale($defaultLocale);
                cookie()->queue(cookie('lang', $defaultLocale, 60 * 24 * 365));
            }
        } elseif ($request->hasCookie('lang')) {
            $lang = $request->cookie('lang');
            if (in_array($lang, $supportedLocales)) {
                app()->setLocale($lang);
            } else {
                app()->setLocale($defaultLocale);
            }
        } else {
            app()->setLocale($defaultLocale);
        }

        $currentLocale = app()->getLocale();

        return [
            ...$shared,
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                ...($shared['flash'] ?? []),
                'status' => $request->session()->get('status'),
            ],
            'locale' => $currentLocale,
            'translations' => function () {
                return __('messages');
            },
        ];
    }
}
