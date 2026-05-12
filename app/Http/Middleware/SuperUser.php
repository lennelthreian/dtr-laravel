<?php

namespace App\Http\Middleware;

use Closure;

class SuperUser
{
    public function handle($request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->is_super) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
