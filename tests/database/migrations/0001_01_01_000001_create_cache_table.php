<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create cache table
        DB::statement(<<<'SQL'
            CREATE TABLE cache (
                key TEXT PRIMARY KEY,
                value TEXT,
                expiration INTEGER
            )
        SQL);

        // Create cache_locks table with generated columns for SQLite
        // The key format is: {prefix}reservation:{model_type}:{model_id}:{type}
        // Examples:
        //   laravel_cache_reservation:App\Models\User:1:processing
        //   reservation:users:42:uploading (no prefix)
        //
        // We strip everything before 'reservation:' to get the normalized key,
        // then parse the remaining parts.
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
                            -- Get everything after 'reservation:' up to the next ':'
                            -- normalized = substr starting from 'reservation:'
                            -- model_type = first segment after 'reservation:'
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
                            -- Get the second segment (after model_type:)
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
                            -- Get everything after model_id:
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

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS cache');
        DB::statement('DROP TABLE IF EXISTS cache_locks');
    }
};
