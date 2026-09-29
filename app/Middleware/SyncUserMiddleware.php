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

        if ($isNew) {
            $this->dispatcher->dispatch(Events::USER_REGISTERED, $userId, $update);
            $this->logger->debug("New user registered", ['user_id' => $userId]);
        }

        return false; // Continue processing
    }
}
