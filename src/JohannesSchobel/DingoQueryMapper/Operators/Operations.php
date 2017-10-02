<?php

namespace JohannesSchobel\DingoQueryMapper\Operators;

interface Operations
{
    /**
     * Get the entire result
     *
     * @return mixed
     */
    public function get();

    /**
     * Get the result paginated
     *
     * @param $page
     * @param $limit
     * @return mixed
     */
    public function paginate($page, $limit);

    /**
     * Sort the result using sort criteria
     *
     * @param array $sorts
     * @return mixed
     */
    public function sort(array $sorts);

    /**
     * Filter the result using filter criteria
     *
     * @param array $filters
     * @return mixed
     */
    public function filter(array $filters);
}
