<?php

namespace Iquesters\Foundation\Support\BusinessEntity;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Iquesters\Foundation\Support\UserModel;

/**
 * Platform models a business entity part can point at, by key ("user",
 * "role", ...). Model parts are reference-only: a business record can link to
 * existing users or roles (e.g. through Spatie's model_has_roles pivot), but
 * never creates or edits them, because those models have their own screens,
 * rules and events.
 *
 * Foundation registers user, and role / permission when spatie/laravel-permission
 * is installed. Other modules register theirs in boot():
 *
 *     app(BusinessEntityModels::class)->register('organisation', Organisation::class);
 */
final class BusinessEntityModels
{
    /** @var array<string, array{class: class-string<Model>, label: string}> */
    private array $models = [];

    public function __construct()
    {
        $this->registerIfModel('user', fn () => UserModel::name(), 'Users');
        $this->registerIfModel('role', fn () => config('permission.models.role'), 'Roles');
        $this->registerIfModel('permission', fn () => config('permission.models.permission'), 'Permissions');
    }

    /** @param class-string<Model> $class */
    public function register(string $key, string $class, ?string $label = null): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException("Invalid model key {$key}.");
        }

        if (! is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException("{$class} is not an Eloquent model.");
        }

        $this->models[$key] = ['class' => $class, 'label' => $label ?? ucfirst(str_replace('_', ' ', $key))];
    }

    public function has(string $key): bool
    {
        return isset($this->models[$key]);
    }

    /** @return class-string<Model> */
    public function classOf(string $key): string
    {
        return ($this->models[$key] ?? throw new InvalidArgumentException("Unknown model {$key}."))['class'];
    }

    public function table(string $key): string
    {
        return $this->instance($key)->getTable();
    }

    /** The value Laravel stores in morph type columns for this model. */
    public function morphClass(string $key): string
    {
        return $this->instance($key)->getMorphClass();
    }

    /** For the builder: [{key, label, table}]. */
    public function describe(): array
    {
        $out = [];

        foreach ($this->models as $key => $model) {
            $out[] = ['key' => $key, 'label' => $model['label'], 'table' => $this->table($key)];
        }

        return $out;
    }

    private function instance(string $key): Model
    {
        $class = $this->classOf($key);

        return new $class;
    }

    private function registerIfModel(string $key, callable $class, string $label): void
    {
        try {
            $name = $class();

            if (is_string($name) && class_exists($name) && is_subclass_of($name, Model::class)) {
                $this->register($key, $name, $label);
            }
        } catch (\Throwable) {
            // Not configured in this app: simply not offered.
        }
    }
}
