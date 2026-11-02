<?php

namespace AhmedElsied\FilamentMediaBrowser\Support;

use AhmedElsied\FilamentMediaBrowser\MediaBrowserPlugin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class UsageLinks
{
    /**
     * @return list<array{label: string, url: ?string}>
     */
    public static function forForeignKey(string $table, string $foreignKey, int|string $id): array
    {
        $usages = DB::table($table)
            ->where($foreignKey, $id)
            ->orderBy('id')
            ->get(['attachable_type', 'attachable_id', 'collection_name']);

        return array_values($usages
            ->map(function (object $usage): array {
                $type = (string) $usage->attachable_type;
                $recordId = (string) $usage->attachable_id;
                $model = self::find($type, $recordId);

                return [
                    'label' => self::label($type, $recordId, (string) $usage->collection_name, $model),
                    'url' => $model instanceof Model ? MediaBrowserPlugin::urlForRecord($model) : null,
                ];
            })
            ->all());
    }

    /**
     * @return list<array{label: string, url: ?string}>
     */
    public static function forOwner(string $modelType, string $modelId): array
    {
        $model = self::find($modelType, $modelId);

        return [[
            'label' => self::modelTypeLabel($modelType).' · '.(self::displayName($model) ?? '#'.$modelId),
            'url' => $model instanceof Model ? MediaBrowserPlugin::urlForRecord($model) : null,
        ]];
    }

    public static function count(string $table, string $foreignKey, int|string $id): int
    {
        return DB::table($table)->where($foreignKey, $id)->count();
    }

    private static function find(string $type, string $id): ?Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        $found = $class::query()->find($id);

        return $found instanceof Model ? $found : null;
    }

    private static function label(string $type, string $id, string $collection, ?Model $model): string
    {
        $basename = $model instanceof Model
            ? (MediaBrowserPlugin::typeLabel($model) ?? self::modelTypeLabel($type))
            : self::modelTypeLabel($type);

        $name = self::displayName($model);
        $label = $name !== null ? "{$basename} · {$name}" : "{$basename} · #{$id}";

        return $collection !== '' ? "{$label} ({$collection})" : $label;
    }

    private static function modelTypeLabel(?string $modelType): string
    {
        if (blank($modelType)) {
            return '—';
        }

        return class_basename(Relation::getMorphedModel($modelType) ?? $modelType);
    }

    private static function displayName(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        foreach (['title', 'name'] as $attribute) {
            $resolved = self::stringify($model->getAttribute($attribute));

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private static function stringify(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed !== '' ? $trimmed : null;
        }

        if (! is_array($value)) {
            return null;
        }

        foreach ([app()->getLocale(), (string) config('app.fallback_locale', 'en'), 'en'] as $locale) {
            $candidate = $value[$locale] ?? null;

            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        foreach ($value as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
