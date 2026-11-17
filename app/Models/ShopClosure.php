<?php

namespace App\Models;

use Database\Factories\ShopClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['closed_on', 'reason'])]
class ShopClosure extends Model
{
    /** @use HasFactory<ShopClosureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['closed_on' => 'date'];
    }
}
