<?php

namespace Shanginn\Relaqs\Eloquent\Filters;

use Illuminate\Database\Eloquent\Builder;
use Shanginn\Relaqs\Eloquent\Exceptions\TooMuchColumnDelimitersException;
use Shanginn\Relaqs\Eloquent\Exceptions\UnbalancedParenthesesException;

class NestedStringFilter
{
    const OPEN_BRACKET = '(';
    const CLOSE_BRACKET = ')';
    const COLUMN_DELIMITER = ':';
    const OR_SIGN = '|';
    const AND_SIGN = ',';
    const ESCAPE_CHAR = '\\';
    const ARRAY_DELIMITER = ' ';

    /**
     * Unprocessed string with filters
     *
     * @var string
     */
    protected $filterString;

    /**
     * Current position in the $filterString
     *
     * @var int
     */
    protected $position = 0;

    /**
     * List of the table fields with types
     *
     * @var array
     */
    protected $fields;

    /**
     * NestedStringFilter constructor.
     *
     * @param string $filterString
     *
     * @param array $fields
     * @throws UnbalancedParenthesesException
     */
    public function __construct(string $filterString, array $fields = [])
    {
        if (!$this->checkParenthesesBalance($filterString)) {
            throw new UnbalancedParenthesesException;
        }

        $this->filterString = $filterString;
        $this->fields = $fields;
    }

    public function applyTo(Builder $query)
    {
        // Encapsulated method to prevent unexpected
        // changes of the position.
        return $this->performOn($query);
    }

    protected function performOn(Builder $query)
    {
        // Initial empty filters array
        $filter = $this->initFilterArray();

        // Default boolean for next Where operation
        $nextBoolean = 'and';

        // We will go through filter string only once
        // char by char to build and execute
        // (maybe) nested where queries.
        while ($this->position < strlen($this->filterString)) {
            switch ($char = $this->filterString[$this->position++]) {
                // It means we stepping into group of filters
                // so we call this function recursively
                // on a newly created nested query
                case static::OPEN_BRACKET:
                    $query->where(function (Builder $query) {
                        $this->performOn($query);
                    }, null, null, $nextBoolean);
                    break;

                // Finish nested query and exit the loop.
                // (We are inside nested call right now,
                // so we can do it safely).
                case static::CLOSE_BRACKET:
                    $this->executeQueryAndResetFilter($query, $filter, $nextBoolean);
                    break 2;

                // This symbols means, that we has successfully
                // built $filter array and can perform query.
                // And choose boolean for the next one.
                case static::OR_SIGN:
                case static::AND_SIGN:
                    $this->executeQueryAndResetFilter($query, $filter, $nextBoolean);
                    $nextBoolean = $char === static::AND_SIGN ? 'and' : 'or';
                    break;

                // When we reach column delimiter we try
                // to fill next sector in the $filer
                // Column, operator or value
                case static::COLUMN_DELIMITER:
                    // But can't understand more than 2 column delimiters
                    // in context of the one filter.
                    if (next($filter) === false) {
                        throw new TooMuchColumnDelimitersException;
                    }
                    break;

                // Just increment position counter and
                // append next char to the filter.
                case static::ESCAPE_CHAR:
                    $char = $this->filterString[$this->position++];
                    // In case of backslash we will append any next char to the filter

                // Every other symbol will go to corresponding
                // segment of the $filter array
                default:
                    $filter[key($filter)] .= $char;
            }
        }

        // Execute query In case there is
        // anything left in the $filter
        $this->executeQueryAndResetFilter($query, $filter, $nextBoolean);

        return $query;
    }

    protected function executeQueryAndResetFilter(Builder $query, array &$filter, string $boolean)
    {
        // If filter is fully stacked
        // and has 3 extracted variables
        if (key($filter) === 'value' && extract($filter) === 3) {
            /**
             * Variables extracted from $filter array
             *
             * @var string $column
             * @var string $operator
             * @var string $value
             */
            $column = snake_case($column);

            if (($this->fields[$column] ?? false) === 'jsonb' && $operator === 'in') {
                $operator = '?|';
            }

            if ($column && $operator && strlen($value)) {
                switch ($operator) {
                    case '!in':
                        $not = true;
                        // Proceed just like 'in'
                    case 'in':
                        $value = explode(static::ARRAY_DELIMITER, $value);
                        $query->whereIn($column, $value, $boolean, $not ?? false);

                        break;
                    case '?|':
                        $value = sprintf(
                            '{%s}',
                            str_replace(static::ARRAY_DELIMITER, ',', $value)
                        );

                        // In case of search in array we need to
                        // convert value to PostgreSQL array
                    default:
                        $query->where($column, $operator, $value, $boolean);
                }
            }

            $filter = $this->initFilterArray();
        }
    }

    protected function initFilterArray()
    {
        return ['column' => '', 'operator' => '', 'value' => ''];
    }

    /**
     * Checks amount of open and closed parentheses,
     * also if they the are in the right order.
     * Nesting and other symbols are allowed.
     *
     * @param  string  $string String to test the balance on.
     * @return boolean (true: balanced | false: unbalanced)
     */
    protected function checkParenthesesBalance($string)
    {
        // Keep track of number of open parens
        $open = 0;

        // Loop through each char
        for ($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];
            switch ($char) {
                case static::OPEN_BRACKET:
                    $open++;
                    break;
                case static::CLOSE_BRACKET:
                    $open--;
                    if ($open < 0) {
                        return false;
                    }
                    break;
            }
        }

        // there must be an equal amount of opened and closed parens
        return ($open === 0);
    }
}
