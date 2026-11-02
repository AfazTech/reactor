<?php

namespace Tests;

use App\Contracts\Repository\UserRepositoryInterface;
use App\Repositories\UpdateCachedUserRepository;
use PHPUnit\Framework\TestCase;
use Reactor\Core\UpdateScope;

class UpdateCachedUserRepositoryTest extends TestCase
{
    public function testLanguageStepTempAndUserShareOneReadPerUpdate(): void
    {
        $raw = $this->createMock(UserRepositoryInterface::class);
        $raw->expects(self::exactly(2))->method('getUser')->with(10)->willReturn([
            'language' => 'fa', 'step' => 'checkout', 'temp' => ['item' => 1],
        ]);
        $scope = new UpdateScope();
        $cached = new UpdateCachedUserRepository($raw, $scope);
        for ($i = 0; $i < 2; $i++) {
            $scope->run(function () use ($cached) {
                self::assertSame('fa', $cached->getLanguage(10));
                self::assertSame('checkout', $cached->getStep(10));
                self::assertSame(['item' => 1], $cached->getTemp(10));
                self::assertSame('fa', $cached->getUser(10)['language']);
            });
        }
    }

    public function testMissingUsersAreCachedAndDifferentUsersStaySeparate(): void
    {
        $raw = $this->createMock(UserRepositoryInterface::class);
        $raw->expects(self::exactly(2))->method('getUser')->willReturnCallback(
            fn ($id) => $id === 10 ? null : ['language' => 'en']
        );
        $scope = new UpdateScope();
        $cached = new UpdateCachedUserRepository($raw, $scope);
        $scope->run(function () use ($cached) {
            self::assertNull($cached->getUser(10));
            self::assertSame('', $cached->getLanguage(10));
            self::assertNull($cached->getStep(10));
            self::assertSame('en', $cached->getLanguage(11));
        });
    }

    /** @dataProvider writes */
    public function testSuccessfulWritesInvalidateTheCurrentUser(string $method, array $arguments): void
    {
        $raw = $this->createMock(UserRepositoryInterface::class);
        $raw->expects(self::exactly(2))->method('getUser')->willReturnOnConsecutiveCalls(
            ['language' => 'old'], ['language' => 'new']
        );
        $expectation = $raw->expects(self::once())->method($method)->with(10, ...$arguments);
        if ($method === 'syncUser') {
            $expectation->willReturn(true);
        }
        $scope = new UpdateScope();
        $cached = new UpdateCachedUserRepository($raw, $scope);
        $scope->run(function () use ($cached, $method, $arguments) {
            self::assertSame('old', $cached->getLanguage(10));
            $result = $cached->{$method}(10, ...$arguments);
            if ($method === 'syncUser') {
                self::assertTrue($result);
            }
            self::assertSame('new', $cached->getLanguage(10));
            self::assertSame('new', $cached->getLanguage(10));
        });
    }

    public static function writes(): array
    {
        return [
            ['syncUser', ['user', 'First', null]], ['setLanguage', ['fa']],
            ['setStep', ['next']], ['setStep', [null]], ['setTemp', [['x' => 1]]],
            ['clearTemp', []], ['setStatus', [1]], ['setLastInteraction', []],
        ];
    }

    public function testFailedWriteDoesNotPublishAnUncommittedValue(): void
    {
        $raw = $this->createMock(UserRepositoryInterface::class);
        $raw->expects(self::once())->method('getUser')->willReturn(['step' => 'old']);
        $raw->method('setStep')->willThrowException(new \RuntimeException('failed'));
        $scope = new UpdateScope();
        $cached = new UpdateCachedUserRepository($raw, $scope);
        $scope->run(function () use ($cached) {
            self::assertSame('old', $cached->getStep(10));
            try {
                $cached->setStep(10, 'new');
                self::fail('Expected failed write');
            } catch (\RuntimeException $e) {
                self::assertSame('failed', $e->getMessage());
            }
            self::assertSame('old', $cached->getStep(10));
        });
    }

    public function testRawWritesCanBeExplicitlyInvalidated(): void
    {
        $raw = $this->createMock(UserRepositoryInterface::class);
        $raw->expects(self::exactly(2))->method('getUser')->willReturnOnConsecutiveCalls(
            ['language' => 'before'], ['language' => 'after']
        );
        $scope = new UpdateScope();
        $cached = new UpdateCachedUserRepository($raw, $scope);
        $scope->run(function () use ($cached) {
            self::assertSame('before', $cached->getLanguage(10));
            $cached->forgetUser(10);
            self::assertSame('after', $cached->getLanguage(10));
        });
    }
    public function testConcurrentUpdatesForTheSameUserKeepIndependentSnapshots(): void
    {
        $raw = $this->createMock(UserRepositoryInterface::class);
        $reads = 0;
        $raw->expects(self::exactly(2))->method('getUser')->willReturnCallback(function () use (&$reads) {
            $value = ++$reads;
            \Amp\delay(0.01);
            return ['language' => 'snapshot-' . $value];
        });
        $scope = new UpdateScope();
        $cached = new UpdateCachedUserRepository($raw, $scope);
        $run = fn () => $scope->run(function () use ($cached) {
            $first = $cached->getLanguage(10);
            \Amp\delay(0.01);
            return [$first, $cached->getLanguage(10)];
        });
        self::assertSame([['snapshot-1', 'snapshot-1'], ['snapshot-2', 'snapshot-2']],
            \Amp\Future\await([\Amp\async($run), \Amp\async($run)]));
    }

}
