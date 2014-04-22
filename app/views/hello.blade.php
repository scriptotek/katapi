@extends('layouts.master')

@section('content')

<h1>
	katapi
</h1>
<p>
	Library metadata transducer
</p>

<h2>Examples</h2>
<?php
	$example1 = URL::action('BibsysController@getShow', array('id' => '132038137'));
	$example2 = URL::action('BibsysController@getShow', array('id' => '12k189510'));
	$example3 = URL::action('BibsysController@getShow', array('id' => '050076NA0'));
	$example4 = URL::action('BibsysController@getShow', array('id' => '1-107-01395-X'));
?>

<ul>
	<li>
		Lookup BIBSYS object by <em>objektid</em>:<br>
		<a href="{{ $example1 }}">{{ $example1 }}</a>		
	</li>
	<li>
		Lookup BIBSYS object by <em>dokid</em>: <br>
		<a href="{{ $example2 }}">{{ $example2 }}</a>		
    </li>
	<li>
		Lookup BIBSYS object by <em>knyttid</em>: <br>
		<a href="{{ $example3 }}">{{ $example3 }}</a>
    </li>
    <li>
		Lookup BIBSYS object by <em>isbn</em> (ISBN10 or 13, hyphens optional): <br>
		<a href="{{ $example4 }}">{{ $example4 }}</a>
    </li>
</ul>


@stop
