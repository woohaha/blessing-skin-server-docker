<?php

namespace App\Models;

use App\Models\Concerns\HasPassword;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Lorisleiva\LaravelSearchString\Concerns\SearchString;

/**
 * @property int         $uid
 * @property string      $email
 * @property string      $password
 * @property string      $nickname
 * @property string|null $locale
 * @property int         $avatar
 * @property int         $score
 * @property int         $permission
 * @property string      $ip
 * @property bool        $is_dark_mode
 * @property string      $last_sign_at
 * @property string      $register_at
 * @property bool        $verified
 * @property string      $player_name
 * @property Collection  $players
 * @property Collection  $closet
 */
class User extends Authenticatable
{
    use Notifiable;
    use HasFactory;
    use HasPassword;
    use HasApiTokens;
    use SearchString;

    public const BANNED = -1;
    public const NORMAL = 0;
    public const ADMIN = 1;
    public const SUPER_ADMIN = 2;

    protected $primaryKey = 'uid';
    public $timestamps = false;

    protected $fillable = [
        'email', 'nickname', 'avatar', 'score', 'permission', 'last_sign_at',
    ];

    protected $casts = [
        'uid' => 'integer',
        'score' => 'integer',
        'avatar' => 'integer',
        'permission' => 'integer',
        'verified' => 'bool',
        'is_dark_mode' => 'bool',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $searchStringColumns = [
        'uid',
        'email' => ['searchable' => true],
        'nickname' => ['searchable' => true],
        'avatar', 'score', 'permission', 'ip',
        'last_sign_at' => ['date' => true],
        'register_at' => ['date' => true],
        'verified' => ['boolean' => true],
        'is_dark_mode' => ['boolean' => true],
    ];

    public function isAdmin(): bool
    {
        return $this->permission >= static::ADMIN;
    }

    public function closet()
    {
        return $this->belongsToMany(Texture::class, 'user_closet')->withPivot('item_name');
    }

    public function getPlayerNameAttribute()
    {
        $player = $this->players->first();

        return $player ? $player->name : '';
    }

    public function setPlayerNameAttribute($value)
    {
        $player = $this->players->first();
        if ($player) {
            $player->name = $value;
            $player->save();
        }
    }

    public function delete()
    {
        Player::where('uid', $this->uid)->delete();

        return parent::delete();
    }

    public function players()
    {
        return $this->hasMany(Player::class, 'uid');
    }

    public function getAuthIdentifier()
    {
        return $this->uid;
    }
}
