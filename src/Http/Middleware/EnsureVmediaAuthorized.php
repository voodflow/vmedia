<?php

declare(strict_types=1);

namespace Voodflow\Vmedia\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional Gate ability for HTTP media routes (in addition to auth + policies).
 */
final class EnsureVmediaAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        $ability = config('vmedia.authorization.ability');

        if (is_string($ability) && $ability !== '' && Gate::denies($ability)) {
            abort(403);
        }

        return $next($request);
    }
}
