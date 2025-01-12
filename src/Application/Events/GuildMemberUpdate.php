<?php

namespace Chorume\Application\Events;

use Discord\Discord;
use Chorume\Repository\User;
use Chorume\Repository\UserChangeHistory;
use Discord\Parts\User\Member;

class GuildMemberUpdate
{
    public function __invoke(Member $message): void
    {
        $this->handle($message);
    }

    public function __construct(
        private Discord $discord,
        private $config,
        private $redis,
        private User $userRepository,
        private UserChangeHistory $userChangeHistoryRepository
    ) {
    }

    public function handle(Member $member): void
    {
        $avatarPath = __DIR__ . "/../../../storage/avatars/{$member->user->id}/";

        if (!is_dir($avatarPath)) {
            mkdir($avatarPath);
        }

        $avatarUrlQuery = parse_url($member->user->avatar, PHP_URL_QUERY);
        $filename = basename($member->user->avatar, '?' . $avatarUrlQuery);

        $user = $this->userRepository->getByDiscordId($member->user->id);

        if (empty($user)) {
            $this->discord->getLogger()->debug("Member Update: User not found, creating new user");
            $userId = $this->userRepository->create([
                'discord_user_id' => $member->user->id,
                'discord_username' => $member->user->username,
                'discord_global_name' => $member->user->global_name,
                'discord_avatar' => $member->user->avatar,
                'discord_joined_at' => $member->joined_at,
                'received_initial_coins' => 0
            ]);

            $this->userChangeHistoryRepository->create(
                $userId,
                $member->user->username,
                $member->user->global_name,
                $filename
            );

            copy($member->user->avatar, $avatarPath . $filename);   

            return;
        }

        if ($user[0]['discord_avatar'] !== $member->user->avatar) {
            $this->discord->getLogger()->debug("Member Update: Updated avatar");

            $this->userRepository->update($user[0]['id'], [
                'discord_avatar' => $member->user->avatar
            ]);
            $this->userChangeHistoryRepository->create(
                $user[0]['id'],
                $member->user->username,
                $member->user->global_name,
                $filename
            );

            copy($member->user->avatar, $avatarPath . $filename);
        }

        if (
            $user[0]['discord_username'] !== $member->user->username
            || $user[0]['discord_global_name'] !== $member->user->global_name
        ) {
            $this->discord->getLogger()->debug("Member Update: User updated");
            $this->userRepository->update($user[0]['id'], [
                'discord_username' => $member->user->username,
                'discord_global_name' => $member->user->global_name,
                'discord_joined_at' => $member->joined_at
            ]);
            $this->userChangeHistoryRepository->create(
                $user[0]['id'],
                $member->user->username,
                $member->user->global_name,
                $filename
            );
        }
    }
}
