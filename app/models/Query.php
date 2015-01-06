<?php

class Query {

    protected $queryString;

    function __construct($queryString)
	{
        $this->queryString = $queryString;
    }

    function getQueryString() {
        return $this->queryString;
    }

}