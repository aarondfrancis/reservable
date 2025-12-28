<?php

namespace AaronFrancis\Reservable\Tests\Models;

use AaronFrancis\Reservable\Reservable;
use Illuminate\Database\Eloquent\Model;

class AnotherTestModel extends Model
{
    use Reservable;

    protected $table = 'another_test_models';

    protected $guarded = [];
}
