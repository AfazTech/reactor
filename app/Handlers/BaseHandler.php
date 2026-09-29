<?php

namespace App\Handlers;

use Neili\Client;
use Reactor\Contracts\LanguageInterface;
use Reactor\Core\Traits\FromIdExtractor;
use Reactor\Contracts\LoggerInterface;
use App\Contracts\Repository\UserRepositoryInterface;

abstract class BaseHandler
{
    use FromIdExtractor;

    protected Client $client;
    protected LanguageInterface $language;
    protected LoggerInterface $logger;
    protected UserRepositoryInterface $userRepository;
    protected array $update;

    public function __construct(
        Client $client,
        LanguageInterface $language,
        LoggerInterface $logger,
        UserRepositoryInterface $userRepository
    ) {
        $this->client = $client;
        $this->language = $language;
        $this->logger = $logger;
        $this->userRepository = $userRepository;
    }

    final public function execute(array $update, array $params = []): void
    {
        $this->update = $update;
        $this->handle($params);
    }

    abstract protected function handle(array $params = []): void;

    /**
     * Reply to the incoming update.
     *
     * By default the message is sent as a reply to the original
     * message. Pass $asReply = false to send a standalone message
     * even when a message_id is present.
     */
    protected function reply(
        string $message,
        ?array $keyboard = null,
        array $extra = [],
        bool $asReply = true
    ): void {
        $chatId = $this->extractChatId($this->update);
        if (!$chatId) {
            $this->logger->warning(
                "Cannot reply: no chat_id found in update",
                ['update' => $this->update]
            );
            return;
        }

        $messageId = $asReply ? $this->extractMessageId($this->update) : null;
        if (!$messageId) {
            $this->client->sendMessage($chatId, $message, $keyboard, $extra);
            return;
        }

        $replyExtra = array_merge($extra, ['reply_to_message_id' => $messageId]);
        $this->client->sendMessage($chatId, $message, $keyboard, $replyExtra);
    }

    protected function send(string $message, ?array $keyboard = null, array $extra = []): void
    {
        $chatId = $this->extractChatId($this->update);
        if (!$chatId) {
            $this->logger->warning(
                "Cannot send: no chat_id found in update",
                ['update' => $this->update]
            );
            return;
        }

        $this->client->sendMessage($chatId, $message, $keyboard, $extra);
    }

    protected function getUserLanguage(): string
    {
        $userId = $this->fromId($this->update);
        if (!$userId) {
            return $this->language->getDefaultLanguage();
        }
        return $this->userRepository->getLanguage($userId);
    }

    protected function getUserId(): ?int
    {
        return $this->fromId($this->update);
    }
}
