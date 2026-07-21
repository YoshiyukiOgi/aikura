<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use App\Support\Masters\FoundationMasterRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FoundationMasterPageController extends Controller
{
    public function show(string $master, Request $request, AuthorizationService $authorizationService): View
    {
        $config = FoundationMasterRegistry::get($master);

        /** @var User $user */
        $user = $request->user();

        abort_unless($this->canManageMaster($authorizationService, $user, $config['permission'].'.view'), 403);

        return view('masters.foundation.show', [
            'user' => $user,
            'masterKey' => $master,
            'masterConfig' => $this->publicConfig($config),
            'masterNav' => collect(FoundationMasterRegistry::all())
                ->map(fn (array $item, string $key): array => [
                    'key' => $key,
                    'title' => $item['short_title'] ?? $item['title'],
                    'can_view' => $this->canManageMaster($authorizationService, $user, $item['permission'].'.view'),
                ])
                ->filter(fn (array $item): bool => $item['can_view'])
                ->values()
                ->all(),
            'canEdit' => $this->canManageMaster($authorizationService, $user, $config['permission'].'.edit'),
        ]);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function publicConfig(array $config): array
    {
        return collect($config)->only([
            'title',
            'short_title',
            'fields',
            'list_columns',
            'warning',
        ])->all();
    }

    private function canManageMaster(AuthorizationService $authorizationService, User $user, string $permission): bool
    {
        return $authorizationService->can($user, $permission)
            || $authorizationService->can($user, 'role.manage');
    }
}
