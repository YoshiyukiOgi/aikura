<?php

namespace App\Models\Retail;

use Illuminate\Database\Eloquent\Model;

abstract class RetailModel extends Model
{
    public function getConnectionName(): ?string
    {
        return config('retail.database.connection', 'retail');
    }
}
