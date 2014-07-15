@extends('layouts.master')

@section('content')

<p>
	Library metadata transducer. API is <em>not</em> stable. <a href="https://github.com/scriptotek/katapi">More info</a>
</p>
<p>
	Use <tt>Accept: application/json</tt> (or add <tt>?format=json</tt>) to get the JSON representation of a resource.
	More formats might be available in the future. HTML representations are quite limited currently.
</p>

<h2>GET /documents/show/:id</h2>
<?php
	$example1 = URL::action('DocumentsController@getShow', array('id' => '132038137'));
	$example2 = URL::action('DocumentsController@getShow', array('id' => '12k189510'));
	$example3 = URL::action('DocumentsController@getShow', array('id' => '050076NA0'));
	$example4 = URL::action('DocumentsController@getShow', array('id' => '1-107-01395-X'));
	$example5 = URL::action('DocumentsController@getShow', array('id' => '841188564'));
	$exampleS1 = URL::action('SubjectsController@getShow', array(
		'vocabulary' => 'noubomn', 'term' => 'Steganografi'
	));
?>

<p>
	Returns a single Document (book, journal, media), specified by the :id parameter.
	Holdings information (information about specific copies of the document) will also be
	embedded within the document.
</p>

<em>Examples:</em>
<ul>
	<li>
		:id is a <em>BIBSYS objektid</em>:<br>
		<a href="{{ $example1 }}">{{ $example1 }}</a>		
	</li>
	<li>
		:id is a <em>BIBSYS dokid</em>:<br>
		<a href="{{ $example2 }}">{{ $example2 }}</a>		
    </li>
	<li>
		:id is a <em>BIBSYS knyttid</em>:<br>
		<a href="{{ $example3 }}">{{ $example3 }}</a>
    </li>
    <li>
		:id is an <em>ISBN</em> (ISBN10 or 13, hyphens optional): <br>
		<a href="{{ $example4 }}">{{ $example4 }}</a>
    </li>
    <li>
		Complex journal succeeding entry:<br>
        <a href="{{ $example5 }}">{{ $example5 }}</a>
    </li>
</ul>

<h2>GET /subjects/:vocabulary/:term</h2>
<p>
	Returns a single Subject, specified by a :vocabulary (an LC subject source code) 
	and :term (an index term).
	Term usage is embedded in the returned object.
</p>
<ul>
	<li>
		The term "Steganografi" in the vocabulary "noubomn" (Realfagstermer):<br>
		<a href="{{ $exampleS1 }}">{{ $exampleS1 }}</a>		
	</li>
</ul>


<h2>GET /documents/search</h2>
…

<h2>GET /authors/show/:id</h2>
…

<h2>GET /authors/search</h2>
…

<h2>GET /covers/show/:id</h2>
Returns a list of available covers for a given Document. The first cover is the preferred cover.
…

<h2>POST /covers/select/:document/:cover</h2>
Set the preferred cover for a given document
…

<h2>POST /covers/store/:document/:cover</h2>
Store a cover for the given document.
…



@stop
