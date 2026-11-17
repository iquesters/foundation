<?php

namespace Iquesters\Foundation\Support;

use Iquesters\Foundation\System\Traits\Loggable;

/**
 * Adds what a module needs to the host app's user model (App\Models\User by
 * default): trait imports + `use` in the class, and fillable attributes.
 *
 * Modules call ensure() during `package:discover` (composer install/update),
 * like the other one-time install steps. It is idempotent: anything already
 * present is left alone, and the file is only written when something changes.
 *
 * Only files inside the app directory are edited (never vendor code). If the
 * model's shape isn't recognised, nothing is changed and the reason is
 * returned, so the developer can apply it by hand (see the module README).
 */
final class UserModelPatcher
{
    use Loggable;

    /**
     * @param  array<int, class-string>  $traits  fully-qualified trait names
     * @param  array<int, string>  $fillable  attributes that must be mass-assignable
     * @return array<int, string> human-readable changes (or skip reasons)
     */
    public static function ensure(array $traits, array $fillable = [], ?string $modelClass = null): array
    {
        $modelClass ??= config('auth.providers.users.model') ?: 'App\\Models\\User';

        if (! class_exists($modelClass)) {
            return ["User model {$modelClass} not found; add the traits by hand."];
        }

        $path = (new \ReflectionClass($modelClass))->getFileName();
        $appPath = realpath(app_path());

        if (! $path || ! $appPath || ! str_starts_with(realpath($path) ?: '', $appPath.DIRECTORY_SEPARATOR)) {
            return ["User model {$modelClass} is outside the app directory; not modified."];
        }

        $source = file_get_contents($path);
        [$patched, $changes] = self::patchSource($source, $traits, $fillable);

        if ($patched !== $source) {
            file_put_contents($path, $patched);
            self::logInfo('User model updated', ['model' => $modelClass, 'changes' => $changes]);
        }

        return $changes;
    }

    /**
     * Pure transformation of a model file's source.
     *
     * @return array{0: string, 1: array<int, string>} [new source, changes]
     */
    public static function patchSource(string $source, array $traits, array $fillable = []): array
    {
        $changes = [];

        foreach ($traits as $trait) {
            [$source, $change] = self::addTrait($source, ltrim($trait, '\\'));
            if ($change) {
                $changes[] = $change;
            }
        }

        if ($fillable) {
            [$source, $change] = self::addFillable($source, $fillable);
            if ($change) {
                $changes[] = $change;
            }
        }

        return [$source, $changes];
    }

    private static function addTrait(string $source, string $trait): array
    {
        $short = substr($trait, strrpos($trait, '\\') + 1);

        if (! preg_match('/^(?:final\s+|abstract\s+)?class\s+\w+[^{]*\{/m', $source, $classMatch, PREG_OFFSET_CAPTURE)) {
            return [$source, "Could not find the class declaration; add {$short} by hand."];
        }

        $bodyStart = $classMatch[0][1] + strlen($classMatch[0][0]);

        // Already used inside the class (short or fully-qualified name)?
        $body = substr($source, $bodyStart);
        if (preg_match('/^\s*use\s+[^;]*\b'.preg_quote($short, '/').'\b[^;]*;/m', $body)) {
            return [$source, null];
        }

        // 1. Import the trait (after the last top-level `use` import, or after the namespace).
        if (! preg_match('/^use\s+'.preg_quote($trait, '/').'\s*;/m', $source)) {
            $head = substr($source, 0, $classMatch[0][1]);
            if (preg_match_all('/^use\s+[^;]+;\s*$/m', $head, $imports, PREG_OFFSET_CAPTURE)) {
                $last = end($imports[0]);
                $insertAt = $last[1] + strlen(rtrim($last[0]));
            } elseif (preg_match('/^namespace\s+[^;]+;/m', $head, $ns, PREG_OFFSET_CAPTURE)) {
                $insertAt = $ns[0][1] + strlen($ns[0][0]);
            } else {
                return [$source, "Could not place the import for {$short}; add it by hand."];
            }

            $source = substr($source, 0, $insertAt)."\nuse {$trait};".substr($source, $insertAt);
            $bodyStart += strlen("\nuse {$trait};");
        }

        // 2. Add it to the class's first trait `use` statement, or create one.
        $body = substr($source, $bodyStart);
        if (preg_match('/^(\s*)use\s+([^;(]+);/m', $body, $useMatch, PREG_OFFSET_CAPTURE)
            && ! str_contains($useMatch[0][0], 'function')) {
            $statementEnd = $bodyStart + $useMatch[0][1] + strlen($useMatch[0][0]) - 1; // position of ';'
            $source = substr($source, 0, $statementEnd).", {$short}".substr($source, $statementEnd);
        } else {
            $source = substr($source, 0, $bodyStart)."\n    use {$short};\n".substr($source, $bodyStart);
        }

        return [$source, "Added trait {$short}"];
    }

    private static function addFillable(string $source, array $fillable): array
    {
        // Laravel 13 attribute: #[Fillable(['name', 'email'])]
        if (preg_match('/#\[Fillable\(\[(.*?)\]\)\]/s', $source, $match, PREG_OFFSET_CAPTURE)) {
            return self::mergeList($source, $match[1][1], $match[1][0], $fillable);
        }

        // Classic property: protected $fillable = ['name', 'email'];
        if (preg_match('/\$fillable\s*=\s*\[(.*?)\]\s*;/s', $source, $match, PREG_OFFSET_CAPTURE)) {
            return self::mergeList($source, $match[1][1], $match[1][0], $fillable);
        }

        if (preg_match('/\$guarded\s*=|#\[Guarded/', $source)) {
            return [$source, null]; // guarded models are mass-assignable already
        }

        return [$source, 'No fillable list found; make '.implode(', ', $fillable).' fillable by hand.'];
    }

    /** Append missing quoted names to an array literal's inner text. */
    private static function mergeList(string $source, int $innerOffset, string $inner, array $wanted): array
    {
        preg_match_all('/[\'"]([^\'"]+)[\'"]/', $inner, $existing);
        $missing = array_values(array_diff($wanted, $existing[1]));

        if (! $missing) {
            return [$source, null];
        }

        $quoted = implode(', ', array_map(fn ($name) => "'{$name}'", $missing));
        $trimmed = rtrim($inner);

        if ($trimmed === '') {
            $newInner = $quoted;
        } elseif (str_contains($inner, "\n")) {
            // Multi-line list: one per line, matching the existing indentation.
            preg_match('/\n([ \t]*)[\'"]/', $inner, $indent);
            $pad = $indent[1] ?? '        ';
            $closingPad = preg_match('/\n([ \t]*)$/', $inner, $closing) ? $closing[1] : '    ';
            $newInner = rtrim($trimmed, ',').",\n"
                .implode(",\n", array_map(fn ($name) => "{$pad}'{$name}'", $missing)).",\n"
                .$closingPad;
        } else {
            $newInner = rtrim($trimmed, ', ').', '.$quoted;
        }

        $source = substr($source, 0, $innerOffset).$newInner.substr($source, $innerOffset + strlen($inner));

        return [$source, 'Made fillable: '.implode(', ', $missing)];
    }
}
