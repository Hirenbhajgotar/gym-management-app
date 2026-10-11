<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Gym;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiGymTenant
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $requestedGymId = $request->header('X-Gym-Id') ?? $request->query('gym_id');

        /** @var Gym|null $gym */
        if (filled($requestedGymId) && ctype_digit((string) $requestedGymId)) {
            $gym = $user->gyms()->where('gyms.id', (int) $requestedGymId)->wherePivot('active', true)->first();
        } else {
            $gym = $user->gyms()->wherePivot('active', true)->first();
        }

        if (! $gym) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'No active gym tenant associated with this account',
            ], 403);
        }

        // Bind resolved gym to request attributes for use in route closures and controllers
        $request->attributes->set('gym', $gym);
        $request->attributes->set('tenant', $gym);

        return $next($request);
    }
}
