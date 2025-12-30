<?php

namespace Iquesters\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SeederCommand extends Command
{
    private string $seederClassShortName;
    
    private string $seederClassKebabName;

    public function __construct(string $seederClass)
    {
        // Validate class exists
        if (!class_exists($seederClass)) {
            throw new InvalidArgumentException("Seeder class {$seederClass} not found!");
        }

        $this->seederClass = $seederClass;
        $this->seederClassShortName = class_basename($this->seederClass);
        $this->seederClassKebabName = $this->toSeederClassKebabName($this->seederClassShortName);
        
        // Convert AbcXyzSeeder -> abc-xyz
        $seederClassKebabName = $this->toSeederClassKebabName($seederClass);
        
        // Dynamically set signature and description
        $this->signature = "{$seederClassKebabName}:seed";
        $this->description = "Seed {$seederClassKebabName} module data";
        
        parent::__construct();
    }


    private function toSeederClassKebabName(string $className): string
    {   
        $shortName = class_basename($className);
        // Remove 'Seeder' suffix if present
        $name = preg_replace('/Seeder$/', '', $shortName);

        // Convert PascalCase to kebab-case
        $kebab = Str::kebab($name);

        return $kebab;
    }


    public function handle()
    {
        $this->info("Running {$this->seederClassShortName}...");

        $seeder = new $this->seederClass();
        $seeder->setCommand($this);
        $seeder->run();

        $this->info("{$this->seederClassKebabName} seeding completed!");
        return self::SUCCESS;
    }
}