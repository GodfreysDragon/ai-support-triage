<?php

namespace App\Demo;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a throwaway account for one visitor, seeded with sample tickets.
 *
 * Expired demo accounts are pruned here as well as by the scheduled
 * demo:prune command, so hosts without a scheduler still clean up.
 */
final class CreateDemoAccount
{
    public function __invoke(): User
    {
        self::pruneExpired();

        return DB::transaction(function (): User {
            $user = new User([
                'name' => 'Demo Visitor',
                'email' => 'demo-'.Str::lower((string) Str::ulid()).'@demo.invalid',
                'password' => Str::password(32),
            ]);
            $user->forceFill(['is_demo' => true, 'email_verified_at' => now()])->save();

            SampleTickets::seedFor($user);

            return $user;
        });
    }

    /**
     * Delete demo accounts past their lifetime; their tickets cascade.
     *
     * @return int The number of accounts deleted.
     */
    public static function pruneExpired(): int
    {
        return User::query()->expiredDemo()->delete();
    }
}
