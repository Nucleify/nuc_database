<?php

namespace App\Services;

use Illuminate\Database\Seeder;

class SeederDiscoveryService
{
    public function discoverAndCallSeeders(Seeder $seeder, array $excludedSeeders = []): void
    {
        if (!is_dir(module_path())) {
            return;
        }

        $modules = $this->getModuleDirectories(module_path());

        foreach ($modules as $module) {
            $this->callModuleSeeder($seeder, $module, $excludedSeeders);
        }
    }

    private function getModuleDirectories(string $modulePath): array
    {
        $modules = array_filter(
            scandir($modulePath),
            fn ($m) => !in_array($m, ['.', '..', '_index.scss', 'index.ts', 'README.md', 'nuc_database'])
        );

        usort($modules, function ($a, $b) {
            $aIsNuc = str_starts_with($a, 'nuc_');
            $bIsNuc = str_starts_with($b, 'nuc_');

            if ($aIsNuc && !$bIsNuc) {
                return -1;
            }

            if (!$aIsNuc && $bIsNuc) {
                return 1;
            }

            return strcmp($a, $b);
        });

        return $modules;
    }

    private function callModuleSeeder(Seeder $seeder, string $module, array $excludedSeeders = []): void
    {
        $config = module_config($module);

        if (empty($config)) {
            return;
        }

        if (!$this->shouldRunSeeder($config)) {
            return;
        }

        if (isset($config['seeder']) && $config['seeder'] === false) {
            return;
        }

        $seederName = $config['seeder'] ?? $this->guessSeederName($module);

        if ($this->isSeederExcluded($seederName, $excludedSeeders)) {
            return;
        }

        if (class_exists("Database\\Seeders\\{$seederName}")) {
            $seederClass = "Database\\Seeders\\{$seederName}";
            $seeder->call($seederClass);

            $this->showCompletionMessage($seeder, $config['name']);
        }
    }

    private function shouldRunSeeder(array $config): bool
    {
        return ($config['enabled'] ?? false) && ($config['installed'] ?? false);
    }

    private function guessSeederName(string $module): string
    {
        $parts = explode('_', $module);
        array_shift($parts);
        $name = implode('', array_map('ucfirst', $parts));

        if (str_ends_with($name, 's') && !str_ends_with($name, 'ies')) {
            $name = substr($name, 0, -1);
        }

        return "{$name}Seeder";
    }

    private function showCompletionMessage(Seeder $seeder, string $moduleName): void
    {
        if (!method_exists($seeder, 'showMessage')) {
            return;
        }

        try {
            $reflection = new \ReflectionClass($seeder);
            $commandProperty = $reflection->getProperty('command');
            $commandProperty->setAccessible(true);
            $command = $commandProperty->getValue($seeder);

            $seeder->showMessage("{$moduleName} seeding completed.", $command);
        } catch (\ReflectionException $e) {
        }
    }

    private function isSeederExcluded(string $seederName, array $excludedSeeders): bool
    {
        $fqcn = "Database\\Seeders\\{$seederName}";

        return in_array($seederName, $excludedSeeders, true)
            || in_array($fqcn, $excludedSeeders, true);
    }
}
