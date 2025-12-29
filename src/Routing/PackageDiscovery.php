<?php

namespace Iquesters\Foundation\Routing;

use Illuminate\Support\Facades\Log;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\Enums\Module;

use function PHPSTORM_META\map;

class PackageDiscovery
{
    private const INSTALLED_JSON_PATH = 'vendor/composer/installed.json';
    private const IQUESTERS_PACKAGES_PREFIX = 'iquesters/';

    public static function getdiscoveredPackages()
    {
        $vendorPackages = self::discoverVendorPackages();
        $enabledPackages = self::getEnabledPackages();

        Log::debug('[PackageDiscovery] Vendor packages discovered', [
            'count' => count($vendorPackages),
            'packages' => $vendorPackages,
        ]);

        $discoveredPackages = [];

        foreach ($vendorPackages as $package) {
            $packageName = $package['name'] ?? null;

            if (! $packageName) {
                Log::debug('[PackageDiscovery] Skipping package without name');
                continue;
            }

            Log::debug('[PackageDiscovery] Evaluating package', [
                'package' => $packageName,
            ]);

            if (! in_array($packageName, $enabledPackages, true)) {
                Log::debug('[PackageDiscovery] Package is not enabled', [
                    'package' => $packageName,
                ]);
                continue;
            }
            $discoveredPackages[] = $package;
        }

        Log::debug('[PackageDiscovery] Enabled vendor packages resolved', [
            'count' => count($discoveredPackages),
            'packages' => $discoveredPackages,
        ]);

        return $discoveredPackages;
    }
    
    public static function getDiscoveredPackageNames(): array
    {
        Log::debug('[PackageDiscovery] Resolving discovered package names (map/reduce)');

        $vendorPackages = self::discoverVendorPackages();

        $packageNames = array_reduce(
            array_map(
                static fn ($package) => $package['name'] ?? null,
                $vendorPackages
            ),
            static function (array $carry, ?string $packageName) {
                if (! $packageName) {
                    Log::debug('[PackageDiscovery] Skipping package without name');
                    return $carry;
                }

                $carry[] = $packageName;

                return $carry;
            },
            []
        );

        Log::debug('[PackageDiscovery] Discovered package names resolved', [
            'count' => count($packageNames),
            'packages' => $packageNames,
        ]);

        return $packageNames;
    }
    
    /**
     * Discover vendor packages matching a given vendor prefix.
     *
     * @param string $vendorName
     * @return array
     */
    public static function discoverVendorPackages(
        string $vendorName = self::IQUESTERS_PACKAGES_PREFIX
    ): array {
        Log::debug('[PackageDiscovery] Starting vendor package discovery', [
            'vendor_prefix' => $vendorName,
        ]);

        $installedPackages = self::discoverInstalledPackages();

        $vendorPackages = array_values(array_filter(
            $installedPackages,
            static fn ($package) =>
                isset($package['name']) &&
                str_starts_with($package['name'], $vendorName)
        ));

        Log::debug('[PackageDiscovery] Vendor packages discovered', [
            'vendor_prefix' => $vendorName,
            'count' => count($vendorPackages),
        ]);

        return $vendorPackages;
    }
    
    /**
     * Load all installed Composer packages.
     *
     * @return array
     */
    public static function discoverInstalledPackages(): array
    {
        Log::debug('[PackageDiscovery] Starting installed packages discovery');

        $installedJsonPath = base_path(self::INSTALLED_JSON_PATH);

        if (! file_exists($installedJsonPath)) {
            Log::warning('[PackageDiscovery] Composer installed.json file not found', [
                'path' => $installedJsonPath,
            ]);

            return [];
        }

        $installedData = json_decode(
            file_get_contents($installedJsonPath),
            true
        );

        $installedPackages = $installedData['packages'] ?? $installedData;

        Log::debug('[PackageDiscovery] Installed packages loaded', [
            'package_count' => is_array($installedPackages) ? count($installedPackages) : 0,
        ]);

        return $installedPackages;
    }
    
    public static function getEnabledPackages()
    {
        $enabledPackages = config('foundation.enabled_packages', []);

        Log::debug('[PackageDiscovery] Enabled packages configuration loaded', [
            'enabled_packages' => $enabledPackages,
            'count' => count($enabledPackages),
        ]);

        return $enabledPackages;
    }
    
    /**
     * Discover enabled Iquesters packages and their API route files.
     *
     * @return array<int, array{
     *     name: string,
     *     prefix: string|null,
     *     versions: array<string, string>
     * }>
     */
    public static function discover(): array
    {
        Log::debug('[PackageDiscovery] Discovery started');

        $enabledPackages = config('foundation.enabled_packages', []);
        $discoveredPackages = [];

        Log::debug('[PackageDiscovery] Enabled packages configuration loaded', [
            'enabled_packages' => $enabledPackages,
        ]);

        $installedJsonPath = base_path('vendor/composer/installed.json');

        if (! file_exists($installedJsonPath)) {
            Log::warning('[PackageDiscovery] installed.json not found', [
                'path' => $installedJsonPath,
            ]);

            return [];
        }

        $installed = json_decode(file_get_contents($installedJsonPath), true);
        $installedPackages = $installed['packages'] ?? $installed;

        $installedPackages = array_values(array_filter(
            $installedPackages,
            static fn ($package) =>
                isset($package['name']) &&
                str_starts_with($package['name'], 'iquesters/')
        ));

        Log::debug('[PackageDiscovery] Iquesters packages loaded from vendor', [
            'count' => count($installedPackages),
        ]);

        foreach ($installedPackages as $package) {
            $packageName = $package['name'] ?? null;

            if (! $packageName) {
                Log::debug('[PackageDiscovery] Skipping package without name');
                continue;
            }

            Log::debug('[PackageDiscovery] Evaluating package', [
                'package' => $packageName,
            ]);

            if (! in_array($packageName, $enabledPackages, true)) {
                Log::debug('[PackageDiscovery] Package is not enabled', [
                    'package' => $packageName,
                ]);
                continue;
            }

            // Ensure base configuration is loaded
            $baseConf = ConfProvider::from(Module::USER_INFE);
            $baseConf->ensureLoaded();

            $packageApiConf = ConfProvider::from($packageName)->api_conf;

            if (! $packageApiConf) {
                Log::warning('[PackageDiscovery] Enabled package missing platform.api configuration', [
                    'package' => $packageName,
                ]);

                if (config('foundation.strict')) {
                    throw new \RuntimeException(
                        "Enabled package '{$packageName}' does not declare platform.api"
                    );
                }

                continue;
            }

            $installPath = __DIR__ . '/../../';

            Log::debug('[PackageDiscovery] Resolving API versions', [
                'package' => $packageName,
                'install_path' => $installPath,
                'prefix' => $packageApiConf->prefix ?? null,
            ]);

            $versions = [];

            foreach ($packageApiConf->api_versions as $apiVersion) {
                $routeFilePath = $installPath . '/' . $apiVersion->file_name;

                Log::debug('[PackageDiscovery] Checking API version route file', [
                    'package' => $packageName,
                    'version' => $apiVersion->version,
                    'expected_path' => $routeFilePath,
                ]);

                if (file_exists($routeFilePath)) {
                    $versions[$apiVersion->version] = $routeFilePath;

                    Log::debug('[PackageDiscovery] API version registered', [
                        'package' => $packageName,
                        'version' => $apiVersion->version,
                        'path' => $routeFilePath,
                    ]);
                } else {
                    Log::warning('[PackageDiscovery] API version route file missing', [
                        'package' => $packageName,
                        'version' => $apiVersion->version,
                        'expected_path' => $routeFilePath,
                    ]);
                }
            }

            if (! $versions) {
                Log::warning('[PackageDiscovery] No valid API versions found for package', [
                    'package' => $packageName,
                ]);
                continue;
            }

            $discoveredPackages[] = [
                'name' => $packageName,
                'prefix' => $packageApiConf->prefix,
                'versions' => $versions,
            ];

            Log::debug('[PackageDiscovery] Package successfully registered', [
                'package' => $packageName,
                'versions' => array_keys($versions),
            ]);
        }

        Log::debug('[PackageDiscovery] Discovery finished', [
            'registered_packages' => count($discoveredPackages),
        ]);

        return $discoveredPackages;
    }
}