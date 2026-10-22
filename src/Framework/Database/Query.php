<?php

namespace Framework\Database;

use Pagerfanta\Pagerfanta;
use Traversable;

class Query implements \IteratorAggregate
{

    /**
     * @var array
     */
    private $select;

    /**
     * @var string
     */
    private $from;

    /**
     * @var array
     */
    private $where = [];

    /**
     * @var string
     */
    private $entity;

    /**
     * @var array
     */
    private $joins;

    /**
     * @var array
     */
    private $order = [];

    private $groupBy;

    /**
     * @var string
     */
    private $limit;

    /**
     * @var array
     */
    private $params = [];

    /**
     * @var null|\PDO
     */
    private $pdo;

    /**
     * Query constructor.
     * @param null|\PDO $pdo
     */
    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param string $table
     * @param null|string $alias
     * @return Query
     */
    public function from(string $table, ?string $alias = null) : self
    {
        if ($alias) {
            $this->from[$table] = $alias;
        } else {
            $this->from[] = $table;
        }
        return $this;
    }

    /**
     * @param string ...$fields
     * @return Query
     */
    public function select(string ...$fields) : self
    {
        $this->select = $fields;
        return $this;
    }

    /**
     * @param string $table
     * @param string $condition
     * @param string $type
     * @return Query
     */
    public function join(string $table, string $condition, string $type = "left") : self
    {
        $this->joins[$type][] = [$table, $condition];
        return $this;
    }

    /**
     * @param int $length
     * @param int $offset
     * @return Query
     */
    public function limit(int $length, int $offset = 0) : self
    {
        $this->limit = "$offset, $length";
        return $this;
    }

    /**
     * @param string $order
     * @return Query
     */
    public function order(string $order) : self
    {
        $this->order[] = $order;
        return $this;
    }

    /**
     * @param string ...$condition
     * @return Query
     */
    public function where(string ...$condition) : self
    {
        $this->where = array_merge($this->where, $condition);
        return $this;
    }

    public function groupBy(string $column) : self
    {
        $this->groupBy = $column;
        return $this;
    }

    public function unsetGroupBy() : self
    {
        $this->groupBy = null;
        return $this;
    }

    /**
     * @param array $params
     * @return Query
     */
    public function params(array $params) : self
    {
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * @return int
     */
    public function count() : int
    {
        $query = clone $this;
        $table = current($this->from);
        return $query->select("COUNT(distinct $table.id)")->execute()->fetchColumn();
    }

    /**
     * @param string $entity
     * @return Query
     */
    public function into(string $entity) : self
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     * @return QueryResult
     */
    public function fetchAll() : QueryResult
    {
        return new QueryResult(
            $this->execute()->fetchAll(\PDO::FETCH_ASSOC),
            $this->entity
        );
    }

    public function paginate(int $perPage, int $currentPage = 1) : Pagerfanta
    {
        $paginator = new PaginatedQuery($this);
        return (new Pagerfanta($paginator))->setMaxPerPage($perPage)->setCurrentPage($currentPage);
    }

    /**
     * @return bool|mixed
     */
    public function fetch()
    {
        $record = $this->execute()->fetch(\PDO::FETCH_ASSOC);
        if ($record === false) {
            return false;
        }
        if ($this->entity) {
            return Hydrator::hydrate($record, $this->entity);
        }
        return $record;
    }

    public function fetchColumn()
    {
        $record = $this->execute()->fetchColumn();
        if ($record === false) {
            return false;
        }
        return $record;
    }

    /**
     * @return bool|mixed
     * @throws NoRecordException
     */
    public function fetchOrFail()
    {
        $record = $this->fetch();
        if ($record === false) {
            throw new NoRecordException();
        }
        return $record;
    }



    /**
     * @return string
     */
    private function buildFrom() : string
    {
        $from = [];
        foreach ($this->from as $key => $value) {
            if (is_string($key)) {
                $from[] = "`$key` as $value";
            } else {
                $from[] = "`$value`";
            }
        }
        return join(', ', $from);
    }

    /**
     * @return \PDOStatement
     */
    private function execute()
    {
        $query = $this->__toString();
        if (!empty($this->params)) {
            $statement = $this->pdo->prepare($query);
            $statement->execute($this->params);
            return $statement;
        }
        return $this->pdo->query($query);
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        /*
         * SELECT
         */
        $parts = ['SELECT'];
        if ($this->select) {
            $parts[] = join(', ', $this->select);
        } else {
            $parts[] = '*';
        }

        /*
         * FROM
         */
        $parts[] = 'FROM';
        $parts[] = $this->buildFrom();

        /*
         * JOIN
         */
        if (!empty($this->joins)) {
            foreach ($this->joins as $type => $joins) {
                foreach ($joins as [$table, $condition]) {
                    $parts[] = strtoupper($type) . " JOIN $table ON $condition";
                }
            }
        }

        //$parts[] = "({$this->from[array_keys($this->from)[0]][0]}.deleted = 0)";

        /*
         * WHERE
         */
        if (!empty($this->where)) {
            //$parts[] = "AND";
            $parts[] = "WHERE";
            $parts[] = "(" . join(') AND (', $this->where) . ")";
        }

        /*
         * GROUP BY
         */
        if ($this->groupBy) {
            $parts[] = "GROUP BY";
            $parts[] = $this->groupBy;
        }

        /*
         * ORDER
         */
        if (!empty($this->order)) {
            $parts[] = "ORDER BY";
            $parts[] = join(', ', $this->order);
        }

        /*
         * LIMIT
         */
        if ($this->limit) {
            $parts[] = "LIMIT";
            $parts[] = $this->limit;
        }

        return join(' ', $parts);
    }

    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator(): \Traversable
    {
        return $this->fetchAll();
    }
}
