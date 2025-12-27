---
paths: "**/*.php"
---

# Laravel Package Rules

### Structure

```
package-name/
├── config/package-name.php
├── database/{factories,migrations}/
├── resources/{lang,views}/
├── routes/
├── src/
│   ├── Commands/
│   ├── Facades/
│   └── PackageNameServiceProvider.php
├── tests/
├── composer.json
├── CHANGELOG.md
└── README.md
```

### composer.json

```json
{
    "name": "vendor/package-name",
    "require": {
        "php": "^8.2",
        "illuminate/contracts": "^10.0|^11.0",
        "illuminate/support": "^10.0|^11.0"
    },
    "require-dev": {
        "orchestra/testbench": "^8.0|^9.0"
    },
    "autoload": {
        "psr-4": { "Vendor\\Package\\": "src/" }
    },
    "extra": {
        "laravel": {
            "providers": ["Vendor\\Package\\PackageServiceProvider"],
            "aliases": { "Package": "Vendor\\Package\\Facades\\Package" }
        }
    }
}
```

**Rules**:
- Require `illuminate/*` packages, NEVER `laravel/framework`
- Use `^10.0|^11.0` for multi-version support
- Match PHP to lowest Laravel (L10=8.1+, L11=8.2+)

### Service Provider (spatie/laravel-package-tools)

```php
class PackageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('package-name')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_tables')
            ->hasCommand(InstallCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(Contract::class, Implementation::class);
    }
}
```

**Method placement**:

| `register()` / `packageRegistered()` | `boot()` / `packageBooted()` |
|--------------------------------------|------------------------------|
| Container bindings | Routes, views, translations |
| Merge config | Publish files |
| | Commands (wrap in `runningInConsole()`) |

### Configuration

```php
// config/package.php — NEVER use closures (breaks config:cache)
return [
    'model' => \Vendor\Package\Models\Item::class,
    'table_name' => 'items',
    'cache_ttl' => env('PACKAGE_CACHE_TTL', 3600),
];
```

**Rules**:
- Always provide defaults: `config('package.timeout', 30)`
- Never `env()` in package code — use `config()`
- `mergeConfigFrom()` only merges first-level arrays

### Swappable Models

```php
// config
'models' => ['item' => \Vendor\Package\Models\Item::class],

// Model — configurable table
public function getTable(): string {
    return config('package.table_names.items', parent::getTable());
}

// Usage
$modelClass = config('package.models.item');
$items = $modelClass::query()->get();
```

Define contracts for replaceable models:
```php
interface Item { public function scopeActive($query); }
```

### Facades

```php
class PackageFacade extends Facade {
    protected static function getFacadeAccessor(): string { return 'package-name'; }
}

// Register both facade accessor AND contract
$this->app->singleton('package-name', fn($app) => new Service($app['config']['package']));
$this->app->bind(PackageContract::class, fn($app) => $app->make('package-name'));
```

### Migrations

```php
// Publishable migrations
$this->publishesMigrations([
    __DIR__.'/../database/migrations' => database_path('migrations'),
], 'package-migrations');

// Configurable table names in migration
Schema::create(config('package.table_names.items'), function (Blueprint $table) {});
```

### Testing (Orchestra Testbench)

```php
abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array {
        return [PackageServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:',
        ]);
    }

    protected function defineDatabaseMigrations(): void {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
```

### GitHub Actions CI

```yaml
strategy:
  matrix:
    php: [8.2, 8.3]
    laravel: [10.*, 11.*]
    include:
      - laravel: 10.*
        testbench: 8.*
      - laravel: 11.*
        testbench: 9.*
steps:
  - run: composer require "laravel/framework:${{ matrix.laravel }}" "orchestra/testbench:${{ matrix.testbench }}" --no-update
  - run: composer update --prefer-stable
  - run: vendor/bin/phpunit
```

### Blade Components

```php
// Register
Blade::componentNamespace('Vendor\\Package\\Components', 'package');

// Usage
<x-package::alert type="warning" />
return view('package::dashboard');
```

### Install Command

```php
class InstallCommand extends Command {
    protected $signature = 'package:install';

    public function handle(): int {
        $this->call('vendor:publish', ['--tag' => 'package-config']);
        if ($this->confirm('Run migrations?', true)) $this->call('migrate');
        return self::SUCCESS;
    }
}
```

### Factories

```php
// Model
protected static function newFactory() {
    return \Vendor\Package\Database\Factories\ItemFactory::new();
}

// composer.json autoload
"Vendor\\Package\\Database\\Factories\\": "database/factories/"
```

### Versioning (SemVer)

- **MAJOR**: Breaking changes, drop Laravel/PHP versions
- **MINOR**: New features, backward-compatible
- **PATCH**: Bug fixes only

**Breaking changes**: Remove/rename public methods, change signatures, change config structure, change DB schema, drop version support

### Extension Points

**Events** for key actions:
```php
event(new ItemProcessed($item, $results));
```

**Action classes** for swappable logic:
```php
// config
'actions' => ['process' => ProcessAction::class],

// Usage
app(config('package.actions.process'))->execute($item);
```

**Macros** with unique names:
```php
Collection::macro('toUpperKeys', fn() => $this->mapWithKeys(fn($v, $k) => [strtoupper($k) => $v]));
```

### Common Mistakes

| Mistake | Fix |
|---------|-----|
| `use App\Models\User` | `config('package.user_model')` |
| `env('KEY')` in code | `config('package.key')` |
| Hardcoded paths | `storage_path()`, `config_path()` |
| `$this->app->bind('user', ...)` | Namespaced: `'vendor.package.user'` |
| Closures in config | Class references |
| Publishing all in one tag | Separate: `config`, `migrations`, `views` |
| Assuming auth structure | Check existence, use interfaces |
| No default config values | Always `config('key', 'default')` |
| `laravel/framework` require | `illuminate/*` packages only |
| `final class` on extendable | Allow extension |
