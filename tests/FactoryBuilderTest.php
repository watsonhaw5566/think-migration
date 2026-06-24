<?php

declare(strict_types = 1);

namespace think\migration\Tests;

use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use think\migration\FactoryBuilder;

final class FactoryBuilderTest extends TestCase
{
    private Faker $faker;

    protected function setUp(): void
    {
        $this->faker = FakerFactory::create();
    }

    public function testRawReturnsDefinitionAttributes(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return ['name' => 'Alice', 'email' => 'alice@example.com'];
                }
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'default', $definitions, [], [], [], $this->faker);

        $attributes = $builder->raw();

        $this->assertSame('Alice', $attributes['name']);
        $this->assertSame('alice@example.com', $attributes['email']);
    }

    public function testRawWithTimesReturnsMultipleAttributeArrays(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function (Faker $faker) {
                    return ['id' => $faker->randomNumber()];
                }
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'default', $definitions, [], [], [], $this->faker);

        $results = $builder->times(5)->raw();

        $this->assertCount(5, $results);
        foreach ($results as $attrs) {
            $this->assertIsArray($attrs);
            $this->assertArrayHasKey('id', $attrs);
        }
    }

    public function testRawWithTimesZeroReturnsEmptyArray(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return ['name' => 'Test'];
                }
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'default', $definitions, [], [], [], $this->faker);

        $results = $builder->times(0)->raw();

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testRawWithOverrideAttributes(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return ['name' => 'Original', 'role' => 'user'];
                }
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'default', $definitions, [], [], [], $this->faker);

        $attributes = $builder->raw(['name' => 'Overridden']);

        $this->assertSame('Overridden', $attributes['name']);
        $this->assertSame('user', $attributes['role']);
    }

    public function testRawWithNamedDefinition(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return ['role' => 'user'];
                },
                'admin' => function () {
                    return ['role' => 'admin'];
                }
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'admin', $definitions, [], [], [], $this->faker);

        $attributes = $builder->raw();

        $this->assertSame('admin', $attributes['role']);
    }

    public function testRawWithStatesAppliesStateAttributes(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return ['name' => 'Test', 'verified' => false];
                }
            ]
        ];

        $states = [
            FakeEntity::class => [
                'verified' => ['verified' => true],
                'banned' => ['banned' => true]
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'default', $definitions, $states, [], [], $this->faker);

        $attributes = $builder->states(['verified', 'banned'])->raw();

        $this->assertTrue($attributes['verified']);
        $this->assertTrue($attributes['banned']);
    }

    public function testRawCallableAttributesAreExpanded(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return [
                        'name' => 'Test',
                        'computed' => function (array $attrs) {
                            return 'computed_' . $attrs['name'];
                        }
                    ];
                }
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'default', $definitions, [], [], [], $this->faker);

        $attributes = $builder->raw();

        $this->assertSame('computed_Test', $attributes['computed']);
    }

    public function testRawThrowsExceptionWhenDefinitionMissing(): void
    {
        $builder = new FactoryBuilder(
            FakeEntity::class,
            'default',
            [], // no definitions at all
            [],
            [],
            [],
            $this->faker
        );

        $this->expectException(InvalidArgumentException::class);

        $builder->raw();
    }

    public function testRawThrowsExceptionWhenStateIsMissing(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return ['name' => 'Test'];
                }
            ]
        ];

        $builder = new FactoryBuilder(
            FakeEntity::class,
            'default',
            $definitions,
            [], // no states
            [],
            [],
            $this->faker
        );

        $this->expectException(InvalidArgumentException::class);

        $builder->state('non-existent-state')->raw();
    }

    public function testTimesMethodIsFluent(): void
    {
        $builder = new FactoryBuilder(FakeEntity::class, 'default', [], [], [], [], $this->faker);

        $result = $builder->times(3);

        $this->assertSame($builder, $result);
    }

    public function testStateMethodIsFluent(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return ['name' => 'Test'];
                }
            ]
        ];

        $states = [
            FakeEntity::class => [
                'admin' => ['role' => 'admin']
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'default', $definitions, $states, [], [], $this->faker);

        $result = $builder->state('admin');

        $this->assertSame($builder, $result);
    }

    public function testConnectionMethodIsFluent(): void
    {
        $builder = new FactoryBuilder(FakeEntity::class, 'default', [], [], [], [], $this->faker);

        $result = $builder->connection('secondary');

        $this->assertSame($builder, $result);
    }

    public function testStatesWithSingleStateAsArray(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return ['name' => 'Default User'];
                }
            ]
        ];
        $states = [
            FakeEntity::class => [
                'suspended' => ['suspended' => true]
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'default', $definitions, $states, [], [], $this->faker);
        $result = $builder->states(['suspended'])->raw();

        $this->assertTrue($result['suspended']);
    }

    public function testStatesWithMultipleStates(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return ['name' => 'User'];
                }
            ]
        ];
        $states = [
            FakeEntity::class => [
                'verified' => ['verified' => true],
                'banned' => ['banned' => true]
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'default', $definitions, $states, [], [], $this->faker);
        $result = $builder->states('verified', 'banned')->raw();

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['banned']);
    }

    public function testStatesWithArrayContainingMultipleStates(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return ['name' => 'User'];
                }
            ]
        ];
        $states = [
            FakeEntity::class => [
                'verified' => ['verified' => true],
                'banned' => ['banned' => true]
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'default', $definitions, $states, [], [], $this->faker);
        $result = $builder->states(['verified', 'banned'])->raw();

        $this->assertTrue($result['verified']);
        $this->assertTrue($result['banned']);
    }

    public function testStatesMethodIsFluent(): void
    {
        $definitions = [
            FakeEntity::class => [
                'default' => function () {
                    return ['name' => 'Test'];
                }
            ]
        ];
        $states = [
            FakeEntity::class => [
                'admin' => ['role' => 'admin']
            ]
        ];

        $builder = new FactoryBuilder(FakeEntity::class, 'default', $definitions, $states, [], [], $this->faker);
        $result = $builder->states('admin');

        $this->assertSame($builder, $result);
    }
}

class FakeEntity
{
    protected array $attrs = [];

    public function setAttrs(array $attrs): self
    {
        $this->attrs = $attrs;
        return $this;
    }

    public function getAttrs(): array
    {
        return $this->attrs;
    }

    public function toCollection(array $items = []): object
    {
        return new class($items) {
            private array $items;

            public function __construct(array $items)
            {
                $this->items = $items;
            }

            public function each(callable $callback): self
            {
                foreach ($this->items as $item) {
                    $callback($item);
                }
                return $this;
            }

            public function getItems(): array
            {
                return $this->items;
            }
        };
    }

    public function each(callable $callback): self
    {
        $callback($this);
        return $this;
    }

    public function getKey(): string
    {
        return spl_object_hash($this);
    }

    public function save(): bool
    {
        return true;
    }
}
