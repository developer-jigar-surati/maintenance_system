<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSociety;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A landmark on the site plan that is not a building: the gate, the garden,
 * the parking bay. Without them a plan is a row of anonymous rectangles and
 * nobody can tell which way round it is.
 */
class SiteFeature extends Model
{
    use BelongsToSociety, HasFactory;

    protected $guarded = ['id'];

    public const KINDS = [
        'gate' => 'Gate',
        'parking' => 'Parking',
        'garden' => 'Garden',
        'clubhouse' => 'Clubhouse',
        'pool' => 'Swimming pool',
        'playground' => 'Play area',
        'temple' => 'Temple',
        'sports' => 'Sports court',
        'utility' => 'Utility',
        'road' => 'Road',
        'other' => 'Other',
    ];

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? 'Other';
    }

    /** The icon a plan draws it with. */
    public function icon(): string
    {
        return match ($this->kind) {
            'gate' => 'shield',
            'parking' => 'car',
            'garden', 'playground' => 'tree',
            'clubhouse', 'sports' => 'building',
            'pool' => 'droplet',
            'road' => 'road',
            default => 'map-pin',
        };
    }
}
