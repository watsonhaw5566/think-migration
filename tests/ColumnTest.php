<?php

declare(strict_types = 1);

namespace think\migration\Tests;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Adapter\MysqlAdapter;
use PHPUnit\Framework\TestCase;
use think\migration\db\Column;

final class ColumnTest extends TestCase
{
    public function testMakeCreatesColumnWithNameAndType(): void
    {
        $column = Column::make('id', AdapterInterface::PHINX_TYPE_INTEGER);

        $this->assertSame('id', $column->getName());
        $this->assertSame(AdapterInterface::PHINX_TYPE_INTEGER, $column->getType());
    }

    public function testBigInteger(): void
    {
        $column = Column::bigInteger('id');

        $this->assertSame('id', $column->getName());
        $this->assertSame(AdapterInterface::PHINX_TYPE_BIG_INTEGER, $column->getType());
    }

    public function testStringWithDefaultLength(): void
    {
        $column = Column::string('email');

        $this->assertSame('email', $column->getName());
        $this->assertSame(AdapterInterface::PHINX_TYPE_STRING, $column->getType());
    }

    public function testStringWithCustomLength(): void
    {
        $column = Column::string('name', 100);

        $this->assertSame('name', $column->getName());
        $this->assertSame(100, $column->getLimit());
    }

    public function testChar(): void
    {
        $column = Column::char('code', 8);

        $this->assertSame('code', $column->getName());
        $this->assertSame(AdapterInterface::PHINX_TYPE_CHAR, $column->getType());
        $this->assertSame(8, $column->getLimit());
    }

    public function testInteger(): void
    {
        $column = Column::integer('count');

        $this->assertSame(AdapterInterface::PHINX_TYPE_INTEGER, $column->getType());
    }

    public function testTinyIntegerHasCorrectLength(): void
    {
        $column = Column::tinyInteger('status');

        $this->assertSame(AdapterInterface::PHINX_TYPE_INTEGER, $column->getType());
        $this->assertSame(MysqlAdapter::INT_TINY, $column->getLimit());
    }

    public function testSmallIntegerHasCorrectLength(): void
    {
        $column = Column::smallInteger('counter');

        $this->assertSame(AdapterInterface::PHINX_TYPE_INTEGER, $column->getType());
        $this->assertSame(MysqlAdapter::INT_SMALL, $column->getLimit());
    }

    public function testMediumIntegerHasCorrectLength(): void
    {
        $column = Column::mediumInteger('value');

        $this->assertSame(AdapterInterface::PHINX_TYPE_INTEGER, $column->getType());
        $this->assertSame(MysqlAdapter::INT_MEDIUM, $column->getLimit());
    }

    public function testUnsignedInteger(): void
    {
        $column = Column::unsignedInteger('count');

        $this->assertSame(AdapterInterface::PHINX_TYPE_INTEGER, $column->getType());
        $this->assertFalse($column->getSigned());
    }

    public function testDecimalWithCustomPrecision(): void
    {
        $column = Column::decimal('price', 10, 4);

        $this->assertSame(AdapterInterface::PHINX_TYPE_DECIMAL, $column->getType());
        $this->assertSame(10, $column->getPrecision());
        $this->assertSame(4, $column->getScale());
    }

    public function testEnumWithValues(): void
    {
        $values = ['active', 'inactive', 'pending'];
        $column = Column::enum('status', $values);

        $this->assertSame(AdapterInterface::PHINX_TYPE_ENUM, $column->getType());
        $this->assertSame($values, $column->getValues());
    }

    public function testBoolean(): void
    {
        $column = Column::boolean('flag');

        $this->assertSame(AdapterInterface::PHINX_TYPE_BOOLEAN, $column->getType());
    }

    public function testDate(): void
    {
        $column = Column::date('birth');

        $this->assertSame(AdapterInterface::PHINX_TYPE_DATE, $column->getType());
    }

    public function testDateTime(): void
    {
        $column = Column::dateTime('created_at');

        $this->assertSame(AdapterInterface::PHINX_TYPE_DATETIME, $column->getType());
    }

    public function testText(): void
    {
        $column = Column::text('body');

        $this->assertSame(AdapterInterface::PHINX_TYPE_TEXT, $column->getType());
    }

    public function testLongText(): void
    {
        $column = Column::longText('content');

        $this->assertSame(AdapterInterface::PHINX_TYPE_TEXT, $column->getType());
        $this->assertSame(MysqlAdapter::TEXT_LONG, $column->getLimit());
    }

    public function testMediumText(): void
    {
        $column = Column::mediumText('content');

        $this->assertSame(AdapterInterface::PHINX_TYPE_TEXT, $column->getType());
        $this->assertSame(MysqlAdapter::TEXT_MEDIUM, $column->getLimit());
    }

    public function testFloat(): void
    {
        $column = Column::float('rating');

        $this->assertSame(AdapterInterface::PHINX_TYPE_FLOAT, $column->getType());
    }

    public function testJson(): void
    {
        $column = Column::json('metadata');

        $this->assertSame(AdapterInterface::PHINX_TYPE_JSON, $column->getType());
    }

    public function testJsonb(): void
    {
        $column = Column::jsonb('metadata');

        $this->assertSame(AdapterInterface::PHINX_TYPE_JSONB, $column->getType());
    }

    public function testBinary(): void
    {
        $column = Column::binary('data');

        $this->assertSame(AdapterInterface::PHINX_TYPE_BLOB, $column->getType());
    }

    public function testTime(): void
    {
        $column = Column::time('duration');

        $this->assertSame(AdapterInterface::PHINX_TYPE_TIME, $column->getType());
    }

    public function testTimestampDefaultsToNullable(): void
    {
        $column = Column::timestamp('deleted_at');

        $this->assertSame(AdapterInterface::PHINX_TYPE_TIMESTAMP, $column->getType());
        $this->assertTrue($column->getNull());
    }

    public function testUuid(): void
    {
        $column = Column::uuid('identifier');

        $this->assertSame(AdapterInterface::PHINX_TYPE_UUID, $column->getType());
    }

    public function testSetNullableReturnsSelf(): void
    {
        $column = Column::string('description');
        $result = $column->setNullable();

        $this->assertSame($column, $result);
        $this->assertTrue($column->getNull());
    }

    public function testSetUnsignedReturnsSelf(): void
    {
        $column = Column::integer('count');
        $result = $column->setUnsigned();

        $this->assertSame($column, $result);
        $this->assertFalse($column->getSigned());
    }

    public function testSetUniqueReturnsSelf(): void
    {
        $column = Column::string('email');
        $result = $column->setUnique();

        $this->assertSame($column, $result);
        $this->assertTrue($column->isUnique());
        $this->assertTrue($column->getUnique());
    }

    public function testSetOptionsWithComment(): void
    {
        $column = Column::string('email');
        $column->setOptions(['comment' => 'User email address']);

        $this->assertSame('User email address', $column->getComment());
    }
}
