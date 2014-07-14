@extends('layouts.master')

@section('content')

<div style="float:right;"><a href="{{ URL::action('DocumentsController@getShow', array('id' => $doc->bibsys_id, 'format' => 'json')) }}">View as JSON</a></div>


<h2>{{ $doc->title }}</h2>

Objektid: {{ $doc->bibsys_id }}.
View MARC21
<a href="http://sru.bibsys.no/search/biblioholdings?operation=searchRetrieve&amp;version=1.1&amp;startRecord=1&amp;maximumRecords=10&amp;recordSchema=marcxchange&amp;query=bs.objektid%3D951909568">from SRU</a>
<a href="http://oai.bibsys.no/oai/repository?verb=GetRecord&amp;metadataPrefix=marcxchange&amp;identifier=oai:bibsys.no:biblio:951909568">from OAI</a>

@if ($doc->other_form)
	Finnes også som <a href="{{ $doc->other_form['uri'] }}">
		{{ $doc->electronic ? 'trykt utgave' : 'elektronisk utgave' }}
	</a>
@endif

<table class="table">
	<tr>
		<td>
			Material:
		</td>
		<td>
			{{$doc->material}}
		</td>
	</tr>
	<tr>
		<td>
			Electronic:
		</td>
		<td>
			{{$doc->electronic ? 'Yes' : 'No'}}
		</td>
	</tr>
	<tr>
		<td>
			ISBNs:
		</td>
		<td>
			<ul>
				@foreach ($doc->isbns as $key => $val)
				<li>
					{{ $val }}
				</li>
				@endforeach
			</ul>
		</td>
	</tr>
</table>

<hr>
Subjects:
<ul>
	@foreach ($doc->subjects as $subj)
	<li>
		{{ isset($subj['term']) ? $subj['term'] : 'n/a'}}
		{{ isset($subj['vocabulary']) ? '<span style="color:#888;">(' . $subj['vocabulary'] . ')</span>' : ''}}
	</li>
	@endforeach
</ul>

Classifications:
<ul>
	@foreach ($doc->classifications as $class)
	<li>
		{{ isset($class['number']) ? $class['number'] : 'n/a'}}
		{{ isset($class['system']) ? '<span style="color:#888;">(' . $class['system'] .
			( isset($class['edition']) ? ' ed. ' . $class['edition'] : '') .
			( isset($class['assigning_agency']) ? ' assigned by ' . $class['assigning_agency'] : '') .
		 ')</span>' : ''}}
	</li>
	@endforeach
</ul>
<hr>

<?php
function arrayToTable($doc, $keys = null)
{
	if (is_object($doc) && (get_class($doc) == 'Carbon\Carbon')) {
		return $doc->toDateString();
	} else if (is_object($doc) && (get_class($doc) == 'MongoId')) {
		return (string) $doc;
	} else if (is_object($doc) || is_array(($doc))) {
		if (is_null($keys)) {
			$keys = is_object($doc) ? get_object_vars($doc) : array_keys($doc);
		}
		$s = '<table class="table">';
		foreach ($keys as $key) {
			$s .= '<tr>
				<td>
					' . $key . '
				</td>
				<td>
					' . arrayToTable($doc[$key]) . '
				</td>
			</tr>';
		}
		$s .= '</table>';
		return $s;
	} else {
		return $doc;
	}
}
?>

{{-- arrayToTable($doc, array_keys($doc->getAttributes())) --}}


@stop