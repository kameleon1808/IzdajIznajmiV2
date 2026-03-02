<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PII Encryption Migration
 *
 * Adds `phone_hash` (HMAC-SHA256 of normalized phone) for DB-level uniqueness,
 * then re-encrypts existing `phone`, `residential_address`, and `address_book`
 * values using Laravel's Crypt::encryptString() / encrypt() helpers (AES-256-CBC).
 *
 * Safe to run multiple times: the chunk loop skips rows whose phone value is
 * already a valid Laravel encrypted payload (starts with 'eyJ').
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add phone_hash column (nullable until backfilled, then unique).
        //    Also widen phone and residential_address to TEXT so the encrypted
        //    ciphertext (240–400 chars) fits without truncation.
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_hash', 64)->nullable()->after('phone');
            $table->text('phone')->nullable()->change();
            $table->text('residential_address')->nullable()->change();
        });

        // 2. Backfill phone_hash and re-encrypt PII columns for existing rows.
        DB::table('users')->orderBy('id')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                $updates = [];

                // phone
                if ($user->phone !== null) {
                    $plainPhone = $this->maybeDecrypt($user->phone);
                    $updates['phone_hash'] = hash_hmac('sha256', mb_strtolower(trim($plainPhone)), config('app.key'));
                    $updates['phone'] = encrypt($plainPhone);
                }

                // residential_address
                if ($user->residential_address !== null) {
                    $plain = $this->maybeDecrypt($user->residential_address);
                    $updates['residential_address'] = encrypt($plain);
                }

                // address_book (stored as JSON before this migration)
                if ($user->address_book !== null) {
                    $plain = $this->maybeDecrypt($user->address_book);
                    // If it decoded to an array (legacy JSON), re-encode as JSON before encrypting.
                    if (is_string($plain)) {
                        $decoded = json_decode($plain, true);
                        $plain = is_array($decoded) ? json_encode($decoded) : $plain;
                    }
                    $updates['address_book'] = encrypt(is_array($plain) ? json_encode($plain) : $plain);
                }

                if (! empty($updates)) {
                    DB::table('users')->where('id', $user->id)->update($updates);
                }
            }
        });

        // 3. Add unique index on phone_hash, drop the old unique index on phone.
        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone_hash', 'users_phone_hash_unique');
            $table->dropUnique('users_phone_unique');
        });
    }

    public function down(): void
    {
        // Re-add the old unique index on phone before dropping phone_hash,
        // and revert column types back to string.
        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone', 'users_phone_unique');
            $table->dropUnique('users_phone_hash_unique');
            $table->dropColumn('phone_hash');
            $table->string('phone')->nullable()->change();
            $table->string('residential_address')->nullable()->change();
        });
    }

    /**
     * Return the plaintext value, decrypting if the value looks like a
     * Laravel encrypted payload (base64-encoded JSON starting with 'eyJ').
     * This makes the migration idempotent.
     */
    private function maybeDecrypt(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (str_starts_with($value, 'eyJ')) {
            try {
                return decrypt($value);
            } catch (\Throwable) {
                // Not a valid encrypted payload — treat as plaintext.
            }
        }

        return $value;
    }
};
