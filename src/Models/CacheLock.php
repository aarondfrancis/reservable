<?php

namespace AaronFrancis\Reservable\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a cache lock record in the database.
 *
 * This model maps to the `cache_locks` table and is used to query active reservations.
 * The Reservable migration adds generated columns (is_reservation, model_type, model_id, type)
 * to enable efficient querying of reservations by model.
 *
 * @property string $key The cache lock key (primary key)
 * @property string|null $owner The lock owner identifier
 * @property int $expiration Unix timestamp when the lock expires
 * @property bool $is_reservation Whether this lock represents a reservation (generated)
 * @property string|null $model_type The morph class of the reserved model (generated)
 * @property int|null $model_id The ID of the reserved model (generated)
 * @property string|null $type The reservation type/key (generated)
 */
class CacheLock extends Model
{
    /** @var string */
    protected $table = 'cache_locks';

    /** @var string */
    protected $primaryKey = 'key';

    /** @var bool */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $timestamps = false;

    /** @var array<int, string> */
    protected $guarded = [];
}
