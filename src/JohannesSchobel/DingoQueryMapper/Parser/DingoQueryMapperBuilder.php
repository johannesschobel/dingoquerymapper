<?php

namespace JohannesSchobel\DingoQueryMapper\Parser;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use JohannesSchobel\DingoQueryMapper\Exceptions\EmptyColumnException;
use JohannesSchobel\DingoQueryMapper\Exceptions\UnknownColumnException;
use JohannesSchobel\DingoQueryMapper\Operators\CollectionOperator;

class DingoQueryMapperBuilder
{
    /**
     * The model behind the querybuilder
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * The builder used to create the query
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    //protected $builder;

    /**
     * The uri parser to extract the query parameters
     *
     * @var UriParser
     */
    protected $uriParser;

    protected $wheres = [];

    // ok
    protected $sort = [];

    // ok
    protected $limit;

    // ok
    protected $page = 1;

    // ok
    protected $offset = 0;

    protected $columns = ['*'];

    protected $relationColumns = [];

    protected $rels = [];

    protected $groupBy = [];

    protected $excludedParameters = [];

    protected $query;

    // ok
    protected $result;

    // ok
    protected $operator;

    /**
     * DingoQueryMapperBuilder constructor.
     *
     * @param Request $request the request with query parameters
     */
    public function __construct(Request $request)
    {
        $this->uriParser = new UriParser($request);

        $this->sort = config('dingoquerymapper.defaults.sort');

        $this->limit = config('dingoquerymapper.defaults.limit');

        $this->excludedParameters = array_merge(
            $this->excludedParameters,
            config('dingoquerymapper.excludedParameters')
        );
    }

    /**
     * Create the Query from an existing builder
     *
     * @param Builder $builder
     * @return $this
     */
    public function createFromBuilder(Builder $builder)
    {
        $this->model = $builder->getModel();
        $this->query = $builder;

        $this->build();

        return $this;
    }

    /**
     * Create the query from an empty model
     *
     * @param Model $model
     * @return $this
     */
    public function createFromModel(Model $model)
    {
        $this->model = $model;
        $this->query = $this->model->newQuery();

        return $this;
    }

    public function createFromCollection(Collection $collection)
    {

        $this->operator = new CollectionOperator($collection);

        return $this;
    }

    public function build()
    {
        $this->prepare();

        if (config('dingoquerymapper.allowFilters')) {
            if ($this->hasWheres()) {
                array_map([$this, 'addWhereToQuery'], $this->wheres);
            }
        }

        if ($this->hasLimit()) {
            $this->query->take($this->limit);
        }

        if ($this->hasOffset()) {
            $this->query->skip($this->offset);
        }

        array_map([$this, 'addSortToQuery'], $this->sort);

        $this->query->with($this->rels);

        $this->query->select($this->columns);

        return $this;
    }

    public function get()
    {
        return $this->query->get();
    }

    public function paginate()
    {
        if (!$this->hasLimit()) {
            throw new Exception("You can't use unlimited option for pagination", 1);
        }

        return $this->query->paginate($this->limit);
    }

    public function lists($value, $key)
    {
        return $this->query->lists($value, $key);
    }

    protected function prepare()
    {
        $this->setWheres($this->uriParser->whereParameters());

        $constantParameters = $this->uriParser->predefinedParameters();

        array_map([$this, 'prepareConstant'], $constantParameters);

        if ($this->hasRels() && $this->hasRelationColumns()) {
            $this->fixRelationColumns();
        }

        return $this;
    }

    private function prepareConstant($parameter)
    {
        if (!$this->uriParser->hasQueryParameter($parameter)) {
            return;
        }

        $callback = [$this, $this->setterMethodName($parameter)];

        $callbackParameter = $this->uriParser->queryParameter($parameter);

        call_user_func($callback, $callbackParameter['value']);
    }

    private function setRels($rels)
    {
        $this->rels = array_filter(explode(',', $rels));
    }

    private function setPage($page)
    {
        $this->page = (int)$page;

        $this->offset = ($page - 1) * $this->limit;
    }

    private function setColumns($columns)
    {
        $columns = array_filter(explode(',', $columns));

        $this->columns = $this->relationColumns = [];

        array_map([$this, 'setColumn'], $columns);

        if ($this->hasColumns($columns) == 0) {
            throw new EmptyColumnException("Columns are empty");
        }
    }

    private function setColumn($column)
    {
        if ($this->isRelationColumn($column)) {
            return $this->appendRelationColumn($column);
        }

        if (!$this->hasTableColumn($column)) {
            throw new UnknownColumnException("Unknown column '{$column}'");
        }

        $this->columns[] = $column;
    }

    private function appendRelationColumn($keyAndColumn)
    {
        list($key, $column) = explode('.', $keyAndColumn);

        $this->relationColumns[$key][] = $column;
    }

    private function fixRelationColumns()
    {
        $keys = array_keys($this->relationColumns);

        $callback = [$this, 'fixRelationColumn'];

        array_map($callback, $keys, $this->relationColumns);
    }

    private function fixRelationColumn($key, $columns)
    {
        $index = array_search($key, $this->rels);

        unset($this->rels[$index]);

        $this->rels[$key] = $this->closureRelationColumns($columns);
    }

    private function closureRelationColumns($columns)
    {
        return function ($q) use ($columns) {
            $q->select($columns);
        };
    }

    private function setSort($sort)
    {
        $this->sort = [];

        $orders = array_filter(explode(',', $sort));

        array_map([$this, 'appendSort'], $orders);
    }

    private function appendSort($sort)
    {
        $column = $sort;
        $direction = 'asc';

        if ($sort[0] == '-') {
            $column = substr($sort, 1);
            $direction = 'desc';
        }

        $this->sort[] = [
            'column'    => $column,
            'direction' => $direction,
        ];
    }

    private function setGroupBy($groups)
    {
        $this->groupBy = array_filter(explode(',', $groups));
    }

    private function setLimit($limit)
    {
        $this->limit = (int)$limit;
    }

    private function setWheres($parameters)
    {
        $this->wheres = $parameters;
    }

    private function addWhereToQuery($where)
    {
        extract($where);

        if ($this->isExcludedParameter($key)) {
            return;
        }

        if ($this->hasCustomFilter($key)) {
            return $this->applyCustomFilter($key, $operator, $value);
        }

        if (!$this->hasTableColumn($key)) {
            throw new UnknownColumnException("Unknown column '{$key}'");
        }

        $this->query->where($key, $operator, $value);
    }

    private function addSortToQuery($order)
    {
        extract($order);

        $this->query->orderBy($column, $direction);
    }

    private function applyCustomFilter($key, $operator, $value)
    {
        $callback = [$this, $this->customFilterName($key)];

        $this->query = call_user_func($callback, $this->query, $value, $operator);
    }

    private function isRelationColumn($column)
    {
        return (count(explode('.', $column)) > 1);
    }

    private function isExcludedParameter($key)
    {
        return in_array($key, $this->excludedParameters);
    }

    private function hasWheres()
    {
        return (count($this->wheres) > 0);
    }

    private function hasRels()
    {
        return (count($this->rels) > 0);
    }

    private function hasGroupBy()
    {
        return (count($this->groupBy) > 0);
    }

    private function hasLimit()
    {
        return ($this->limit);
    }

    private function hasOffset()
    {
        return ($this->offset != 0);
    }

    private function hasRelationColumns()
    {
        return (count($this->relationColumns) > 0);
    }

    private function hasTableColumn($column)
    {
        return (Schema::hasColumn($this->model->getTable(), $column));
    }

    private function hasCustomFilter($key)
    {
        $methodName = $this->customFilterName($key);

        return (method_exists($this, $methodName));
    }

    private function setterMethodName($key)
    {
        return 'set' . studly_case($key);
    }

    private function customFilterName($key)
    {
        return 'filterBy' . studly_case($key);
    }

    private function hasColumns($columns)
    {
        return (count($columns) > 0);
    }
}
