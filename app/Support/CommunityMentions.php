<?php

namespace App\Support;

use App\Models\User;

class CommunityMentions
{
    /**
     * @return array<int, array{id: int, username: string}>
     */
    public static function extract(?string ...$texts): array
    {
        $combined = implode(' ', array_filter($texts));
        if ($combined === '') {
            return [];
        }

        preg_match_all('/@([a-zA-Z0-9_]+)/', $combined, $matches);
        $usernames = array_unique($matches[1] ?? []);
        if ($usernames === []) {
            return [];
        }

        return User::query()
            ->whereIn('username', $usernames)
            ->get(['id', 'username'])
            ->map(fn (User $user) => ['id' => $user->id, 'username' => $user->username])
            ->values()
            ->all();
    }
}
