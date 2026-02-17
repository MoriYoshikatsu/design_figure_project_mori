<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]);

            $accountId = $this->claimGuestAccountIfPossible((int)$user->id, (string)$input['name']);

            if ($accountId <= 0) {
                $accountId = (int)DB::table('accounts')->insertGetId([
                    'account_type' => 'B2C',
                    'internal_name' => $input['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('account_user')->insert([
                    'account_id' => $accountId,
                    'user_id' => (int)$user->id,
                    'role' => 'customer',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $user;
        });
    }

    private function claimGuestAccountIfPossible(int $userId, string $userName): int
    {
        $candidateAccountIds = $this->resolveGuestCandidateAccountIds();
        if (empty($candidateAccountIds)) {
            return 0;
        }

        foreach ($candidateAccountIds as $accountId) {
            $accountId = (int)$accountId;
            if ($accountId <= 0) {
                continue;
            }

            $alreadyLinked = DB::table('account_user')
                ->where('account_id', $accountId)
                ->exists();
            if ($alreadyLinked) {
                continue;
            }

            DB::table('accounts')
                ->where('id', $accountId)
                ->update([
                    'account_type' => 'B2C',
                    'internal_name' => trim($userName) !== '' ? $userName : null,
                    'memo' => DB::raw("case when memo = 'GUEST_TEMP' then null else memo end"),
                    'updated_at' => now(),
                ]);

            DB::table('account_user')->insert([
                'account_id' => $accountId,
                'user_id' => $userId,
                'role' => 'customer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $accountId;
        }

        return 0;
    }

    private function resolveGuestCandidateAccountIds(): array
    {
        $ids = [];

        $intended = (string)(session('url.intended') ?? '');
        $path = parse_url($intended, PHP_URL_PATH);
        $path = is_string($path) ? $path : '';
        if ($path === '' && str_starts_with($intended, '/')) {
            $path = $intended;
        }

        if (preg_match('#^/quotes/(\d+)/?$#', $path, $m)) {
            $quoteAccountId = (int)DB::table('quotes')
                ->where('id', (int)$m[1])
                ->lockForUpdate()
                ->value('account_id');
            if ($quoteAccountId > 0) {
                $ids[] = $quoteAccountId;
            }
        }

        $sid = request()?->cookie('config_session_id');
        if (is_numeric($sid)) {
            $accountId = (int)DB::table('configurator_sessions')
                ->where('id', (int)$sid)
                ->lockForUpdate()
                ->value('account_id');
            if ($accountId > 0) {
                $ids[] = $accountId;
            }
        }

        return array_values(array_unique($ids));
    }
}
