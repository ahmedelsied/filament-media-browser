<?php

namespace AhmedElsied\FilamentMediaBrowser\Console;

use AhmedElsied\FilamentMediaBrowser\Enums\Driver;
use Illuminate\Console\Command;
use RuntimeException;

class InstallCommand extends Command
{
    protected $signature = 'media-browser:install';

    protected $description = 'Choose the media browser driver and publish its files';

    public function handle(): int
    {
        $usesSpatie = $this->confirm(
            'Does this project already use Filament Spatie Media?',
            false,
        );

        $driver = $usesSpatie ? Driver::Spatie : Driver::Default;

        $this->publishConfig($driver);
        $this->publishMigrations($driver);

        $this->components->info("Selected driver [{$driver->value}].");
        $this->components->info('Run php artisan migrate, then register MediaBrowserPlugin::make() on your panel.');

        return self::SUCCESS;
    }

    private function publishConfig(Driver $driver): void
    {
        $published = config_path('media-browser.php');

        if (! is_file($published)) {
            $this->ensureDirectory(dirname($published));
            copy(dirname(__DIR__, 2).'/config/media-browser.php', $published);
        }

        $contents = (string) file_get_contents($published);
        $updated = preg_replace(
            "/^(\\s*'driver'\\s*=>\\s*)'[^']*'/m",
            "\${1}'{$driver->value}'",
            $contents,
            1,
        );

        if (! is_string($updated)) {
            throw new RuntimeException('Could not set the media browser driver.');
        }

        file_put_contents($published, $updated);
    }

    private function publishMigrations(Driver $driver): void
    {
        $source = dirname(__DIR__, 2).'/database/migrations/'.$driver->value;
        $target = database_path('migrations');
        $this->ensureDirectory($target);

        foreach (glob($source.'/*.php') ?: [] as $file) {
            $destination = $target.'/'.basename($file);

            if (! is_file($destination)) {
                copy($file, $destination);
            }
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
