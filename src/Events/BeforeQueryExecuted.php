<?php

namespace Shanginn\Relaqs\Events;

use Illuminate\Database\Eloquent\Builder;

class BeforeQueryExecuted
{

    public $column;

    public $operator;

    public $value;

    /**
     * @var Builder $query
     */
    public $query;

    /**
     * Create a new event instance.
     *
     * @param string        $column
     * @param string        $operator
     * @param string|array  $value
     * @param Builder       $query
     */
    public function __construct(&$column, &$operator, &$value, Builder &$query)
    {
        $this->column = &$column;
        $this->operator = &$operator;
        $this->value = &$value;
        $this->query = &$query;
    }
}