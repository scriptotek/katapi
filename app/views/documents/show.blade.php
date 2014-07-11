@extends('layouts.master')

@section('content')

<div style="float:right;"><a href="{{ URL::action('DocumentsController@getShow', array('id' => $doc->bibsys_id, 'format' => 'json')) }}">View as JSON</a></div>


<h2>{{ $doc->title }}</h2>

Objektid: {{ $doc->bibsys_id }}.
View MARC21
<a href="http://sru.bibsys.no/search/biblioholdings?operation=searchRetrieve&amp;version=1.1&amp;startRecord=1&amp;maximumRecords=10&amp;recordSchema=marcxchange&amp;query=bs.objektid%3D951909568">from SRU</a>
<a href="http://oai.bibsys.no/oai/repository?verb=GetRecord&amp;metadataPrefix=marcxchange&amp;identifier=oai:bibsys.no:biblio:951909568">from OAI</a>


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