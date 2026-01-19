<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\BookingRequest;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Rating;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'full_name',
        'email',
        'phone',
        'role',
        'password',
        'address_book',
        'email_verified',
        'phone_verified',
        'address_verified',
        'is_suspicious',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'address_book' => 'array',
            'email_verified' => 'boolean',
            'phone_verified' => 'boolean',
            'address_verified' => 'boolean',
            'is_suspicious' => 'boolean',
        ];
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'owner_id');
    }

    public function bookingRequestsAsTenant()
    {
        return $this->hasMany(BookingRequest::class, 'tenant_id');
    }

    public function bookingRequestsAsLandlord()
    {
        return $this->hasMany(BookingRequest::class, 'landlord_id');
    }

    public function conversationsAsTenant()
    {
        return $this->hasMany(Conversation::class, 'tenant_id');
    }

    public function conversationsAsLandlord()
    {
        return $this->hasMany(Conversation::class, 'landlord_id');
    }

    public function applicationsAsSeeker()
    {
        return $this->hasMany(Application::class, 'seeker_id');
    }

    public function applicationsAsLandlord()
    {
        return $this->hasMany(Application::class, 'landlord_id');
    }

    public function ratingsGiven()
    {
        return $this->hasMany(Rating::class, 'rater_id');
    }

    public function ratingsReceived()
    {
        return $this->hasMany(Rating::class, 'ratee_id');
    }
}
