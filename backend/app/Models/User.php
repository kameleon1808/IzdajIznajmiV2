<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    public const GENDERS = ['muski', 'zenski', 'ne_zelim_da_kazem'];

    public const EMPLOYMENT_STATUSES = ['zaposlen', 'nezaposlen', 'student', 'penzioner'];

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'full_name',
        'date_of_birth',
        'gender',
        'residential_address',
        'employment_status',
        'email',
        'phone',
        'role',
        'password',
        'address_book',
        'email_verified',
        'phone_verified',
        'address_verified',
        'is_suspicious',
        'verification_status',
        'verified_at',
        'verification_notes',
        'badge_override_json',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_totp_secret',
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
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'address_book' => 'array',
            'email_verified' => 'boolean',
            'phone_verified' => 'boolean',
            'address_verified' => 'boolean',
            'is_suspicious' => 'boolean',
            'mfa_enabled' => 'boolean',
            'mfa_confirmed_at' => 'datetime',
            'verified_at' => 'datetime',
            'badge_override_json' => 'array',
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

    public function viewingSlots()
    {
        return $this->hasMany(ViewingSlot::class, 'landlord_id');
    }

    public function viewingRequestsAsSeeker()
    {
        return $this->hasMany(ViewingRequest::class, 'seeker_id');
    }

    public function viewingRequestsAsLandlord()
    {
        return $this->hasMany(ViewingRequest::class, 'landlord_id');
    }

    public function kycSubmissions()
    {
        return $this->hasMany(KycSubmission::class);
    }

    public function rentalTransactionsAsLandlord()
    {
        return $this->hasMany(RentalTransaction::class, 'landlord_id');
    }

    public function rentalTransactionsAsSeeker()
    {
        return $this->hasMany(RentalTransaction::class, 'seeker_id');
    }

    public function mfaRecoveryCodes()
    {
        return $this->hasMany(MfaRecoveryCode::class);
    }

    public function trustedDevices()
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function userSessions()
    {
        return $this->hasMany(UserSession::class);
    }

    public function fraudSignals()
    {
        return $this->hasMany(FraudSignal::class);
    }

    public function fraudScore()
    {
        return $this->hasOne(FraudScore::class);
    }

    public function landlordMetric()
    {
        return $this->hasOne(LandlordMetric::class, 'landlord_id');
    }
}
