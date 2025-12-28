---
paths: "**/*.php"
---

# Laravel Package Rules

### Structure

```
package-name/
├── .github/
│   ├── scripts/parse-changelog.sh
│   └── workflows/
│       ├── tests.yaml
│       ├── pint.yaml
│       └── release.yaml
├── config/package-name.php
├── database/migrations/*.php.stub
├── docs/
│   ├── index.md
│   ├── installation.md
│   ├── configuration.md
│   ├── usage.md
│   ├── api-reference.md
│   └── troubleshooting.md
├── src/
│   ├── Commands/
│   ├── Concerns/              # Traits
│   ├── Contracts/             # Interfaces
│   ├── Models/
│   └── PackageServiceProvider.php
├── tests/
│   ├── TestCase.php
│   └── Fixtures/
├── composer.json
├── CHANGELOG.md
├── LICENSE
├── README.md
├── phpunit.xml.dist
└── pint.json
```

### README Badges

```markdown
[![Latest Version on Packagist](https://img.shields.io/packagist/v/vendor/package.svg?style=flat-square)](https://packagist.org/packages/vendor/package)
[![Tests](https://github.com/vendor/package/actions/workflows/tests.yaml/badge.svg)](https://github.com/vendor/package/actions/workflows/tests.yaml)
[![Total Downloads](https://img.shields.io/packagist/dt/vendor/package.svg?style=flat-square)](https://packagist.org/packages/vendor/package)
[![PHP Version](https://img.shields.io/packagist/php-v/vendor/package.svg?style=flat-square)](https://packagist.org/packages/vendor/package)
[![License](https://img.shields.io/packagist/l/vendor/package.svg?style=flat-square)](https://packagist.org/packages/vendor/package)
```

**Note**: Shields.io caches responses. New packages may show "invalid response data" for a few minutes after first Packagist publish.

### composer.json

```json
{
    "name": "vendor/package-name",
    "require": {
        "php": "^8.2",
        "illuminate/contracts": "^10.0|^11.0|^12.24",
        "illuminate/support": "^10.0|^11.0|^12.24"
    },
    "require-dev": {
        "laravel/pint": "^1.0",
        "orchestra/testbench": "^8.21|^9.6|^10.5",
        "pestphp/pest": "^2.0|^3.0|^4.0"
    },
    "autoload": {
        "psr-4": { "Vendor\\Package\\": "src/" },
        "files": ["src/helpers.php"]
    },
    "extra": {
        "laravel": {
            "providers": ["Vendor\\Package\\PackageServiceProvider"]
        }
    },
    "scripts": {
        "test": "pest",
        "lint": "pint"
    }
}
```

**Version Matrix** (Laravel 12, PHP 8.5, Pest 4 are released):
| Laravel | PHP | Testbench |
|---------|-----|-----------|
| 10.x | 8.1-8.3 | 8.x |
| 11.x | 8.2-8.4 | 9.x |
| 12.24+ | 8.2-8.5 | 10.x |

**Note**: Laravel 12.24+ is required for PHP 8.5 support due to PHPUnit 12 API changes.

**PHP Support**: 8.2, 8.3, 8.4, 8.5 (L10: 8.1-8.3, L11: 8.2-8.4, L12: 8.2-8.5)

**Rules**:
- Require `illuminate/*` packages, NEVER `laravel/framework`
- Use Pest for testing (not PHPUnit)
- Include `pint.json` with `{"preset": "laravel"}`

### Service Provider (Vanilla)

```php
class PackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/package.php', 'package');
        $this->app->singleton(PackageService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/package.php' => config_path('package.php'),
            ], 'package-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'package-migrations');

            $this->commands([PackageCommand::class]);
        }

        if (config('package.route.enabled', true)) {
            $this->registerRoutes();
        }
    }

    protected function registerRoutes(): void
    {
        Route::prefix(config('package.route.prefix', 'api'))
            ->middleware(config('package.route.middleware', []))
            ->group(fn() => $this->loadRoutesFrom(__DIR__.'/../routes/api.php'));
    }
}
```

### Traits for Eloquent Models

```php
// src/Concerns/HasFeature.php
namespace Vendor\Package\Concerns;

trait HasFeature
{
    public function initializeHasFeature(): void
    {
        // Called on model boot
    }

    public function feature(): MorphMany
    {
        $model = config('package.model', \Vendor\Package\Models\Feature::class);
        return $this->morphMany($model, 'featureable');
    }

    public function addFeature(BackedEnum $type, mixed $data = null): Model
    {
        return $this->feature()->create([
            'type' => $type->value,
            'data' => $data,
        ]);
    }

    // Query scopes
    public function scopeWithFeature($query, BackedEnum $type)
    {
        return $query->whereHas('feature', fn($q) => $q->where('type', $type->value));
    }
}
```

**Usage**: `use Vendor\Package\Concerns\HasFeature;`

### Helper Functions

```php
// src/helpers.php
if (!function_exists('package')) {
    function package(string $source, string $path): UrlBuilder
    {
        return new UrlBuilder($source, $path);
    }
}
```

### Fluent URL/API Builders

```php
class UrlBuilder implements Htmlable, Stringable
{
    protected array $options = [];

    public function __construct(protected string $source, protected string $path) {}

    public function width(int $w): static { $this->options['w'] = $w; return $this; }
    public function height(int $h): static { $this->options['h'] = $h; return $this; }
    public function format(string $f): static { $this->options['f'] = $f; return $this; }

    // Shortcut methods
    public function webp(): static { return $this->format('webp'); }

    public function url(): string
    {
        $opts = collect($this->options)->map(fn($v, $k) => "$k=$v")->implode(',');
        return "/$opts/{$this->source}/{$this->path}";
    }

    public function toHtml(): string { return $this->url(); }
    public function __toString(): string { return $this->url(); }
}
```

**Blade usage**: `<img src="{{ package('images', 'photo.jpg')->width(400)->webp() }}">`

### Configuration

```php
// config/package.php
return [
    'route' => [
        'enabled' => true,
        'prefix' => null,
        'middleware' => [],
        'name' => 'package.show',
    ],
    'model' => \Vendor\Package\Models\Item::class,
    'rate_limit' => [
        'enabled' => true,
        'max_attempts' => 10,
    ],
    'cache' => [
        'max_age' => 2592000,        // 30 days
        's_maxage' => 2592000,
        'immutable' => true,
    ],
];
```

### Migration Stubs

Use `.php.stub` for publishable migrations:

```php
// database/migrations/create_items_table.php.stub
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('package.table', 'items'), function (Blueprint $table) {
            $table->id();
            $table->morphs('itemable');
            $table->string('type');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['itemable_id', 'itemable_type']);
        });
    }
};
```

### Artisan Commands

```php
class PackageCommand extends Command
{
    protected $signature = 'package:process
        {--list : List items without processing}
        {--pretend : Show what would be processed}
        {--dry-run : Alias for --pretend}';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listItems();
        }

        $pretend = $this->option('pretend') || $this->option('dry-run');

        foreach ($this->discoverItems() as $item) {
            if ($pretend) {
                $this->line("Would process: {$item->name}");
                continue;
            }
            $this->processItem($item);
            $this->info("Processed: {$item->name}");
        }

        return self::SUCCESS;
    }
}
```

### Testing (Pest + Testbench)

```php
// tests/TestCase.php
abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
```

```php
// tests/FeatureTest.php
uses(TestCase::class);

it('processes items correctly', function () {
    $item = Item::factory()->create();

    $result = $item->process();

    expect($result)->toBeTrue();
});
```

### GitHub Actions

**tests.yaml**:
```yaml
name: Tests

on:
  push:
    branches: [main]
  pull_request:
  workflow_call:

jobs:
  tests:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.2', '8.3', '8.4', '8.5']
        laravel: ['10.*', '11.*', '12.*']
        dependency-version: [prefer-lowest, prefer-stable]
        exclude:
          # Laravel 10: PHP 8.1-8.3
          - php: '8.4'
            laravel: '10.*'
          - php: '8.5'
            laravel: '10.*'
          # Laravel 11: PHP 8.2-8.4
          - php: '8.5'
            laravel: '11.*'

    name: P${{ matrix.php }} - L${{ matrix.laravel }} - ${{ matrix.dependency-version }}

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none

      - run: |
          composer require "laravel/framework:${{ matrix.laravel }}" --no-update
          composer update --${{ matrix.dependency-version }} --prefer-dist --no-interaction

      - run: vendor/bin/pest
```

**Testbench resolution**: Don't specify testbench in the workflow. With `"orchestra/testbench": "^8.21|^9.6|^10.5"` in composer.json, composer automatically resolves the correct version based on the Laravel constraint.

**Handling prefer-lowest failures**: If `prefer-lowest` fails due to an old dependency missing a feature you need, bump the minimum version constraint (e.g., `^11.1` instead of `^11.0`). Never skip `prefer-lowest` entirely—it catches accidental dependency on newer features. Current recommended minimums:
- Testbench: `^8.21|^9.6|^10.5` (fixes static property issues in Pest)
- Laravel 12: `^12.24` (required for PHPUnit 12/Pest 4 compatibility on PHP 8.5)

**pint.yaml** (auto-fix):
```yaml
name: Fix Code Style

on:
  push:
    branches: [main]
  pull_request:

permissions:
  contents: write

jobs:
  pint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          ref: ${{ github.head_ref }}

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - run: composer install --no-interaction --prefer-dist
      - run: vendor/bin/pint

      - uses: stefanzweifel/git-auto-commit-action@v5
        with:
          commit_message: Fix code style
```

**release.yaml**: Use `/create-release-workflow` command

**Workflow rules**:
- Keep `tests.yaml` and `pint.yaml` as separate workflows, not one combined "CI" workflow
- Release workflow should call/require test and lint workflows, not duplicate their steps

### CHANGELOG.md (Keep a Changelog)

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release

[Unreleased]: https://github.com/vendor/package/compare/HEAD
```

### Path Validation (Security)

```php
class PathValidator
{
    public static function directories(array $allowed): Closure
    {
        return fn(string $path) => Str::startsWith($path, $allowed);
    }

    public static function extensions(array $allowed): Closure
    {
        return fn(string $path) => in_array(pathinfo($path, PATHINFO_EXTENSION), $allowed);
    }
}

// In service
public function resolve(string $path): void
{
    if (str_contains($path, '..')) {
        throw new InvalidPathException('Directory traversal not allowed');
    }

    $validator = config('package.validator');
    if ($validator && !$validator($path)) {
        throw new InvalidPathException('Path validation failed');
    }
}
```

### Common Patterns

**Interface-based discovery** (like Enqueue):
```php
interface Processable {
    public static function process(): void;
    public static function shouldProcess(CallbackEvent $event): CallbackEvent|bool;
}
```

**Enum-based types** (like Eventable):
```php
// config: 'types' => ['user' => UserType::class]
class TypeRegistry {
    public static function getAlias(BackedEnum $enum): string { }
    public static function getClass(string $alias): string { }
}
```

**Prune/cleanup commands**:
```php
interface Pruneable {
    public function prune(): ?PruneConfig;
}

class PruneConfig {
    public function __construct(
        public ?Carbon $before = null,
        public ?int $keep = null,
    ) {}
}
```

### Versioning (SemVer)

- **MAJOR**: Breaking changes, drop Laravel/PHP versions
- **MINOR**: New features, backward-compatible
- **PATCH**: Bug fixes only

### Common Mistakes

| Mistake | Fix |
|---------|-----|
| `use App\Models\User` | `config('package.user_model')` |
| `env('KEY')` in code | `config('package.key')` |
| `laravel/framework` require | `illuminate/*` packages only |
| PHPUnit directly | Use Pest with Testbench |
| No CHANGELOG.md | Keep a Changelog format |
| `--test` in release CI | Auto-fix + commit pattern |
| Hardcoded table names | `config('package.table')` |
| No route toggle | `'route.enabled' => true` config |
| Missing --pretend flag | Add for destructive commands |
