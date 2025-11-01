<?php

namespace Iquesters\Foundation\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseConf
{
    /**
     * Internal flag to ensure DB config is loaded only once.
     * Private: only used internally by BaseConf.
     */
    private bool $isLoaded = false;
    
    /**
     * Identifier for the module this config belongs to, 
     * must be initialized in child implementation classes
     */
    protected ?string $identifier;
    
    /**
     * Default values of config
     * may be initialized in child implementation classes
     */
    private BaseConf $default_values;
    
    /**
     * Default Constructor
     */
    public function __construct() {
        $this->initializeNestedConfigs();
        $this->default_values = clone $this;
        $this->prepareDefault($this->default_values);
        
        // 🚀 Load config from DB automatically (once)
        $flattened = $this->loadConfigFromDB($this->identifier);
        if (!empty($flattened)) {
            $this->decipherConf($flattened);
        }
    }
    
    abstract protected function prepareDefault(BaseConf $default_values);

    /**
     * Flatten the configuration into key-value pairs
     * 
     * @param BaseConf|null $values The config object to flatten (defaults to current instance)
     * @param bool $useDefault Whether to use default values
     * @return array<array{key: string, value: mixed}> Flattened key-value pairs
     */
    public function getflattenConfig(
        ?BaseConf $values = null,
        bool $useDefault = false,
        bool $ignoreNull = true
    ): array {
        // Determine the source config
        $sourceConfig = $useDefault ? $this->default_values : ($values ?? $this);
        
        // Always generate keys from default_values to ensure full structure
        $keyGenerationSource = $this->default_values;
        $keys = $keyGenerationSource->generateKeys();
        Log::debug("Generated keys for flattening", ['keys' => $keys]);

        $result = [];

        foreach ($keys as $key) {
            // Get value by traversing source object
            $value = $this->getValueByKey($key, $sourceConfig);

            // ✅ Conditionally skip null values based on $ignoreNull flag
            if ($ignoreNull && $value === null) {
                Log::debug("Skipping key '{$key}' because value is null (ignoreNull=true)");
                continue;
            }

            $result[] = [
                'key' => $key,
                'value' => $value
            ];
        }

        return $result;
    }

    /**
     * Get a value from the config object using a generated key
     * 
     * @param string $key The generated key (e.g., '$SOCIAL_LOGIN~@O_AUTH_PROVIDERS~$GOOGLE~ENABLED')
     * @param BaseConf $config The config object to traverse
     * @return mixed The value at that key path
     */
    private function getValueByKey(string $key, BaseConf $config)
    {
        $parts = explode('~', $key);
        $current = $config;
        $i = 0;
        
        while ($i < count($parts)) {
            $part = $parts[$i];
            
            // Remove prefixes and convert to snake_case
            $cleanPart = strtolower(ltrim($part, '$@'));
            
            // Check if current is still an object we can traverse
            if (!is_object($current)) {
                Log::warning("Cannot traverse further at part '{$part}' for key: {$key}, current value: " . json_encode($current));
                return null;
            }
            
            // Handle reflection to get property value
            $reflection = new \ReflectionObject($current);
            
            if (!$reflection->hasProperty($cleanPart)) {
                Log::warning("Property '{$cleanPart}' not found in " . get_class($current) . " while traversing key: {$key}");
                return null;
            }
            
            $prop = $reflection->getProperty($cleanPart);
            $prop->setAccessible(true);
            
            if (!$prop->isInitialized($current)) {
                Log::debug("Property '{$cleanPart}' not initialized in " . get_class($current) . " for key: {$key}");
                return null;
            }
            
            $value = $prop->getValue($current);
            
            // Check if this is the last part
            if ($i === count($parts) - 1) {
                Log::debug("Returning leaf value for key '{$key}': " . json_encode($value));
                return $value;
            }
            
            // If it's an array with @ prefix, next part should be array element key
            if (is_array($value) && str_starts_with($part, '@')) {
                $i++; // Move to next part
                if ($i >= count($parts)) {
                    // No more parts, return the array itself
                    Log::debug("Returning array for key '{$key}': " . json_encode($value));
                    return $value;
                }
                
                $nextPart = $parts[$i];
                $arrayKey = strtolower(ltrim($nextPart, '$@'));
                
                Log::debug("Looking for array element with key '{$arrayKey}' in array with " . count($value) . " elements");
                
                // Try associative key first
                if (isset($value[$arrayKey])) {
                    $current = $value[$arrayKey];
                    Log::debug("Found array element using associative key '{$arrayKey}'");
                } else {
                    // Search through all array elements for matching identifier
                    $found = false;
                    foreach ($value as $index => $item) {
                        if ($item instanceof BaseConf) {
                            $itemId = strtolower($item->identifier ?? '');
                            Log::debug("Checking array item at index {$index} with identifier '{$itemId}' against '{$arrayKey}'");
                            if ($itemId === $arrayKey) {
                                $current = $item;
                                $found = true;
                                Log::debug("Found matching element at index {$index}");
                                break;
                            }
                        }
                    }
                    
                    if (!$found) {
                        Log::debug("Array element '{$arrayKey}' not found in array for key: {$key}");
                        return null;
                    }
                }
            } elseif ($value instanceof BaseConf) {
                // Continue traversing nested BaseConf
                $current = $value;
                Log::debug("Traversing into BaseConf: " . get_class($current));
            } else {
                // Scalar value but not at end - shouldn't happen
                Log::warning("Reached scalar value '{$value}' before end of key: {$key}");
                return $value;
            }
            
            $i++;
        }
        
        // Shouldn't reach here normally
        return $current;
    }



    /**
     * Automatically initialize nested BaseConf objects and arrays
     */
    protected function initializeNestedConfigs(): void
    {
        $reflection = new \ReflectionObject($this);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $prop) {
            $prop->setAccessible(true);

            // Skip if already initialized
            if ($prop->isInitialized($this)) {
                continue;
            }

            $type = $prop->getType();

            if ($type) {
                $typeName = $type->getName();

                // --- 1. Initialize nested BaseConf objects ---
                if (! $type->isBuiltin() && is_subclass_of($typeName, BaseConf::class)) {
                    $prop->setValue($this, new $typeName());
                    continue;
                }

                // --- 2. Initialize arrays ---
                if ($typeName === 'array') {
                    $prop->setValue($this, []);
                    continue;
                }
            }
        }
    }

    /**
     * Generate keys for all attributes of this config recursively
     * Ignores private properties and class constants
     *
     * @param string $prefix Current key prefix (optional)
     * @return string[]
     */
    private function generateKeys(string $prefix = ''): array
    {
        $keys = [];

        $recurse = function ($data, string $currentPrefix) use (&$recurse, &$keys) {
            if (!is_object($data)) return;

            $reflection = new \ReflectionObject($data);
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $prop) {
                $prop->setAccessible(true);
                $propName = $prop->getName();

                // Check if typed property is initialized
                $isInitialized = !$prop->hasType() || $prop->isInitialized($data);
                $value = $isInitialized ? $prop->getValue($data) : null;

                $newPrefix = $currentPrefix === '' ? $this->toAllCaps($propName) : $currentPrefix . '~' . $this->toAllCaps($propName);

                Log::debug("Inspecting property: '{$propName}', prefix: '{$newPrefix}', initialized: " . ($isInitialized ? 'yes' : 'no'));

                // Nested BaseConf object
                if ($prop->hasType() && !$prop->getType()->isBuiltin() && is_subclass_of($prop->getType()->getName(), BaseConf::class)) {
                    $objPrefix = $currentPrefix === '' ? '$' . $this->toAllCaps($propName) : $currentPrefix . '~$' . $this->toAllCaps($propName);
                    Log::debug("Detected nested BaseConf object: '{$propName}', using prefix '{$objPrefix}'");

                    if ($isInitialized) {
                        $recurse($value, $objPrefix);
                    } else {
                        Log::debug("Uninitialized nested object '{$propName}', adding key: '{$objPrefix}'");
                        $keys[] = $objPrefix;
                    }
                    continue;
                }

                // Array handling - FIXED: Prevent duplicate key generation
                if ($prop->hasType() && $prop->getType()->getName() === 'array') {
                    $pluralKey = $this->toAllCaps($propName);
                    if (!str_ends_with($pluralKey, 'S')) {
                        $pluralKey .= 'S';
                    }
                    $arrayPrefix = $currentPrefix === '' ? '@' . $pluralKey : $currentPrefix . '~@' . $pluralKey;

                    // Parse docblock to detect element type
                    $doc = $prop->getDocComment() ?: '';
                    $elementClass = null;
                    if (preg_match('/@var\s+([a-zA-Z0-9_\\\\]+)\[\]/', $doc, $matches)) {
                        $elementClass = $matches[1];
                    }

                    if ($elementClass && class_exists($elementClass) && is_subclass_of($elementClass, BaseConf::class)) {
                        // Check if we already have array elements to avoid duplicates
                        if (!empty($value) && is_array($value)) {
                            // Use actual array elements to generate keys
                            foreach ($value as $item) {
                                if ($item instanceof BaseConf) {
                                    $id = $this->toAllCaps($item->identifier ?? $this->getClassShortName($item));
                                    $itemPrefix = $arrayPrefix . '~$' . $id;
                                    $recurse($item, $itemPrefix);
                                }
                            }
                        } else {
                            // Only generate from temp instance if array is empty AND we haven't already processed this
                            $temp = new $elementClass();
                            $itemPrefix = $arrayPrefix . '~$' . $this->toAllCaps($temp->identifier ?? $this->getClassShortName($temp));
                            
                            // Check if this key was already added to prevent duplicates
                            $keyExists = false;
                            foreach ($keys as $existingKey) {
                                if (str_starts_with($existingKey, $itemPrefix)) {
                                    $keyExists = true;
                                    break;
                                }
                            }
                            
                            if (!$keyExists) {
                                $recurse($temp, $itemPrefix);
                            }
                        }
                    } elseif (!empty($value) && is_array($value)) {
                        // Non-empty array of objects or scalars
                        foreach ($value as $item) {
                            if ($item instanceof BaseConf) {
                                $id = $this->toAllCaps($item->identifier ?? $this->getClassShortName($item));
                                $itemPrefix = $arrayPrefix . '~$' . $id;
                                $recurse($item, $itemPrefix);
                            } else {
                                // For scalar arrays, only add the array prefix once
                                if (!in_array($arrayPrefix, $keys)) {
                                    $keys[] = $arrayPrefix;
                                }
                            }
                        }
                    } else {
                        // Empty array and no docblock class - add array prefix only once
                        if (!in_array($arrayPrefix, $keys)) {
                            $keys[] = $arrayPrefix;
                        }
                    }

                    continue;
                }

                // Leaf scalar - only add if not already present
                if (!in_array($newPrefix, $keys)) {
                    Log::debug("Adding leaf property key: '{$newPrefix}'");
                    $keys[] = $newPrefix;
                }
            }
        };

        Log::info("Starting generateKeys for class: " . get_class($this));
        $recurse($this, $prefix);
        Log::info("Completed generateKeys, total keys generated: " . count($keys));

        return $keys;
    }


    /**
     * Helper to get short class name without namespace
     */
    private function getClassShortName(object $obj): string
    {
        $fqcn = get_class($obj);
        if (strpos($fqcn, '\\') !== false) {
            $parts = explode('\\', $fqcn);
            return end($parts);
        }
        return $fqcn;
    }
    
    /**
     * Converts property name to ALL_CAPS with underscores
     */
    private function toAllCaps(string $name): string
    {
        // Convert camelCase → snake_case first, then to upper
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $name);
        return strtoupper(str_replace('-', '_', $snake));
    }
    
    
    private function loadConfigFromDB(string $moduleKey): array
    {
        $cacheKey = "conf_{$moduleKey}_flattened";

        // Check cache first
        return Cache::rememberForever($cacheKey, function () use ($moduleKey) {
            Log::info("Loading config for module: {$moduleKey}");

            $moduleEntry = DB::table('master_data')
                ->where('key', "{$moduleKey}-conf")
                ->first();

            if (!$moduleEntry) {
                Log::warning("No master_data entry found for {$moduleKey}-conf");
                return [];
            }

            $metas = DB::table('master_data_metas')
                ->where('ref_parent', $moduleEntry->id)
                ->get(['meta_key', 'meta_value']);

            $flattened = [];
            foreach ($metas as $meta) {
                $value = $meta->meta_value;

                // Decode JSON / boolean
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
                if ($value === 'true') $value = true;
                if ($value === 'false') $value = false;

                $flattened[] = [
                    'key' => $meta->meta_key,
                    'value' => $value,
                ];
            }

            return $flattened;
        });
    }
    
    /**
     * @param array<array{key: string, value: string}> $pairs
     * @return BaseConf
     */
    private function decipherConf(array $pairs): BaseConf
    {
        Log::debug("Input pairs: " . json_encode($pairs));

        foreach ($pairs as $pair) {
            if (!isset($pair['key'], $pair['value'])) {
                Log::warning("Skipping invalid pair: " . json_encode($pair));
                continue;
            }

            // Log::debug("Deciphering key '{$pair['key']}' with value '{$pair['value']}'");
            $this->processKey($pair['key'], $pair['value']);
        }

        return $this;
    }

    private function processKey(string $key, $value, ?object &$current = null, string $trace = ''): void
    {
        $current ??= $this;

        $parts = explode('~', $key);

        // Normalize ALL_CAPS → snake_case and strip prefixes
        $normalize = function (string $p): string {
            return strtolower(ltrim($p, '$@'));
        };
        
        $normalizedParts = array_map($normalize, $parts);
        $root = array_shift($normalizedParts);
        $rootOriginal = array_shift($parts); // Also shift original
        
        $trace = $trace === '' ? $root : $trace . " → {$root}";

        $reflection = new \ReflectionObject($current);
        if (!$reflection->hasProperty($root)) {
            Log::warning("⚠️  [{$trace}] Property '{$root}' missing in " . get_class($current));
            return;
        }

        $prop = $reflection->getProperty($root);
        $prop->setAccessible(true);
        $currentValue = $prop->isInitialized($current) ? $prop->getValue($current) : null;

        // 🎯 Leaf assignment
        if (empty($normalizedParts)) {
            Log::debug("✅ [{$trace}] Assigning value → " . json_encode($value));
            $prop->setValue($current, $value);
            return;
        }

        // 🧱 Nested object (BaseConf)
        // Check ORIGINAL key for $ prefix, not the normalized root
        if (str_starts_with($rootOriginal, '$') || $currentValue instanceof BaseConf || is_subclass_of($prop->getType()?->getName() ?? '', BaseConf::class)) {
            if (!is_object($currentValue)) {
                $className = ucfirst($root) . 'Conf';
                $baseNamespace = (new \ReflectionClass($this))->getNamespaceName();
                $fqcn = "{$baseNamespace}\\{$className}";
                if (class_exists($fqcn)) {
                    Log::debug("🧱 [{$trace}] Creating nested config {$fqcn}");
                    $currentValue = new $fqcn();
                    $prop->setValue($current, $currentValue);
                } else {
                    Log::warning("⚠️  [{$trace}] Missing class {$fqcn}");
                    return;
                }
            }

            // Pass remaining ORIGINAL parts (not normalized)
            $this->processKey(implode('~', $parts), $value, $currentValue, $trace);
            return;
        }

        // Array handling
        if ($prop->getType()?->getName() === 'array') {
            $arrayValue = $currentValue ?? [];

            if (!empty($normalizedParts)) {
                $nextPartOriginal = array_shift($parts); // Get original
                $nextPartNormalized = array_shift($normalizedParts); // Get normalized

                // Check ORIGINAL part for $ prefix
                if (str_starts_with($nextPartOriginal, '$')) {
                    $elementKey = $nextPartNormalized; // Use normalized for array key
                    $trace .= " → \${$elementKey}";

                    // Get element class from docblock
                    $doc = $prop->getDocComment() ?: '';
                    $elementClass = null;
                    if (preg_match('/@var\s+([a-zA-Z0-9_\\\\]+)\[\]/', $doc, $m)) {
                        $elementClass = $m[1];
                        
                        // If class is not fully qualified, try to resolve it
                        if (!class_exists($elementClass)) {
                            // Try in the same namespace as current class
                            $currentNamespace = (new \ReflectionClass($current))->getNamespaceName();
                            $fqcn = $currentNamespace . '\\' . $elementClass;
                            if (class_exists($fqcn)) {
                                $elementClass = $fqcn;
                                Log::debug("Resolved element class to: {$elementClass}");
                            } else {
                                Log::warning("Could not resolve element class: {$elementClass} (tried {$fqcn})");
                                $elementClass = null;
                            }
                        }
                    }

                    if ($elementClass && class_exists($elementClass)) {
                        if (!isset($arrayValue[$elementKey]) || !($arrayValue[$elementKey] instanceof $elementClass)) {
                            // Check if the class is abstract
                            $reflection = new \ReflectionClass($elementClass);
                            if ($reflection->isAbstract()) {
                                // Try to find concrete class based on element key
                                $concreteClass = $this->resolveConcreteClass($elementClass, $elementKey);
                                if ($concreteClass) {
                                    Log::debug("🧩 [{$trace}] Instantiating concrete class {$concreteClass} for abstract {$elementClass}");
                                    $arrayValue[$elementKey] = new $concreteClass();
                                } else {
                                    Log::warning("⚠️  [{$trace}] Cannot instantiate abstract class {$elementClass} and no concrete class found for '{$elementKey}'");
                                    return;
                                }
                            } else {
                                Log::debug("🧩 [{$trace}] Instantiating new {$elementClass}");
                                $arrayValue[$elementKey] = new $elementClass();
                            }
                        }

                        $prop->setValue($current, $arrayValue);
                        
                        // Pass remaining ORIGINAL parts
                        if (!empty($parts)) {
                            $this->processKey(implode('~', $parts), $value, $arrayValue[$elementKey], $trace);
                        } else {
                            // No more parts, this was just the array element identifier
                            Log::debug("✅ [{$trace}] Array element created/accessed");
                        }
                        return;
                    } else {
                        Log::warning("⚠️  [{$trace}] Cannot resolve array element class");
                        return;
                    }
                } else {
                    Log::warning("⚠️  [{$trace}] Unexpected array key part: {$nextPartNormalized} (original: {$nextPartOriginal})");
                    return;
                }
            }
        }

        Log::warning("⚠️  [{$trace}] Unsupported path: " . implode('~', $parts));
    }

    /**
     * Resolve concrete class from abstract base class using identifier
     * Tries to find a class named {Identifier}Conf that extends the base class
     */
    private function resolveConcreteClass(string $abstractClass, string $identifier): ?string
    {
        // Convert identifier to class name (e.g., 'google' -> 'GoogleConf')
        $className = ucfirst($identifier) . 'Conf';
        
        // Try in the same namespace as the abstract class
        $reflection = new \ReflectionClass($abstractClass);
        $namespace = $reflection->getNamespaceName();
        $fqcn = $namespace . '\\' . $className;
        
        if (class_exists($fqcn)) {
            $concreteReflection = new \ReflectionClass($fqcn);
            if ($concreteReflection->isSubclassOf($abstractClass) && !$concreteReflection->isAbstract()) {
                return $fqcn;
            }
        }
        
        return null;
    }

    private function loadConfigOnce(): void
    {
        try {
            $flattened = $this->loadConfigFromDB($this->identifier);
            if (!empty($flattened)) {
                $this->decipherConf($flattened);
            }

            $this->isLoaded = true; // mark as loaded
            Log::debug("✅ Config for {$this->identifier} loaded lazily");
        } catch (\Throwable $e) {
            Log::error("❌ Failed to lazy-load config for {$this->identifier}: " . $e->getMessage());
        }
    }
    
    public function ensureLoaded(): void
    {
        if (!$this->isLoaded && method_exists($this, 'prepareDefault')) {
            $this->prepareDefault($this);
            $this->isLoaded = true;
        }
    }

    /**
     * Magic getter
     * Get a config value by key
     */
    public function __get($property)
    {
        // Lazy load config only once
        if (!$this->isLoaded && property_exists($this, 'identifier') && isset($this->identifier)) {
            $this->loadConfigOnce();
        }
        
        if (property_exists($this, $property)) {
            return $this->$property;
        }
        trigger_error("Undefined property: " . __CLASS__ . "::$" . $property, E_USER_NOTICE);
        return null;
    }

    /**
     * Magic setter
     * Set a config value by key
     */
    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        } else {
            trigger_error("Cannot set undefined property: " . __CLASS__ . "::$" . $property, E_USER_NOTICE);
        }
    }
}