<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Support\LocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __construct(
        private readonly LocaleResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(LocaleResolver::SUPPORTED)],
        ]);

        $locale = $this->resolver->normalize($validated['locale']);
        $previous = $this->resolver->resolve($request);
        $request->session()->put('locale', $locale);

        if ($request->user() !== null) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }

        $sessionUser = $request->session()->get('library.user');
        if (is_array($sessionUser)) {
            $sessionUser['locale'] = $locale;
            $request->session()->put('library.user', $sessionUser);
        }

        app()->setLocale($locale);

        Cookie::queue(cookie(
            LocaleResolver::COOKIE,
            $locale,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));

        if ($previous !== $locale) {
            $this->audit->logRequired(
                actionType: 'locale.changed',
                entityType: 'user_preference',
                entityId: $request->user()?->getKey() ?? 'guest',
                oldValues: ['locale' => $previous],
                newValues: ['locale' => $locale],
                scope: 'preferences',
                actor: $request->user() ?? $request->session()->get('library.user'),
                request: $request,
            );
        }

        return redirect()->to($this->safeReturnUrl($request));
    }

    private function safeReturnUrl(Request $request): string
    {
        $fallback = '/';
        $candidate = (string) $request->input('return_to', $request->headers->get('referer', $fallback));
        $parts = parse_url($candidate);
        if ($parts === false || isset($parts['user'], $parts['pass'])) {
            return $fallback;
        }

        if (isset($parts['host']) && mb_strtolower((string) $parts['host']) !== mb_strtolower($request->getHost())) {
            return $fallback;
        }

        $path = (string) ($parts['path'] ?? '/');
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, "\0")) {
            return $fallback;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
