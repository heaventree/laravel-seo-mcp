<?php

namespace Heaventree\SeoMcp\Tools\Providers;

use Heaventree\Seo\SeoManager;
use RuntimeException;

/**
 * Wraps heaventree/laravel-seo's real API (verified against its source,
 * not guessed):
 *   - HasSeo trait on a model: seo(), seoMeta(), setSeo(array), clearSeo()
 *   - SeoManager::for($model)->resolve()/toArray() for the resolved
 *     (stored + computed + inherited) values
 *
 * Writes are opt-in per model class via config('laravel-seo-mcp.allowed_models')
 * -- an empty allowlist (the default) means every seo_set/seo_clear call is
 * refused, same "nothing writable by default" rule AI SEO MCP uses for
 * wp_update_option on the WordPress side. seo_get has no allowlist: reading
 * resolved SEO values is safe for any model that uses HasSeo.
 */
class SeoTools
{
    private static array $handled = ['seo_get', 'seo_set', 'seo_clear'];

    public static function canHandle(string $name): bool
    {
        return in_array($name, self::$handled, true);
    }

    public static function getTools(): array
    {
        return [
            [
                'name' => 'seo_get',
                'description' => 'Get the fully-resolved SEO values for a model (stored overrides merged with computed and inherited defaults via laravel-seo\'s resolution chain): title, description, canonical, image, noindex, nofollow, schema.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'model_type' => ['type' => 'string', 'description' => 'Fully-qualified model class, e.g. App\\Models\\Page.'],
                        'model_id' => ['type' => 'integer'],
                    ],
                    'required' => ['model_type', 'model_id'],
                ],
            ],
            [
                'name' => 'seo_set',
                'description' => 'Create or update a model\'s stored SEO values (title, description, canonical, image, noindex, nofollow, schema). Only works for model classes explicitly allowlisted in config(\'laravel-seo-mcp.allowed_models\') -- empty by default, meaning no writes until an app opts a model class in.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'model_type' => ['type' => 'string'],
                        'model_id' => ['type' => 'integer'],
                        'attributes' => [
                            'type' => 'object',
                            'description' => 'Any subset of: title, description, canonical, image, noindex, nofollow, schema.',
                        ],
                    ],
                    'required' => ['model_type', 'model_id', 'attributes'],
                ],
            ],
            [
                'name' => 'seo_clear',
                'description' => 'Remove a model\'s stored SEO row entirely, dropping it back to computed/inherited values. Same allowlist gate as seo_set.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'model_type' => ['type' => 'string'],
                        'model_id' => ['type' => 'integer'],
                    ],
                    'required' => ['model_type', 'model_id'],
                ],
            ],
        ];
    }

    public static function execute(string $name, array $args): mixed
    {
        switch ($name) {
            case 'seo_get':
                $model = self::resolveModel($args, requireAllowlist: false);

                return app(SeoManager::class)->for($model)->toArray();

            case 'seo_set':
                $model = self::resolveModel($args, requireAllowlist: true);
                if (! method_exists($model, 'setSeo')) {
                    throw new RuntimeException('Model does not use the HasSeo trait.');
                }
                $attributes = is_array($args['attributes'] ?? null) ? $args['attributes'] : [];

                return $model->setSeo($attributes)->toArray();

            case 'seo_clear':
                $model = self::resolveModel($args, requireAllowlist: true);
                if (! method_exists($model, 'clearSeo')) {
                    throw new RuntimeException('Model does not use the HasSeo trait.');
                }
                $model->clearSeo();

                return ['success' => true];

            default:
                throw new RuntimeException('Unhandled tool in SeoTools provider: ' . $name);
        }
    }

    private static function resolveModel(array $args, bool $requireAllowlist): mixed
    {
        $modelType = (string) ($args['model_type'] ?? '');
        $modelId = (int) ($args['model_id'] ?? 0);

        if ($modelType === '' || $modelId <= 0) {
            throw new RuntimeException('model_type and model_id are required.');
        }
        if (! class_exists($modelType)) {
            throw new RuntimeException('Unknown model_type: ' . $modelType);
        }

        $allowed = config('laravel-seo-mcp.allowed_models', []);
        if ($requireAllowlist && ! in_array($modelType, $allowed, true)) {
            throw new RuntimeException(
                $modelType . ' is not in config(\'laravel-seo-mcp.allowed_models\') -- writes are refused until an app opts this model class in.'
            );
        }

        $model = $modelType::query()->find($modelId);
        if (! $model) {
            throw new RuntimeException("{$modelType} #{$modelId} not found.");
        }

        return $model;
    }
}
