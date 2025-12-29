<?php

namespace Iquesters\Foundation\Console;


use Illuminate\Console\Command;

class SeederCommand extends Command
{

    public function __construct(string $seederClass)
    {
        parent::__construct();

        // Validate class exists
        if (!class_exists($seederClass)) {
            throw new \InvalidArgumentException("Seeder class {$seederClass} not found!");
        }

        $this->seederClass = $seederClass;

        // Dynamically set signature and description

        // Convert AbcXyzSeeder -> abc-xyz
        $seederClassKebabName = $this->toSeederClassKebabName($seederClass);
        
        $this->signature = "{$seederClassKebabName}:seed";
        $this->description = "Seed {$seederClassKebabName} module data";
    }


    private function toSeederClassKebabName(string $className): string
    {
        // Remove 'Seeder' suffix if present
        $name = preg_replace('/Seeder$/', '', $className);

        // Convert PascalCase to kebab-case
        $kebab = StringUtils::toKebabCase($name);

        return $kebab;
    }

            // Remove 'Seeder' suffix if present
        $name = preg_replace('/Seeder$/', '', $string);



    public function handle()
    {

        $this->info("Running {$this->seederClass}...");

        $seeder = new $this->seederClass();
        $seeder->setCommand($this);
        $seeder->run();

        $this->info("{$this->seederClass} seeding completed!");
        return 0;
    }
}
