<?php

namespace JohannesSchobel\DingoQueryMapper\Operators;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CollectionOperator implements Operations
{
    protected $collection;
    protected $request;

    public function __construct(Collection $collection, Request $request)
    {
        $this->collection = $collection;
        $this->request = $request;
    }

    public function get()
    {
        return $this->collection;
    }

    public function paginate($page, $limit)
    {

        // the user has disabled the pagination!
        // so we manually set the size of the resultset
        if ($limit == 0) {
            $page = 1;
            if ($this->collection->isEmpty()) {
                $limit = config('dingoquerymapper.defaults.limit');
            } else {
                $limit = count($this->collection);
            }
        }

        $slice = $this->collection->forPage($page, $limit);

        return new LengthAwarePaginator(
            $slice, // only the items needed
            count($this->collection), // total amount of items
            $limit, // items per page
            $page, // current page
            [
                'path'  => $this->request->url(),
                'query' => $this->request->query(), // We need this so we can keep all old query parameters from the url
            ]
        );
    }

    public function sort(array $sorts)
    {
        $comparer = $this->callbackSearchable($sorts);
        $this->collection = $this->collection->sort($comparer);
    }

    public function filter(array $filters)
    {
        $filterer = $this->callbackFilterable($filters);
        $this->collection = $this->collection->filter($filterer);
    }

    private function callbackSearchable($criteria)
    {
        $callback = function ($first, $second) use ($criteria) {
            foreach ($criteria as $c) {
                // normalize sort direction
                $orderType = strtolower($c['direction']);
                if (strtolower($first[$c['column']]) < strtolower($second[$c['column']])) {
                    return $orderType === "asc" ? -1 : 1;
                } elseif (strtolower($first[$c['column']]) > strtolower($second[$c['column']])) {
                    return $orderType === "asc" ? 1 : -1;
                }
            }

            // all elements were equal
            return 0;
        };

        return $callback;
    }

    private function callbackFilterable($criteria)
    {
        $callback = function ($item) use ($criteria) {
            $attributes = $item->getAttributes();

            foreach ($criteria as $c) {
                // check, if the criteria to check is present
                if (!array_key_exists($c['key'], $attributes)) {
                    // attribute does not exist - continue with the next one
                    continue;
                }

                // the attribute exists - so check the operator and value
                $attribute = $item->getAttribute($c['key']);
                $rule = $this->createEvaluationRule($attribute, $c['operator'], $c['value']);

                $evalString = 'return(' . $rule . ');';
                $result = (boolean)eval($evalString);

                if ($result === false) {
                    return false;
                }
            }

            return true;
        };

        return $callback;
    }

    private function createEvaluationRule($key, $operator, $value)
    {
        // first, check the operator type!
        if ($operator == '=') {
            $operator = '==';
        }

        // escaping
        $key = addslashes($key);

        $rule = "'%s' %s '%s'"; // key, operator, value
        $rule = sprintf($rule, $key, $operator, $value);

        // now check if the operator was "(not) like"?
        if (strpos($operator, 'like') !== false) {
            $value = str_replace('%', '', $value);
            $rule = "substr('%s', 0, strlen('%s')) %s '%s'"; // haystack, $needle, $comparable, $needle
            $expectedResult = '===';

            if (stripos($operator, 'not') !== false) {
                // it is a NOT LIKE operator
                $expectedResult = '!==';
            }

            $rule = sprintf($rule, $key, $value, $expectedResult, $value);
        }

        return $rule;
    }
}
