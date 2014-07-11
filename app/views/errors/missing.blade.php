@extends('layouts.master')

@section('content')
<h1>
	404
</h1>
<p>
@if ($message)
	{{ $message }}
@else
	URL has been eaten!
@endif 
</p>
<p>
	<a href="/">Go back to start?</a>
</p>

@stop
