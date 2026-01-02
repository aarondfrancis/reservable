<?php

use AaronFrancis\Reservable\Tests\Models\TestModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadReservableMigrationStub(): object
{
    return require __DIR__.'/../database/migrations/add_reservation_columns_to_cache_locks_table.php.stub';
}

describe('migration stub', function () {
    it('adds generated columns, parses keys, and can roll back', function () {
        Schema::dropIfExists('cache_locks');

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner')->nullable();
            $table->integer('expiration')->nullable();
        });

        $migration = loadReservableMigrationStub();
        $migration->up();

        foreach (['is_reservation', 'model_type', 'model_id', 'type'] as $column) {
            expect(Schema::hasColumn('cache_locks', $column))->toBeTrue();
        }

        $modelType = TestModel::class;
        $key = "prefix_reservation:{$modelType}:123:processing";

        DB::table('cache_locks')->insert([
            'key' => $key,
            'owner' => 'owner',
            'expiration' => now()->addMinute()->timestamp,
        ]);

        $row = DB::table('cache_locks')->where('key', $key)->first();

        expect((bool) $row->is_reservation)->toBeTrue();
        expect($row->model_type)->toBe($modelType);
        expect((int) $row->model_id)->toBe(123);
        expect($row->type)->toBe('processing');

        $migration->down();

        foreach (['is_reservation', 'model_type', 'model_id', 'type'] as $column) {
            expect(Schema::hasColumn('cache_locks', $column))->toBeFalse();
        }
    });

    it('throws for unsupported drivers', function () {
        $migration = loadReservableMigrationStub();

        $originalSchema = Schema::getFacadeRoot();

        $fakeConnection = new class {
            public function getDriverName(): string
            {
                return 'sqlsrv';
            }
        };

        $fakeSchema = new class($fakeConnection) {
            public function __construct(private object $connection)
            {
            }

            public function getConnection(): object
            {
                return $this->connection;
            }
        };

        Schema::swap($fakeSchema);

        try {
            expect(fn () => $migration->up())->toThrow(RuntimeException::class);
        } finally {
            Schema::swap($originalSchema);
        }
    });
});
