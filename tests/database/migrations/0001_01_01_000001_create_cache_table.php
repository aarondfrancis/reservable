<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Create cache table (standard Laravel cache table)
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        // Create cache_locks table with generated columns for parsing reservation keys
        // The key format is: {prefix}reservation:{model_type}:{model_id}:{type}
        if ($driver === 'sqlite') {
            $this->createSqliteCacheLocksTable();
        } elseif ($driver === 'pgsql') {
            $this->createPostgresCacheLocksTable();
        } elseif ($driver === 'mysql') {
            $this->createMysqlCacheLocksTable();
        }
    }

    protected function createSqliteCacheLocksTable(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE cache_locks (
                key TEXT PRIMARY KEY,
                owner TEXT,
                expiration INTEGER,
                is_reservation INTEGER GENERATED ALWAYS AS (
                    key LIKE '%reservation:%'
                ) STORED,
                model_type TEXT GENERATED ALWAYS AS (
                    CASE
                        WHEN key LIKE '%reservation:%' THEN
                            substr(
                                substr(key, instr(key, 'reservation:') + 12),
                                1,
                                instr(substr(key, instr(key, 'reservation:') + 12), ':') - 1
                            )
                        ELSE NULL
                    END
                ) STORED,
                model_id INTEGER GENERATED ALWAYS AS (
                    CASE
                        WHEN key LIKE '%reservation:%' THEN
                            CAST(
                                substr(
                                    substr(key, instr(key, 'reservation:') + 12),
                                    instr(substr(key, instr(key, 'reservation:') + 12), ':') + 1,
                                    instr(
                                        substr(
                                            substr(key, instr(key, 'reservation:') + 12),
                                            instr(substr(key, instr(key, 'reservation:') + 12), ':') + 1
                                        ),
                                        ':'
                                    ) - 1
                                ) AS INTEGER
                            )
                        ELSE NULL
                    END
                ) STORED,
                type TEXT GENERATED ALWAYS AS (
                    CASE
                        WHEN key LIKE '%reservation:%' THEN
                            substr(
                                substr(key, instr(key, 'reservation:') + 12),
                                instr(substr(key, instr(key, 'reservation:') + 12), ':') + 1 +
                                instr(
                                    substr(
                                        substr(key, instr(key, 'reservation:') + 12),
                                        instr(substr(key, instr(key, 'reservation:') + 12), ':') + 1
                                    ),
                                    ':'
                                )
                            )
                        ELSE NULL
                    END
                ) STORED
            )
        SQL);
    }

    protected function createPostgresCacheLocksTable(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE cache_locks (
                key TEXT PRIMARY KEY,
                owner TEXT,
                expiration INTEGER,
                is_reservation BOOLEAN GENERATED ALWAYS AS (
                    key LIKE '%reservation:%'
                ) STORED,
                model_type TEXT GENERATED ALWAYS AS (
                    CASE
                        WHEN key LIKE '%reservation:%' THEN
                            split_part(substring(key FROM position('reservation:' IN key) + 12), ':', 1)
                        ELSE NULL
                    END
                ) STORED,
                model_id INTEGER GENERATED ALWAYS AS (
                    CASE
                        WHEN key LIKE '%reservation:%' THEN
                            CAST(split_part(substring(key FROM position('reservation:' IN key) + 12), ':', 2) AS INTEGER)
                        ELSE NULL
                    END
                ) STORED,
                type TEXT GENERATED ALWAYS AS (
                    CASE
                        WHEN key LIKE '%reservation:%' THEN
                            split_part(substring(key FROM position('reservation:' IN key) + 12), ':', 3)
                        ELSE NULL
                    END
                ) STORED
            )
        SQL);
    }

    protected function createMysqlCacheLocksTable(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE cache_locks (
                `key` VARCHAR(255) PRIMARY KEY,
                owner VARCHAR(255),
                expiration INTEGER,
                is_reservation BOOLEAN AS (
                    `key` LIKE '%reservation:%'
                ) STORED,
                model_type VARCHAR(255) AS (
                    CASE
                        WHEN `key` LIKE '%reservation:%' THEN
                            SUBSTRING_INDEX(SUBSTRING(`key`, LOCATE('reservation:', `key`) + 12), ':', 1)
                        ELSE NULL
                    END
                ) STORED,
                model_id INTEGER AS (
                    CASE
                        WHEN `key` LIKE '%reservation:%' THEN
                            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING(`key`, LOCATE('reservation:', `key`) + 12), ':', 2), ':', -1) AS UNSIGNED)
                        ELSE NULL
                    END
                ) STORED,
                type VARCHAR(255) AS (
                    CASE
                        WHEN `key` LIKE '%reservation:%' THEN
                            SUBSTRING_INDEX(SUBSTRING(`key`, LOCATE('reservation:', `key`) + 12), ':', -1)
                        ELSE NULL
                    END
                ) STORED
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
