<?php

namespace Iquesters\Foundation\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseConf
{
    /**
     * Identifier for the module this config belongs to, 
     * must be initialized in child implementation classes
     */
    protected string $identifier;
    
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
    }
    
    abstract protected function prepareDefault(BaseConf $default_values);

    // /**
    //  * @return array<array{key: string, value: string}> $pairs
    //  */
    // protected function getflattenConfig(array $values = $this->default_values, boolean $useDefault = false)pairs
    // {
    //     // [
    //     //     ['key' => '$RECAPTCHA~ENABLED', 'value' => true],
    //     //     ['key' => '$RECAPTCHA~SITE_KEY', 'value' => 'new_site_key'],
    //     //     ['key' => '$SOCIAL_LOGIN~@O_AUTH_PROVIDERS~$GOOGLE~CLIENT_ID', 'value' => 'new_client_id'],
    //     //     ['key' => 'AUTH_LAYOUT', 'value' => 'hola']
    //     // ]

    //     // call generateKeys();
    //     // loop over generated keys
    //     // for each key, pass the key in decyphir() it return object, get value from $values object
//          ignore null
    // }

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
    public function generateKeys(string $prefix = ''): array
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

    
    /**
     * @param array<array{key: string, value: string}> $pairs
     * @return BaseConf
     */
    public function decipherConf(array $pairs): BaseConf
    {
        Log::debug("Input pairs: " . json_encode($pairs));

        foreach ($pairs as $pair) {
            if (!isset($pair['key'], $pair['value'])) {
                Log::warning("Skipping invalid pair: " . json_encode($pair));
                continue;
            }

            Log::debug("Deciphering key '{$pair['key']}' with value '{$pair['value']}'");
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
        $parts = array_map($normalize, $parts);
        $root = array_shift($parts);
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
        if (empty($parts)) {
            Log::debug("✅ [{$trace}] Assigning value → " . json_encode($value));
            $prop->setValue($current, $value);
            return;
        }

        // 🧱 Nested object (BaseConf)
        if (str_starts_with($key, '$') || $currentValue instanceof BaseConf || is_subclass_of($prop->getType()?->getName() ?? '', BaseConf::class)) {
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

            $this->processKey(implode('~', $parts), $value, $currentValue, $trace);
            return;
        }

        if ($prop->getType()?->getName() === 'array') {
            $arrayValue = $currentValue ?? [];

            // Handle array with @ prefix
            if (!empty($parts)) {
                $nextPart = array_shift($parts);

                if (str_starts_with($nextPart, '$')) {
                    $elementKey = strtolower(ltrim($nextPart, '$'));
                    $trace .= " → \${$elementKey}";

                    // Get element class from docblock
                    $doc = $prop->getDocComment() ?: '';
                    $elementClass = null;
                    if (preg_match('/@var\s+([a-zA-Z0-9_\\\\]+)\[\]/', $doc, $m)) {
                        $elementClass = $m[1];
                    }

                    if ($elementClass && class_exists($elementClass)) {
                        if (!isset($arrayValue[$elementKey]) || !($arrayValue[$elementKey] instanceof $elementClass)) {
                            Log::debug("🧩 [{$trace}] Instantiating new {$elementClass}");
                            $arrayValue[$elementKey] = new $elementClass();
                        }

                        $prop->setValue($current, $arrayValue);
                        $this->processKey(implode('~', $parts), $value, $arrayValue[$elementKey], $trace);
                        return;
                    } else {
                        Log::warning("⚠️  [{$trace}] Cannot resolve array element class");
                        return;
                    }
                } else {
                    Log::warning("⚠️  [{$trace}] Unexpected array key part: {$nextPart}");
                    return;
                }
            }
        }


        Log::warning("⚠️  [{$trace}] Unsupported path: " . implode('~', $parts));
    }

    /**
     * Magic getter
     * Get a config value by key
     */
    public function __get($property)
    {
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