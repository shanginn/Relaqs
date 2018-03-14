<?php

namespace Shanginn\Relaqs\Eloquent\Interfaces;

interface Filtratable
{
    public static function addFiltersScope($filterString, $fields = []);
}
