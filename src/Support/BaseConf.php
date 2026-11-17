<?php

namespace Iquesters\Foundation\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Iquesters\Foundation\Support\Settings\SettingsStore;
use Iquesters\Foundation\System\Traits\Loggable;

/**
 * Typed module settings.
 *
 * A settings class declares its settings as typed protected properties and
 * their defaults in prepareDefault(); optional validation rules in rules():
 *
 *     class UserManagementConf extends BaseConf
 *     {
 *         protected ?string $identifier = Module::USER_MGMT;
 *         protected string $auth_form_type;
 *         protected RecaptchaConf $recaptcha;           // nested settings
 *         /** @var OAuthConf[] *\/
 *         protected array $o_auth_providers;            // map of nested settings
 *
 *         protected function prepareDefault(BaseConf $d) { $d->auth_form_type = 'classic'; ... }
 *         protected function rules(): array { return ['auth_form_type' => 'in:classic,schema,both']; }
 *     }
 *
 * Values = code defaults, overridden by what is stored for the module in
 * `module_settings` (JSON, only values that differ from the defaults; see
 * SettingsStore). Stored values are converted to the declared type and
 * validated; anything invalid is logged and the default is used instead.
 *
 * Only a module's root settings class (registered with ConfProvider) reads
 * the database; nested settings objects are filled from the root's document.
 */
abstract class BaseConf
{
    use Loggable;

    /** Module identifier for root settings (e.g. 'iquesters/user-management'); a key for map elements. */
    protected ?string $identifier;

    /** Kept for backwards compatibility; settings are always loaded on construction now. */
    private bool $isLoaded = false;

    /** @var array<string, \ReflectionProperty[]> setting properties per class */
    private static array $propertyCache = [];

    /** Code defaults of this instance, captured before stored values are applied. */
    private array $defaultValues = [];

    abstract protected function prepareDefault(BaseConf $default_values);

    /**
     * Laravel validation rules for this settings class, keyed by setting name
     * (dot paths for nested values). Nested settings classes declare their own.
     * Types are always checked from the property declarations.
     */
    protected function rules(): array
    {
        return [];
    }

    public function __construct()
    {
        $this->applyDefaults();
        $this->defaultValues = $this->toArray();

        if ($this->isRootSettings()) {
            $this->applyStored(SettingsStore::get($this->identifier));
        }

        $this->isLoaded = true;
    }

    /* -------------------------------------------------
     | Reading
     |--------------------------------------------------*/

    /** Current values as a nested array (setting name => value). Secrets are decrypted. */
    public function toArray(): array
    {
        $values = [];

        foreach (self::settingProperties($this) as $name => $prop) {
            $value = $prop->isInitialized($this) ? $prop->getValue($this) : null;

            if ($value instanceof self) {
                $values[$name] = $value->toArray();
            } elseif (is_array($value)) {
                $values[$name] = array_map(fn ($item) => $item instanceof self ? $item->toArray() : $item, $value);
            } else {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /** Code defaults as a nested array. */
    public function defaults(): array
    {
        return $this->defaultValues;
    }

    /** Validation rules of this class and its nested settings, as full dot paths. */
    public function validationRules(): array
    {
        $rules = $this->rules();

        foreach (self::settingProperties($this) as $name => $prop) {
            $value = $prop->isInitialized($this) ? $prop->getValue($this) : null;

            if ($value instanceof self) {
                foreach ($value->validationRules() as $path => $rule) {
                    $rules["{$name}.{$path}"] = $rule;
                }
            }
        }

        return $rules;
    }

    /**
     * One row per setting (for admin screens): dot path, declared type,
     * current value, default, validation rule and whether it is a secret.
     *
     * @return array<int, array{path: string, type: string, value: mixed, default: mixed, rule: mixed, secret: bool}>
     */
    public function describe(): array
    {
        return $this->describeInto('', $this->validationRules(), $this->defaultValues);
    }

    private function describeInto(string $prefix, array $rules, array $defaults): array
    {
        $rows = [];

        foreach (self::settingProperties($this) as $name => $prop) {
            $path = $prefix === '' ? $name : "{$prefix}.{$name}";
            $value = $prop->isInitialized($this) ? $prop->getValue($this) : null;
            $default = $defaults[$name] ?? null;

            if ($value instanceof self) {
                array_push($rows, ...$value->describeInto($path, $rules, is_array($default) ? $default : []));
                continue;
            }

            if (is_array($value) && self::elementClass($prop)) {
                foreach ($value as $key => $element) {
                    if ($element instanceof self) {
                        array_push($rows, ...$element->describeInto("{$path}.{$key}", $rules, (array) ($default[$key] ?? [])));
                    }
                }
                continue;
            }

            $type = $prop->getType();
            $rows[] = [
                'path' => $path,
                'type' => $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed',
                'nullable' => $type?->allowsNull() ?? true,
                'value' => $value,
                'default' => $default,
                'rule' => $rules[$path] ?? null,
                'secret' => ConfSecret::isSensitive($name),
            ];
        }

        return $rows;
    }

    /* -------------------------------------------------
     | Writing
     |--------------------------------------------------*/

    /**
     * Validate and save changed settings (nested array, e.g. ['recaptcha' => ['enabled' => true]]).
     * Only values that differ from the code defaults are stored.
     *
     * @throws ValidationException when a value has the wrong type or breaks a rule
     */
    public function update(array $changes, ?int $userId = null): void
    {
        if (!$this->isRootSettings()) {
            throw new \LogicException(static::class . ' is not a registered module settings class; update the root settings instead.');
        }

        $candidate = clone $this;
        $candidate->cloneNested();
        $errors = [];
        $candidate->fill($changes, '', $errors);

        $data = $candidate->toArray();
        $validator = Validator::make($data, $this->validationRules());
        foreach ($validator->errors()->messages() as $path => $messages) {
            $errors[$path] = $messages[0];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        SettingsStore::put($this->identifier, self::diff($data, $this->defaultValues), $userId);
        ConfProvider::forget($this->identifier);

        // This instance reflects the saved values too.
        $this->applyDefaults();
        $this->applyStored(SettingsStore::get($this->identifier));

        static::logInfo('Settings updated', ['module' => $this->identifier, 'keys' => array_keys($changes), 'user_id' => $userId]);
    }

    /**
     * Typed overrides from a nested array of (possibly loosely typed) values:
     * converts each to its declared type and returns only what differs from
     * the code defaults. Invalid values are skipped and logged. Used when
     * importing settings (e.g. the migration from the old master_data_metas format).
     */
    public function overridesFrom(array $values): array
    {
        $candidate = clone $this;
        $candidate->cloneNested();
        $candidate->applyDefaults();
        $errors = [];
        $candidate->fill($values, '', $errors);

        foreach ($errors as $path => $message) {
            static::logWarning("Skipping invalid setting '{$path}' for " . static::class, ['reason' => $message]);
        }

        return self::diff($candidate->toArray(), $this->defaultValues);
    }

    /** Drop the cached settings so the next ConfProvider::from() reads them again. */
    public function clearCache(): void
    {
        if (!empty($this->identifier)) {
            SettingsStore::forget($this->identifier);
            ConfProvider::forget($this->identifier);
        }
    }

    /** Re-read the stored settings into this instance. */
    public function reload(): void
    {
        $this->clearCache();
        $this->applyDefaults();

        if ($this->isRootSettings()) {
            $this->applyStored(SettingsStore::get($this->identifier));
        }
    }

    /** @deprecated Settings are loaded on construction; kept for existing callers. */
    public function ensureLoaded(): void
    {
    }

    /* -------------------------------------------------
     | Magic access (settings are protected properties)
     |--------------------------------------------------*/

    public function __get($property)
    {
        if (property_exists($this, $property)) {
            $prop = new \ReflectionProperty($this, $property);

            return $prop->isInitialized($this) ? $prop->getValue($this) : null;
        }

        trigger_error('Undefined setting: ' . static::class . '::$' . $property, E_USER_NOTICE);

        return null;
    }

    /** In-memory override for the current request only (tests, runtime tweaks); use update() to save. */
    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            (new \ReflectionProperty($this, $property))->setValue($this, $value);
        } else {
            trigger_error('Cannot set undefined setting: ' . static::class . '::$' . $property, E_USER_NOTICE);
        }
    }

    public function __isset($property): bool
    {
        return property_exists($this, $property) && $this->__get($property) !== null;
    }

    /* -------------------------------------------------
     | Internals
     |--------------------------------------------------*/

    private function isRootSettings(): bool
    {
        return !empty($this->identifier) && ConfProvider::isRegistered($this->identifier);
    }

    /** Every setting gets a typed empty value, then the class's own defaults. */
    private function applyDefaults(): void
    {
        foreach (self::settingProperties($this) as $prop) {
            $type = $prop->getType();

            if (!$type instanceof \ReflectionNamedType) {
                $prop->setValue($this, null);
                continue;
            }

            $name = $type->getName();
            if (!$type->isBuiltin() && is_subclass_of($name, self::class) && !(new \ReflectionClass($name))->isAbstract()) {
                $prop->setValue($this, new $name());
            } elseif ($type->allowsNull()) {
                $prop->setValue($this, null);
            } else {
                $prop->setValue($this, match ($name) {
                    'string' => '',
                    'int' => 0,
                    'float' => 0.0,
                    'bool' => false,
                    'array' => [],
                    default => null,
                });
            }
        }

        $this->prepareDefault($this);
    }

    /** Stored values over the defaults; invalid ones are logged and keep their default. */
    private function applyStored(array $stored): void
    {
        if ($stored === []) {
            return;
        }

        $errors = [];
        $this->fill($stored, '', $errors);

        $validator = Validator::make($this->toArray(), $this->validationRules());
        foreach ($validator->errors()->messages() as $path => $messages) {
            $errors[$path] = $messages[0];
            $this->resetToDefault($path);
        }

        foreach ($errors as $path => $message) {
            static::logWarning("Invalid stored setting '{$path}' for {$this->identifier}, using the default", ['reason' => $message]);
        }
    }

    /**
     * Assign values (nested array) to the typed properties. Type errors are
     * collected in $errors (dot path => message) and the property is left as is.
     */
    private function fill(array $values, string $path, array &$errors): void
    {
        $properties = self::settingProperties($this);

        foreach ($values as $key => $value) {
            $keyPath = $path === '' ? (string) $key : "{$path}.{$key}";
            $prop = $properties[$key] ?? null;

            if (!$prop) {
                static::logDebug("Ignoring unknown setting '{$keyPath}' for " . static::class);
                continue;
            }

            $type = $prop->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;
            $current = $prop->isInitialized($this) ? $prop->getValue($this) : null;

            // Nested settings object
            if ($typeName && !$type->isBuiltin() && is_subclass_of($typeName, self::class)) {
                if (!is_array($value)) {
                    $errors[$keyPath] = "Must be a group of settings.";
                    continue;
                }
                if (!$current instanceof self) {
                    $current = self::makeElement($typeName, $key);
                    if (!$current) {
                        $errors[$keyPath] = "Cannot create settings group {$typeName}.";
                        continue;
                    }
                    $prop->setValue($this, $current);
                }
                $current->fill($value, $keyPath, $errors);
                continue;
            }

            // Map of nested settings objects (declared with @var SomeConf[])
            if ($typeName === 'array' && ($elementClass = self::elementClass($prop))) {
                if (!is_array($value)) {
                    $errors[$keyPath] = 'Must be a map of settings groups.';
                    continue;
                }
                $map = is_array($current) ? $current : [];
                foreach ($value as $elementKey => $elementValues) {
                    $element = ($map[$elementKey] ?? null) instanceof self ? $map[$elementKey] : self::makeElement($elementClass, (string) $elementKey);
                    if (!$element || !is_array($elementValues)) {
                        $errors["{$keyPath}.{$elementKey}"] = 'Invalid settings group.';
                        continue;
                    }
                    $element->fill($elementValues, "{$keyPath}.{$elementKey}", $errors);
                    $map[$elementKey] = $element;
                }
                $prop->setValue($this, $map);
                continue;
            }

            // Scalars and plain arrays
            $value = self::decryptIfSecret($value, $keyPath);
            [$ok, $coerced] = self::coerce($value, $type);

            if ($ok) {
                $prop->setValue($this, $coerced);
            } else {
                $errors[$keyPath] = 'Must be of type ' . ($type ? (string) $type : 'mixed') . '.';
            }
        }
    }

    /** Put one dot path back to its code default. */
    private function resetToDefault(string $path): void
    {
        $default = data_get($this->defaultValues, $path);
        $errors = [];
        $this->fill(self::nest($path, $default), '', $errors);
    }

    /** Deep copy of nested settings objects (so a candidate can be filled without touching $this). */
    private function cloneNested(): void
    {
        foreach (self::settingProperties($this) as $prop) {
            $value = $prop->isInitialized($this) ? $prop->getValue($this) : null;

            if ($value instanceof self) {
                $copy = clone $value;
                $copy->cloneNested();
                $prop->setValue($this, $copy);
            } elseif (is_array($value)) {
                $prop->setValue($this, array_map(function ($item) {
                    if (!$item instanceof self) {
                        return $item;
                    }
                    $copy = clone $item;
                    $copy->cloneNested();

                    return $copy;
                }, $value));
            }
        }
    }

    /**
     * Convert a value to a property's declared type.
     *
     * @return array{0: bool, 1: mixed} [ok, converted value]
     */
    private static function coerce(mixed $value, ?\ReflectionType $type): array
    {
        if (!$type instanceof \ReflectionNamedType || $type->getName() === 'mixed') {
            return [true, $value];
        }

        if ($value === null) {
            return [$type->allowsNull(), null];
        }

        return match ($type->getName()) {
            'bool' => match (true) {
                is_bool($value) => [true, $value],
                in_array($value, [1, '1', 'true', 'yes', 'on'], true) => [true, true],
                in_array($value, [0, '0', 'false', 'no', 'off', ''], true) => [true, false],
                default => [false, null],
            },
            'int' => match (true) {
                is_int($value) => [true, $value],
                is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1 => [true, (int) $value],
                is_float($value) && floor($value) === $value => [true, (int) $value],
                default => [false, null],
            },
            'float' => is_numeric($value) ? [true, (float) $value] : [false, null],
            'string' => is_string($value) || is_int($value) || is_float($value) ? [true, (string) $value] : [false, null],
            'array' => match (true) {
                is_array($value) => [true, $value],
                is_string($value) && is_array($decoded = json_decode($value, true)) => [true, $decoded],
                default => [false, null],
            },
            default => [true, $value],
        };
    }

    private static function decryptIfSecret(mixed $value, string $path): mixed
    {
        if (!ConfSecret::isEncrypted($value)) {
            return $value;
        }

        try {
            return ConfSecret::decrypt($value);
        } catch (\Throwable $e) {
            static::logError("Could not decrypt setting '{$path}' (was APP_KEY changed?)", ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array<string, \ReflectionProperty> setting name => property (public/protected, non-static, not `identifier`) */
    private static function settingProperties(self $conf): array
    {
        return self::$propertyCache[$conf::class] ??= (function () use ($conf) {
            $props = [];
            foreach ((new \ReflectionObject($conf))->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $prop) {
                if ($prop->isStatic() || $prop->getName() === 'identifier') {
                    continue;
                }
                $props[$prop->getName()] = $prop;
            }

            return $props;
        })();
    }

    /** Element class of an `@var SomeConf[]` array property, resolved against the declaring class's namespace. */
    private static function elementClass(\ReflectionProperty $prop): ?string
    {
        if (!preg_match('/@var\s+\\\\?([A-Za-z0-9_\\\\]+)\[\]/', (string) $prop->getDocComment(), $m)) {
            return null;
        }

        foreach ([$m[1], $prop->getDeclaringClass()->getNamespaceName() . '\\' . $m[1]] as $class) {
            if (class_exists($class) && is_subclass_of($class, self::class)) {
                return $class;
            }
        }

        return null;
    }

    /** New element of a settings map; an abstract class resolves to "{Key}Conf" in its namespace (google → GoogleConf). */
    private static function makeElement(string $class, string $key): ?self
    {
        $reflection = new \ReflectionClass($class);

        if ($reflection->isAbstract()) {
            $concrete = $reflection->getNamespaceName() . '\\' . \Illuminate\Support\Str::studly($key) . 'Conf';
            if (!class_exists($concrete) || !is_subclass_of($concrete, $class)) {
                return null;
            }
            $class = $concrete;
        }

        $element = new $class();
        if (empty($element->identifier)) {
            $element->identifier = $key;
        }

        return $element;
    }

    /** Values of $data that differ from $defaults (lists and scalars compared whole, groups recursively). */
    private static function diff(array $data, array $defaults): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $default = $defaults[$key] ?? null;
            $isGroup = is_array($value) && !array_is_list($value) && $value !== [];

            if ($isGroup && is_array($default)) {
                $nested = self::diff($value, $default);
                if ($nested !== []) {
                    $result[$key] = $nested;
                }
            } elseif (!array_key_exists($key, $defaults) || $value !== $default) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** 'a.b.c' + value → ['a' => ['b' => ['c' => value]]] */
    private static function nest(string $path, mixed $value): array
    {
        foreach (array_reverse(explode('.', $path)) as $segment) {
            $value = [$segment => $value];
        }

        return $value;
    }
}
