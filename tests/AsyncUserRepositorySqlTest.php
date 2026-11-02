<?php

namespace Tests;

use Amp\Future;
use Amp\Sql\SqlQueryError;
use App\Repositories\AsyncUserRepository;
use PHPUnit\Framework\TestCase;
use Reactor\Contracts\AsyncDatabaseManagerInterface;

class AsyncUserRepositorySqlTest extends TestCase
{
    public function testMysqlDuplicateIsNotReportedAsANewRegistration(): void
    {
        $db = $this->createMock(AsyncDatabaseManagerInterface::class);
        $db->method('getDriver')->willReturn('mysql');
        $calls = 0;
        $db->expects(self::exactly(2))->method('execute')->willReturnCallback(function ($sql) use (&$calls) {
            if ($calls++ === 0) {
                self::assertStringNotContainsString('IGNORE', $sql);
                return Future::error(new SqlQueryError('MySQL error (1062): 23000 Duplicate entry'));
            }
            self::assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
            return Future::complete(1);
        });
        self::assertFalse((new AsyncUserRepository($db))->syncUser(1, 'test', 'Test', null));
    }

    public function testMysqlErrorsOtherThanDuplicateKeysAreNotHidden(): void
    {
        $db = $this->createMock(AsyncDatabaseManagerInterface::class);
        $db->method('getDriver')->willReturn('mysql');
        $db->expects(self::once())->method('execute')
            ->willReturn(Future::error(new SqlQueryError('MySQL error (1406): 22001 Data too long')));
        $this->expectException(SqlQueryError::class);
        $this->expectExceptionMessage('Data too long');
        (new AsyncUserRepository($db))->syncUser(1, 'test', 'Test', null);
    }
}
