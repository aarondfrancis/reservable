<?php

namespace AaronFrancis\Reservable\Tests\Models;

use AaronFrancis\Reservable\Concerns\Reservable;
use Illuminate\Database\Eloquent\Model;

class TestModel extends Model
{
    use Reservable;

    protected $table = 'test_models';

    protected $guarded = [];
}
