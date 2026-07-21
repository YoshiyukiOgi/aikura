<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Authorization\AuthorizationService;
use App\Support\Masters\FoundationMasterRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FoundationMasterController extends ApiController
{
    public function index(string $master, Request $request, AuthorizationService $authorizationService): JsonResponse
    {
        $config = $this->authorizeMaster($master, $request, $authorizationService, 'view');
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'in:active,inactive,all'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query();
        foreach ($config['with'] ?? [] as $relation) {
            $query->with($relation);
        }

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '' && ! empty($config['search'])) {
            $query->where(function (Builder $query) use ($search, $config): void {
                foreach ($config['search'] as $column) {
                    $query->orWhere($column, 'ilike', '%'.$search.'%');
                }
            });
        }

        if ($this->hasField($config, 'is_active')) {
            match ($validated['active'] ?? 'active') {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                default => null,
            };
        }

        foreach ($config['order'] ?? ['id'] as $column) {
            $query->orderBy($column);
        }

        $paginator = $query->paginate($validated['per_page'] ?? 40);

        return $this->ok([
            'master' => $master,
            'records' => collect($paginator->items())->map(fn (Model $record): array => $this->serialize($record, $config))->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'references' => $this->references($config),
        ]);
    }

    public function show(string $master, int $id, Request $request, AuthorizationService $authorizationService): JsonResponse
    {
        $config = $this->authorizeMaster($master, $request, $authorizationService, 'view');
        $record = $this->findRecord($config, $id);

        return $this->ok([
            'master' => $master,
            'record' => [
                ...$this->serialize($record, $config),
                'history' => $this->history($record),
            ],
            'references' => $this->references($config),
        ]);
    }

    public function store(string $master, Request $request, AuthorizationService $authorizationService): JsonResponse
    {
        $config = $this->authorizeMaster($master, $request, $authorizationService, 'edit');
        $validated = $this->validatePayload($request, $config);

        $record = DB::transaction(function () use ($request, $config, $validated): Model {
            /** @var class-string<Model> $modelClass */
            $modelClass = $config['model'];
            $permissionIds = $validated['permission_ids'] ?? null;
            unset($validated['change_reason'], $validated['permission_ids']);

            /** @var Model $record */
            $record = $modelClass::query()->create($validated);
            if ($record instanceof Role && is_array($permissionIds)) {
                $record->permissions()->sync($permissionIds);
            }
            $record->refresh();
            $this->audit($request, $record, null, $record->getAttributes(), $config, 'created');

            return $record;
        });

        return $this->created(['record' => $this->serialize($this->reload($record, $config), $config)]);
    }

    public function update(string $master, int $id, Request $request, AuthorizationService $authorizationService): JsonResponse
    {
        $config = $this->authorizeMaster($master, $request, $authorizationService, 'edit');
        $record = $this->findRecord($config, $id);
        $validated = $this->validatePayload($request, $config, $record);

        $record = DB::transaction(function () use ($request, $record, $config, $validated): Model {
            $before = $record->getAttributes();
            $permissionIds = $validated['permission_ids'] ?? null;
            unset($validated['change_reason'], $validated['permission_ids']);

            $record->fill($validated);
            $record->save();

            if ($record instanceof Role && is_array($permissionIds)) {
                $record->permissions()->sync($permissionIds);
            }

            $record->refresh();
            $this->audit($request, $record, $before, $record->getAttributes(), $config, 'updated');

            return $record;
        });

        return $this->ok(['record' => $this->serialize($this->reload($record, $config), $config)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function authorizeMaster(string $master, Request $request, AuthorizationService $authorizationService, string $ability): array
    {
        $config = FoundationMasterRegistry::get($master);
        abort_unless(
            $authorizationService->can($request->user(), $config['permission'].'.'.$ability)
                || $authorizationService->can($request->user(), 'role.manage'),
            403,
        );

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function findRecord(array $config, int $id): Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query();
        foreach ($config['with'] ?? [] as $relation) {
            $query->with($relation);
        }

        return $query->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, array $config, ?Model $record = null): array
    {
        $rules = [];
        foreach ($config['fields'] as $field) {
            $name = $field['name'];
            $fieldRules = [];
            $fieldRules[] = ($field['required'] ?? false) ? 'required' : 'nullable';

            if ($field['type'] === 'permissions') {
                $rules[$name] = ['nullable', 'array'];
                $rules[$name.'.*'] = ['integer', 'exists:permissions,id'];
                continue;
            }

            if ($field['type'] === 'boolean') {
                $fieldRules[] = 'boolean';
            } elseif ($field['type'] === 'number') {
                $fieldRules[] = 'integer';
            } elseif ($field['type'] === 'decimal') {
                $fieldRules[] = 'numeric';
            } elseif ($field['type'] === 'date') {
                $fieldRules[] = 'date';
            } elseif ($field['type'] === 'relation') {
                $fieldRules[] = 'integer';
                $fieldRules[] = Rule::exists($this->tableForMaster($field['source']), 'id');
            } elseif ($field['type'] === 'select') {
                $fieldRules[] = Rule::in(array_keys($field['options']));
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:'.($field['max'] ?? 255);
            }

            if (isset($field['min'])) {
                $fieldRules[] = 'min:'.$field['min'];
            }
            if (isset($field['max_value'])) {
                $fieldRules[] = 'max:'.$field['max_value'];
            }
            if ($name === 'code') {
                /** @var class-string<Model> $modelClass */
                $modelClass = $config['model'];
                $fieldRules[] = Rule::unique((new $modelClass)->getTable(), 'code')->ignore($record?->getKey());
            }

            $rules[$name] = $fieldRules;
        }
        $rules['change_reason'] = [$record ? 'required' : 'nullable', 'string', 'max:1000'];

        return $request->validate($rules);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function serialize(Model $record, array $config): array
    {
        $data = ['id' => $record->getKey()];
        foreach ($config['fields'] as $field) {
            $name = $field['name'];
            if ($field['type'] === 'permissions') {
                $data[$name] = $record instanceof Role
                    ? $record->permissions->pluck('id')->values()->all()
                    : [];
                continue;
            }
            $value = $record->{$name};
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format($field['type'] === 'date' ? 'Y-m-d' : \DateTimeInterface::ATOM);
            }
            $data[$name] = $value;
        }
        $data['updated_at'] = $record->updated_at?->toIso8601String();

        if ($record instanceof Role) {
            $data['permissions_count'] = $record->permissions->count();
        }
        if (method_exists($record, 'getRelation') && $record->relationLoaded('consumptionTaxCategory')) {
            $data['consumption_tax_category_name'] = $record->consumptionTaxCategory?->name;
        }
        if (method_exists($record, 'getRelation') && $record->relationLoaded('parent')) {
            $data['parent_stock_location_name'] = $record->parent?->name;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function references(array $config): array
    {
        $references = [];
        foreach ($config['fields'] as $field) {
            if (($field['type'] ?? null) === 'relation') {
                /** @var class-string<Model> $modelClass */
                $modelClass = FoundationMasterRegistry::get($field['source'])['model'];
                $references[$field['source']] = $modelClass::query()
                    ->when($this->hasField(FoundationMasterRegistry::get($field['source']), 'is_active'), fn (Builder $query) => $query->where('is_active', true))
                    ->orderBy('code')
                    ->limit(200)
                    ->get(['id', 'code', 'name'])
                    ->map(fn (Model $item): array => ['id' => $item->getKey(), 'label' => trim(($item->code ?? '').' '.$item->name)])
                    ->all();
            }
        }
        if (isset($config['references']['permissions'])) {
            $references['permissions'] = Permission::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Permission $permission): array => ['id' => $permission->id, 'code' => $permission->code, 'label' => $permission->code.'：'.$permission->name])
                ->all();
        }

        return $references;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function hasField(array $config, string $name): bool
    {
        return collect($config['fields'])->contains(fn (array $field): bool => $field['name'] === $name);
    }

    private function tableForMaster(string $master): string
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = FoundationMasterRegistry::get($master)['model'];

        return (new $modelClass)->getTable();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function reload(Model $record, array $config): Model
    {
        foreach ($config['with'] ?? [] as $relation) {
            $record->load($relation);
        }

        return $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function history(Model $record): array
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->where('target_table', $record->getTable())
            ->where('target_id', (string) $record->getKey())
            ->latest('occurred_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'event' => $log->event,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
                'user_name' => $log->user?->name,
                'reason' => $log->reason,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     * @param array<string, mixed> $config
     */
    private function audit(Request $request, Model $record, ?array $before, array $after, array $config, string $event): void
    {
        AuditLog::query()->create([
            'occurred_at' => now(),
            'user_id' => $request->user()?->id,
            'event' => 'foundation_master.'.$event,
            'auditable_type' => $record::class,
            'auditable_id' => (string) $record->getKey(),
            'target_table' => $record->getTable(),
            'target_id' => (string) $record->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'reason' => $request->input('change_reason') ?: ($event === 'created' ? '新規登録' : null),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
