<?php

namespace JohannesSchobel\DingoQueryMapper\Parser;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use JohannesSchobel\DingoQueryMapper\Operators\CollectionOperator;

class DingoQueryMapper
{
    // the original request
    protected $request = null;
    // the uri parser used to extract all query parameters
    protected $uriParser = null;
    // the operator to handle respective functionality (e.g., for collections, builders, ...)
    protected $operator = null;

    // filter parameters
    protected $filters = [];
    // sort parameters
    protected $sort = [];
    // elements per page
    protected $limit = 15;
    // page to display
    protected $page = 1;
    // offset of elements (page-1)*limit
    protected $offset = 0;
    // the parameters to exclude from parsing (e.g., token)
    protected $excludedParameters = [];

    public function __construct(Request $request)
    {
        $this->request = $request;

        $this->uriParser = new UriParser($request);

        $this->sort = config('dingoquerymapper.defaults.sort');
        $this->limit = config('dingoquerymapper.defaults.limit');

        $this->excludedParameters = array_merge(
            $this->excludedParameters,
            config('dingoquerymapper.excludedParameters')
        );

        $this->setFilters($this->uriParser->whereParameters());
    }

    /**
     * Create the result based on a collection
     *
     * @param Collection $collection
     * @return $this
     */
    public function createFromCollection(Collection $collection)
    {
        $this->operator = new CollectionOperator($collection, $this->request);
        $this->prepare();

        return $this;
    }

    /**
     * Prepare the result (e.g., sort, filter, ...)
     *
     * @return $this
     */
    private function prepare()
    {
        $constantParameters = $this->uriParser->predefinedParameters();
        array_map([$this, 'prepareConstant'], $constantParameters);

        $this->filter();
        $this->sort();

        return $this;
    }

    /**
     * Filter the result. Operator implements actual logic.
     *
     * @return mixed
     */
    private function filter()
    {
        if ($this->allowsFilter()) {
            if ($this->hasFilters()) {
                $tmp = [];
                foreach ($this->filters as $filter) {
                    // check, if it is a "forbidden" query parameter
                    if ($this->isExcludedParameter($filter['key'])) {
                        continue;
                    }
                    $tmp[] = $filter;
                }

                $this->filters = $tmp;

                return $this->operator->filter($this->filters);
            }
        }
    }

    /**
     * Get the entire result.
     *
     * @return mixed
     */
    public function get()
    {
        return $this->operator->get();
    }

    /**
     * Returns the paginated result.
     *
     * @return mixed
     */
    public function paginate()
    {
        return $this->operator->paginate($this->page, $this->limit);
    }

    /**
     * Sorts the result
     *
     * @return mixed
     */
    private function sort()
    {
        return $this->operator->sort($this->sort);
    }

    /**
     * Calls respective setXXX Method for the predefined parameters
     *
     * @param $parameter
     */
    private function prepareConstant($parameter)
    {
        if (!$this->uriParser->hasQueryParameter($parameter)) {
            return;
        }

        $callback = [$this, $this->setterMethodName($parameter)];
        $callbackParameter = $this->uriParser->queryParameter($parameter);

        call_user_func($callback, $callbackParameter['value']);
    }

    /**
     * setter for query parameter page. Is called by by prepareConstant() method
     *
     * @param $page
     */
    private function setPage($page)
    {
        $this->page = (int)$page;
        $this->offset = ($page - 1) * $this->limit;
    }

    /**
     * setter for query parameter limit. Is called by by prepareConstant() method
     *
     * @param $limit
     */
    private function setLimit($limit)
    {
        $this->limit = (int)$limit;
    }

    /**
     * setter for query parameter sort. Is called by by prepareConstant() method
     *
     * @param $sort
     */
    private function setSort($sort)
    {
        $this->sort = [];
        $orders = array_filter(explode(',', $sort));
        array_map([$this, 'appendSort'], $orders);
    }

    /**
     * setter for all additional query parameters, which are used to filter the result.
     *
     * @param $filters
     */
    private function setFilters($filters)
    {
        $this->filters = $filters;
    }

    /**
     * Appends sort-criteria to the sort-list based on their orientation (asc / desc)
     *
     * @param $sort
     */
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

    /**
     * returns the SETTER method name for respective parameters
     *
     * @param $parameter
     * @return string
     */
    private function setterMethodName($parameter)
    {
        return 'set' . studly_case($parameter);
    }

    /**
     * Checks if the parameter is an parameter to be excluded (e.g., "token")
     *
     * @param $parameter
     * @return bool
     */
    private function isExcludedParameter($parameter)
    {
        return in_array($parameter, $this->excludedParameters);
    }

    /**
     * Checks, if the requester is allowed to filter the result
     *
     * @return mixed
     */
    private function allowsFilter()
    {
        return config('dingoquerymapper.allowFilters');
    }

    /**
     * Checks, if filters are set
     *
     * @return bool
     */
    private function hasFilters()
    {
        return (count($this->filters) > 0);
    }
}
