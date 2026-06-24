<?php
declare(strict_types=1);
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\migration\db;

use Phinx\Db\Table\Index;

class Table extends \Phinx\Db\Table
{

    protected function setOption($name, $value)
    {
        $options = $this->getOptions();

        $options[$name] = $value;

        $this->table->setOptions($options);

        return $this;
    }

    /**
     * 设置id
     * @param $id
     * @return $this
     */
    public function setId($id)
    {
        return $this->setOption('id', $id);
    }

    /**
     * 设置主键
     * @param $key
     * @return $this
     */
    public function setPrimaryKey($key)
    {
        return $this->setOption('primary_key', $key);
    }

    /**
     * 设置引擎
     * @param $engine
     * @return $this
     */
    public function setEngine($engine)
    {
        return $this->setOption('engine', $engine);
    }

    /**
     * 设置表注释
     * @param $comment
     * @return $this
     */
    public function setComment($comment)
    {
        return $this->setOption('comment', $comment);
    }

    /**
     * 设置排序比对方法
     * @param $collation
     * @return $this
     */
    public function setCollation($collation)
    {
        return $this->setOption('collation', $collation);
    }

    /**
     * 添加软删除字段
     * @param string $type 字段类型：'timestamp'（默认，时间戳）或 'datetime'（日期时间）
     * @return $this
     */
    public function addSoftDelete(string $type = 'timestamp')
    {
        if ($type === 'datetime') {
            $this->addColumn(Column::datetime('delete_time')->setNullable());
        } else {
            $this->addColumn(Column::timestamp('delete_time')->setNullable());
        }
        return $this;
    }

    public function addMorphs($name, $indexName = null)
    {
        $this->addColumn(Column::unsignedInteger("{$name}_id"));
        $this->addColumn(Column::string("{$name}_type"));
        $options = [];
        if ($indexName) {
            $options['name'] = $indexName;
        }
        $this->addIndex(["{$name}_id", "{$name}_type"], $options);
        return $this;
    }

    public function addNullableMorphs($name, $indexName = null)
    {
        $this->addColumn(Column::unsignedInteger("{$name}_id")->setNullable());
        $this->addColumn(Column::string("{$name}_type")->setNullable());
        $options = [];
        if ($indexName) {
            $options['name'] = $indexName;
        }
        $this->addIndex(["{$name}_id", "{$name}_type"], $options);
        return $this;
    }

    /**
     * 添加时间戳字段（使用 timestamp 类型）
     * @param string $createdAt
     * @param string $updatedAt
     * @return $this
     */
    public function addTimestamps($createdAt = 'create_time', $updatedAt = 'update_time', bool $withTimezone = false)
    {
        if ($createdAt) {
            $this->addColumn($createdAt, 'timestamp', [
                'null'     => false,
                'default'  => 'CURRENT_TIMESTAMP',
                'timezone' => $withTimezone,
            ]);
        }
        if ($updatedAt) {
            $this->addColumn($updatedAt, 'timestamp', [
                'null'     => true,
                'default'  => 'CURRENT_TIMESTAMP',
                'update'   => 'CURRENT_TIMESTAMP',
                'timezone' => $withTimezone,
            ]);
        }

        return $this;
    }

    /**
     * 添加时间字段（使用 datetime 类型，提供更高精度）
     * @param string $createdAt
     * @param string $updatedAt
     * @return $this
     */
    public function addDatetimes($createdAt = 'create_time', $updatedAt = 'update_time')
    {
        if ($createdAt) {
            $this->addColumn($createdAt, 'datetime', [
                'null'    => false,
                'default' => 'CURRENT_TIMESTAMP',
            ]);
        }
        if ($updatedAt) {
            $this->addColumn($updatedAt, 'datetime', [
                'null'    => true,
                'default' => 'CURRENT_TIMESTAMP',
                'update'  => 'CURRENT_TIMESTAMP',
            ]);
        }

        return $this;
    }

    /**
     * @param \Phinx\Db\Table\Column|string $columnName
     * @param null $type
     * @param array $options
     * @return $this
     */
    public function addColumn($columnName, $type = null, $options = [])
    {
        if ($columnName instanceof Column && $columnName->getUnique()) {
            $index = new Index();
            $index->setColumns([$columnName->getName()]);
            $index->setType(Index::UNIQUE);
            $this->addIndex($index);
        }
        return parent::addColumn($columnName, $type, $options);
    }

    /**
     * @param string $columnName
     * @param mixed  $newColumnType
     * @param array  $options
     * @return $this
     */
    public function changeColumn($columnName, $newColumnType = null, $options = [])
    {
        if ($columnName instanceof \Phinx\Db\Table\Column) {
            return parent::changeColumn($columnName->getName(), $columnName, $options);
        }
        if ($newColumnType instanceof \Phinx\Db\Table\Column) {
            return parent::changeColumn($columnName, $newColumnType, $options);
        }
        if ($newColumnType === null) {
            if (isset($options['type'])) {
                return parent::changeColumn($columnName, $options['type'], $options);
            }
            throw new \InvalidArgumentException('changeColumn() requires a column type (either as second argument or in options[\'type\']).');
        }
        return parent::changeColumn($columnName, $newColumnType, $options);
    }
}