<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

use DateTimeImmutable;
use Override;
use PDOException;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Driver\ResultInterface;
use PhpDb\Exception\ExceptionInterface;
use PhpDb\Sql\Predicate\Operator;
use PhpDb\Sql\PreparableSqlInterface;
use PhpDb\Sql\Sql;

use function is_array;

/**
 * Links in the site database's `preview_share` table, which the migration in schema/ creates.
 *
 * @api
 *
 * @mago-expect lint:too-many-methods One method per method of the store it implements, and the three ways it runs a statement.
 */
final readonly class PhpDbShareStore implements ShareStoreInterface
{
    public const string TABLE = 'preview_share';

    private Sql $sql;

    public function __construct(AdapterInterface $adapter)
    {
        $this->sql = new Sql($adapter);
    }

    #[Override]
    public function add(Share $share): void
    {
        $this->execute($this->sql->insert(self::TABLE)->values(ShareRow::values($share)));
    }

    #[Override]
    public function delete(string $id): bool
    {
        return $this->affected($this->sql->delete(self::TABLE)->where(['preview_share_id' => $id])) > 0;
    }

    #[Override]
    public function deleteExpired(DateTimeImmutable $now): int
    {
        return $this->affected($this->sql
            ->delete(self::TABLE)
            ->where(
                new Operator('expires', Operator::OPERATOR_LESS_THAN_OR_EQUAL_TO, ShareRow::time($now)),
            ));
    }

    #[Override]
    public function deleteForRecord(string $section, array $keys): int
    {
        return $this->affected($this->sql
            ->delete(self::TABLE)
            ->where([
                'section'     => $section,
                'record_keys' => ShareRow::keys($keys),
            ]));
    }

    #[Override]
    public function find(string $id): ?Share
    {
        $rows = $this->rows($this->sql->select(self::TABLE)->where(['preview_share_id' => $id]));

        return [] === $rows ? null : ShareRow::share($rows[0]);
    }

    #[Override]
    public function forRecord(string $section, array $keys): array
    {
        $shares = [];
        $select = $this->sql
            ->select(self::TABLE)
            ->where(['section' => $section, 'record_keys' => ShareRow::keys($keys)])
            ->order(['expires ASC', 'created ASC']);
        foreach ($this->rows($select) as $row) {
            $shares[] = ShareRow::share($row);
        }

        return $shares;
    }

    #[Override]
    public function replaceCode(string $id, string $code): bool
    {
        return $this->affected($this->sql
            ->update(self::TABLE)
            ->set(['code' => $code])
            ->where(['preview_share_id' => $id]))
            > 0;
    }

    /**
     * @throws ShareStoreException
     */
    private function affected(PreparableSqlInterface $statement): int
    {
        return $this->execute($statement)->getAffectedRows();
    }

    /**
     * @throws ShareStoreException
     */
    private function execute(PreparableSqlInterface $statement): ResultInterface
    {
        try {
            $result = $this->sql->prepareStatementForSqlObject($statement)->execute();
        } catch (ExceptionInterface|PDOException $failure) {
            throw ShareStoreException::failed($failure);
        }

        return $result ?? throw ShareStoreException::unreadable('result');
    }

    /**
     * @return list<array<array-key, mixed>>
     *
     * @throws ShareStoreException
     */
    private function rows(PreparableSqlInterface $select): array
    {
        $rows = [];
        /** @var mixed $row */
        foreach ($this->execute($select) as $row) {
            $rows[] = is_array($row) ? $row : throw ShareStoreException::unreadable('row');
        }

        return $rows;
    }
}
