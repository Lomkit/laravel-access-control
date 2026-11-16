<?php

namespace Lomkit\Access\Tests\Unit\Decisions;

use Lomkit\Access\Decisions\WriteStatement;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class WriteStatementTest extends PackageTestCase
{
    /** @return array<string, array{string}> */
    public static function reads(): array
    {
        return [
            'a select' => ['select * from "posts" where "id" = ?'],
            'an upper-case select after spaces' => ["  \n SELECT 1"],
            'a MySQL union' => ['(select `title` from `posts`) union (select `title` from `posts`)'],
            'an exists' => ['select exists(select * from "posts" where "posts"."id" = ?) as "exists"'],
            'a locking select' => ['select * from `jobs` where `queue` = ? limit 1 for update skip locked'],
            'a MySQL show' => ['show tables'],
            'a PostgreSQL show' => ['SHOW search_path'],
        ];
    }

    #[Test]
    #[DataProvider('reads')]
    public function it_reads_a_select_or_a_show(string $sql): void
    {
        $this->assertTrue(WriteStatement::reads($sql));
    }

    /** @return array<string, array{string}> */
    public static function writes(): array
    {
        return [
            'an insert' => ['insert into "posts" ("title") values (?)'],
            'a common table expression' => ['with "listed" as (select "id" from "posts") select count(*) from "listed"'],
            'a PostgreSQL writing common table expression' => ['with "gone" as (delete from "posts" returning *) select count(*) from "gone"'],
            'an explain' => ['explain select * from "posts"'],
            'an explain analyze of a delete' => ['explain analyze delete from "posts"'],
            'a SQLite pragma' => ['pragma foreign_keys = off'],
            'a leading comment' => ['/* report */ select 1'],
            'a procedure call' => ['call refresh_access()'],
            'a session setting' => ['set search_path to other'],
            'a truncate' => ['truncate table "posts"'],
            'a word starting with select' => ['selections'],
        ];
    }

    #[Test]
    #[DataProvider('writes')]
    public function it_reads_nothing_else(string $sql): void
    {
        $this->assertFalse(WriteStatement::reads($sql));
    }

    /** @return array<string, array{string, string}> */
    public static function oneTableWrites(): array
    {
        return [
            'a MySQL upsert' => ['insert into `cache` (`expiration`, `key`, `value`) values (?, ?, ?) on duplicate key update `expiration` = values(`expiration`)', '`cache`'],
            'a MySQL insert ignore' => ['insert ignore into `cache_locks` (`expiration`, `key`, `owner`) values (?, ?, ?)', '`cache_locks`'],
            'a MySQL replace' => ['replace into `cache` (`key`, `value`) values (?, ?)', '`cache`'],
            'a MySQL update' => ['update `jobs` set `reserved_at` = ?, `attempts` = ? where `id` = ?', '`jobs`'],
            'a MySQL delete' => ['delete from `sessions` where `id` = ?', '`sessions`'],
            'a PostgreSQL upsert' => ['insert into "cache" ("expiration", "key", "value") values (?, ?, ?) on conflict ("key") do update set "expiration" = "excluded"."expiration"', '"cache"'],
            'a PostgreSQL insert returning' => ['insert into "jobs" ("queue", "payload") values (?, ?) returning "id"', '"jobs"'],
            'a PostgreSQL delete by ctid' => ['delete from "cache" where "ctid" in (select "cache"."ctid" from "cache" where "key" = ?)', '"cache"'],
            'a PostgreSQL update from' => ['update "cache" set "value" = ? from "posts" where "posts"."id" = ?', '"cache"'],
            'a schema-qualified table' => ['insert into "public"."cache" ("key") values (?)', '"public"."cache"'],
            'a SQLite insert or ignore' => ['insert or ignore into "cache_locks" ("key", "owner", "expiration") values (?, ?, ?)', '"cache_locks"'],
            'a SQLite insert or replace' => ['insert or replace into "cache" ("key", "value", "expiration") values (?, ?, ?)', '"cache"'],
            'a delete of every row' => ['delete from "cache"', '"cache"'],
            'an insert from a select' => ['insert into "cache" select * from "posts"', '"cache"'],
            'a SQL Server update' => ['update [jobs] set [reserved_at] = ? where [id] = ?', '[jobs]'],
            'an unquoted table' => ['DELETE FROM cache WHERE expiration <= ?', 'cache'],
        ];
    }

    #[Test]
    #[DataProvider('oneTableWrites')]
    public function it_finds_the_only_table_a_write_changes(string $sql, string $table): void
    {
        $this->assertSame($table, WriteStatement::table($sql));
    }

    /** @return array<string, array{string}> */
    public static function otherWrites(): array
    {
        return [
            'a MySQL update with a join' => ['update `cache` inner join `posts` on `posts`.`id` = `cache`.`key` set `posts`.`title` = ?'],
            'a MySQL delete with a join' => ['delete `posts` from `posts` inner join `cache` on `cache`.`key` = `posts`.`id`'],
            'a MySQL delete from two tables' => ['delete from `cache`, `posts` using `cache` inner join `posts`'],
            'a PostgreSQL delete using' => ['delete from "cache" using "posts" where "posts"."id" = ?'],
            'two statements' => ['delete from "cache"; delete from "posts"'],
            'a writing common table expression' => ['with "gone" as (delete from "posts" returning *) insert into "cache" select * from "gone"'],
            'a truncate' => ['truncate table "cache"'],
            'a drop' => ['drop table "cache"'],
            'a raw statement' => ['call refresh_access()'],
        ];
    }

    #[Test]
    #[DataProvider('otherWrites')]
    public function it_finds_no_single_table_for_other_writes(string $sql): void
    {
        $this->assertNull(WriteStatement::table($sql));
    }
}
