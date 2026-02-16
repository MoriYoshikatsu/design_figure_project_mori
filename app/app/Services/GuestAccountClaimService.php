<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class GuestAccountClaimService
{
    public function claimQuoteForUser(int $quoteId, int $userId, ?int $cookieSessionId = null): void
    {
        if ($quoteId <= 0 || $userId <= 0) {
            return;
        }

        DB::transaction(function () use ($quoteId, $userId, $cookieSessionId): void {
            $targetAccountId = $this->resolveOrCreateUserAccountId($userId);
            if ($targetAccountId <= 0) {
                return;
            }

            if ($cookieSessionId && $cookieSessionId > 0) {
                $this->claimSessionIfUnclaimed($cookieSessionId, $targetAccountId);
            }

            $quote = DB::table('quotes')
                ->where('id', $quoteId)
                ->lockForUpdate()
                ->first(['id', 'account_id', 'session_id']);
            if (!$quote) {
                return;
            }

            $quoteAccountId = (int)$quote->account_id;
            if ($quoteAccountId <= 0) {
                return;
            }

            if ($this->accountBelongsToUser($quoteAccountId, $userId)) {
                if (!empty($quote->session_id)) {
                    $this->claimSessionIfUnclaimed((int)$quote->session_id, $targetAccountId);
                }
                return;
            }

            if ($this->accountLinkedToAnyUser($quoteAccountId)) {
                // 既に別ユーザーへ紐付いているアカウントは奪取しない
                return;
            }

            DB::table('quotes')
                ->where('account_id', $quoteAccountId)
                ->update([
                    'account_id' => $targetAccountId,
                    'updated_at' => now(),
                ]);

            DB::table('configurator_sessions')
                ->where('account_id', $quoteAccountId)
                ->update([
                    'account_id' => $targetAccountId,
                    'updated_at' => now(),
                ]);

            $this->cleanupGuestAccountIfUnused($quoteAccountId);
        });
    }

    private function claimSessionIfUnclaimed(int $sessionId, int $targetAccountId): void
    {
        if ($sessionId <= 0 || $targetAccountId <= 0) {
            return;
        }

        $session = DB::table('configurator_sessions')
            ->where('id', $sessionId)
            ->lockForUpdate()
            ->first(['id', 'account_id']);
        if (!$session) {
            return;
        }

        $accountId = (int)$session->account_id;
        if ($accountId <= 0 || $accountId === $targetAccountId) {
            return;
        }

        if ($this->accountLinkedToAnyUser($accountId)) {
            return;
        }

        DB::table('configurator_sessions')
            ->where('id', $sessionId)
            ->update([
                'account_id' => $targetAccountId,
                'updated_at' => now(),
            ]);
    }

    private function accountLinkedToAnyUser(int $accountId): bool
    {
        return DB::table('account_user')
            ->where('account_id', $accountId)
            ->exists();
    }

    private function accountBelongsToUser(int $accountId, int $userId): bool
    {
        return DB::table('account_user')
            ->where('account_id', $accountId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function resolveOrCreateUserAccountId(int $userId): int
    {
        $accountId = (int)DB::table('account_user')
            ->where('user_id', $userId)
            ->orderByRaw("
                case role
                    when 'customer' then 1
                    when 'admin' then 2
                    when 'sales' then 3
                    else 9
                end
            ")
            ->orderBy('account_id')
            ->value('account_id');

        if ($accountId > 0) {
            return $accountId;
        }

        $userName = (string)(DB::table('users')->where('id', $userId)->value('name') ?? '');
        $accountId = (int)DB::table('accounts')->insertGetId([
            'account_type' => 'B2C',
            'internal_name' => trim($userName) !== '' ? $userName : null,
            'created_at' => now(),
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

    private function cleanupGuestAccountIfUnused(int $accountId): void
    {
        if ($accountId <= 0) {
            return;
        }

        $account = DB::table('accounts')
            ->where('id', $accountId)
            ->first(['id', 'memo']);
        if (!$account) {
            return;
        }

        if (trim((string)($account->memo ?? '')) !== 'GUEST_TEMP') {
            return;
        }

        $hasUser = DB::table('account_user')->where('account_id', $accountId)->exists();
        $hasQuote = DB::table('quotes')->where('account_id', $accountId)->exists();
        $hasSession = DB::table('configurator_sessions')->where('account_id', $accountId)->exists();
        if ($hasUser || $hasQuote || $hasSession) {
            return;
        }

        DB::table('accounts')->where('id', $accountId)->delete();
    }
}

