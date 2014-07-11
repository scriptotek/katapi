@extends('layouts.master')

@section('content')

<h2>
@if ($message)
	Error {{ $code }} : {{ $message }}
@else
	URL has been eaten!
@endif 
</h2>
<p>
	<a href="/">Back to start?</a>
</p>

@stop
