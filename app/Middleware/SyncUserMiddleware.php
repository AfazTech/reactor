<?php
namespace App\Middleware;

use Reactor\Attributes\Middleware;
use Reactor\Attributes\OnUpdate;
use Reactor\Enums\MiddlewareMode;
use Reactor\Core\Traits\FromIdExtractor;
use Reactor\Contracts\LoggerInterface;
use Reactor\Contracts\MiddlewareInterface;
use App\Contracts\Repository\UserRepositoryInterface;
use Reactor\Core\EventDispatcher;
use Reactor\Events;

/**
 * Middleware that syncs user data and dispatches registration event.
 *
 * User extraction is delegated to the shared FromIdExtractor trait so
 * that handler code and middleware agree on how a user is located
 * inside every supported update type.
 *
 * Also maintains the user's activity metadata:
 *   - Any incoming interaction sets status = 1 (active) and refreshes
 *     last_interaction_at.
 *   - A block event (my_chat_member in a private chat with 'kicked'
 *     status) sets status = 0 but still refreshes last_interaction_at,
 *     because blocking is itself an interaction.
 */
#[Middleware(priority: 50, mode: MiddlewareMode::GLOBAL)]
#[OnUpdate('any')]
class SyncUserMiddleware implements MiddlewareInterface
{
    use FromIdExtractor;

    private UserRepositoryInterface $userRepository;
    private LoggerInterface $logger;
    private EventDispatcher $dispatcher;

    public function __construct(
        UserRepositoryInterface $userRepository,
        LoggerInterface $logger,
        EventDispatcher $dispatcher
    ) {
        $this->userRepository = $userRepository;
        $this->logger = $logger;
        $this->dispatcher = $dispatcher;
    }

    public function handle(array $update): bool
    {
        // Block / unblock events arrive as my_chat_member updates where
        // the chat is the private conversation between the user and the
        // bot. They must be handled separately because the actor of the
        // update is not necessarily the bot's peer.
        $myChatMember = $update['my_chat_member'] ?? null;
        if (is_array($myChatMember) && ($myChatMember['chat']['type'] ?? null) === 'private') {
            $this->handlePrivateChatMemberUpdate($myChatMember);
            return false;
        }

        $user = $this->extractUser($update);
        if (!$user) {
            return false;
        }

        $userId = (int) $user['id'];
        $existing = $this->userRepository->getUser($userId);
        $isNew = ($existing === null);

        $this->userRepository->syncUser(
            $userId,
            $user['username']   ?? null,
            $user['first_name'] ?? null,
            $user['last_name']  ?? null
        );

        // Any incoming interaction counts as the user being active.
        $this->userRepository->setStatus($userId, 1);
        $this->userRepository->setLastInteraction($userId);

        if ($isNew) {
            $this->dispatcher->dispatch(Events::USER_REGISTERED, $userId, $update);
            $this->logger->debug("New user registered", ['user_id' => $userId]);
        }

        return false; // Continue processing
    }

    /**
     * Set the user's status based on the bot's membership state inside
     * their private chat. Telegram delivers 'kicked' when the user
     * blocks the bot and 'member' when they unblock it.
     *
     * Both transitions are interactions, so last_interaction_at is
     * refreshed in either case.
     */
    private function handlePrivateChatMemberUpdate(array $myChatMember): void
    {
        $userId = $myChatMember['chat']['id'] ?? null;
        if ($userId === null) {
            return;
        }

        $userId = (int) $userId;
        $newStatus = $myChatMember['new_chat_member']['status'] ?? null;

        if ($newStatus === 'kicked') {
            $this->userRepository->setStatus($userId, 0);
            $this->userRepository->setLastInteraction($userId);
            $this->logger->debug('User blocked the bot', ['user_id' => $userId]);
            return;
        }

        if ($newStatus !== null) {
            $this->userRepository->setStatus($userId, 1);
            $this->userRepository->setLastInteraction($userId);
            $this->logger->debug('User unblocked the bot', ['user_id' => $userId]);
        }
    }
}
