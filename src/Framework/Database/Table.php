<?php
namespace Framework\Database;

class Table
{
    /**
     * @var null|\PDO
     */
    private $pdo;

    /**
     * @var string Table name in the database
     */
    protected $table;

    /**
     * @var string|null
     */
    protected $entity = \stdClass::class;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findList() : array
    {
        $results = $this->pdo
            ->query("SELECT id, name FROM {$this->table}")
            ->fetchAll(\PDO::FETCH_NUM);
        $list = [];
        foreach ($results as $result) {
            $list[$result[0]] = $result[1];
        }
        return $list;
    }

    /**
     * @param int $id
     * @return mixed|null
     * @throws NoRecordException
     */
    public function find(int $id)
    {
        return $this->makeQuery()->where("id  = :id")->params(["id" => $id])->fetchOrFail();
    }

    /**
     * @return Query
     */
    public function makeQuery() : Query
    {
        return (new Query($this->pdo))
            ->from($this->table, $this->table[0])
            ->into($this->entity);
    }

    /**
     * @return Query
     */
    public function findAll() : Query
    {
        return $this->makeQuery();
    }

    /**
     * @param string $field
     * @param string $value
     * @return mixed
     * @throws NoRecordException
     */
    public function findBy(string $field, string $value)
    {
        return $this->makeQuery()->where("$field  = :field")->params(["field" => $value])->fetchOrFail();
    }

    /**
     * @return int
     */
    public function count() : int
    {
        return $this->makeQuery()->count();
    }

    /**
     * @param int $id
     * @param array $params
     * @return bool
     */
    public function update(int $id, array $params) : bool
    {
        $fieldQuery = $this->buildFieldsQuery($params);
        $params["id"] = $id;
        $query = $this->pdo->prepare("UPDATE `" . $this->table . "` SET $fieldQuery WHERE id = :id");
        return $query->execute($params);
    }

    /**
     * @param array $params
     * @return bool
     */
    public function insert(array $params) : bool
    {
        $fields = array_keys($params);
        $values = array_map(function ($field) {
            return ':' . $field;
        }, $fields);
        $query = $this->pdo->prepare(
            "INSERT INTO `" . $this->table . "` (".join(',', $fields).") VALUES (".join(',', $values).")
        "
        );

        return $query->execute($params);
    }

    /**
     * @param int $id
     * @return bool
     */
    public function delete(int $id) : bool
    {
        //$query = $this->pdo->prepare("UPDATE " . $this->table . " SET deleted=1 WHERE id = ?");
        $query = $this->pdo->prepare("DELETE FROM " . $this->table . " WHERE id = ?");
        return $query->execute([$id]);
    }

    /**
     * @param int $id
     * @return bool
     */
    public function exists(int $id) : bool
    {
        $query = $this->pdo->prepare("SELECT id FROM {$this->table} WHERE id = ?");
        $query->execute([$id]);
        return $query->fetchColumn() !== false;
    }

    /**
     * @param string $key
     * @param string $value
     * @param int|null $exclude
     * @return bool
     * @internal param $id
     */
    public function entryExists(string $key, string $value, ?int $exclude = null) : bool
    {
        $query = "SELECT id FROM {$this->table} WHERE {$key} = ?";
        $params = [$value];
        if ($exclude !== null) {
            $query .= " AND id != ?";
            $params[] = $exclude;
        }
        $query = $this->pdo->prepare($query);
        $query->execute($params);
        return $query->fetchColumn() !== false;
    }

    /**
     * @param string $query
     * @param array $params
     * @return mixed
     * @throws NoRecordException
     */
    /*protected function fetchOrFail(string $query, array $params = [])
    {
        $query = $this->pdo->prepare($query);
        $query->execute($params);
        if ($this->entity) {
            $query->setFetchMode(\PDO::FETCH_CLASS, $this->entity);
        }
        $record = $query->fetch();
        if ($record === false) {
            throw new NoRecordException();
        }
        return $record;
    }*/

    /**
     * @param array $params
     * @return string
     */
    private function buildFieldsQuery(array $params)
    {
        return join(', ', array_map(function ($field) {
            return "$field = :$field";
        }, array_keys($params)));
    }

    /**
     * @return string
     */
    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return \PDO
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
