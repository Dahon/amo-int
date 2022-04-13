<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AmoTokens
 * @var string $access_token
 * @var string $refresh_token
 * @var integer $expires_in
 * @var integer $expired_time
 */

class AmoTokens extends Model
{
    protected $fillable = [
        'access_token', 'refresh_token', 'expires_in', 'expired_time', 'company_id',
    ];

}
