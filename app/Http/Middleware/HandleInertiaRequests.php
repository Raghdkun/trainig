<?php

namespace App\Http\Middleware;

use App\Enums\MediaType;
use App\Models\Store;
use App\Models\User;
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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Upload constraints so the client can reject an oversized file
            // before sending a request the server may not survive.
            'mediaLimits' => MediaType::uploadLimits(),
            // Drives the sidebar's store switcher, which stays in sync with
            // whichever store filter is active on the current page.
            'storeSwitcher' => $request->user() ? $this->storeSwitcherContext($request->user()) : null,
        ];
    }

    /**
     * @return array{canChoose: bool, options: array<int, array{id: int, name: string}>}
     */
    private function storeSwitcherContext(User $user): array
    {
        $stores = $user->isSuperAdmin()
            ? Store::orderBy('name')->get(['id', 'name'])
            : $user->stores()->orderBy('stores.name')->get(['stores.id', 'stores.name']);

        return [
            'canChoose' => $user->isSuperAdmin() || $stores->count() > 1,
            'options' => $stores->map(fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name,
            ])->values()->all(),
        ];
    }
}
