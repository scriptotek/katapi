@extends('layouts.master')

@section('content')

<h2>
	<i class="fa fa-book"></i>
	{{ $doc->title }} ({{ $doc->year }})
</h2>

Record ID: {{ $doc->bibsys_id }}.

<a href="{{ URL::action('DocumentsController@getShow', array('id' => $doc->bibsys_id, 'format' => 'json')) }}">JSON representation</a>. MARC21 source data from
<a href="http://sru.bibsys.no/search/biblioholdings?operation=searchRetrieve&amp;version=1.1&amp;startRecord=1&amp;maximumRecords=10&amp;recordSchema=marcxchange&amp;query=bs.objektid%3D{{ $doc->bibsys_id }}">SRU</a>
/
<a href="http://oai.bibsys.no/oai/repository?verb=GetRecord&amp;metadataPrefix=marcxchange&amp;identifier=oai:bibsys.no:collection:{{ $doc->bibsys_id }}">OAI-PMH</a>


<div class="panel panel-default panel-descr">
  <!-- Default panel contents -->
  <div class="panel-heading">Description</div>

  <div class="panel-body">



	<table class="table">
		<tr>
			<td style="width:25%;">
				Material:
			</td>
			<td>
				{{$doc->material}} {{ $doc->electronic ? ' (electronic)' : ' (not electronic)' }}

				@if ($doc->other_form)
					<div>
						<i class="fa fa-hand-o-right"></i> <a href="{{ $doc->other_form['uri'] }}">
						{{ $doc->electronic ? 'A printed' : 'An electronic' }} edition</a>
						is also available
					</div>
				@endif
			</td>
		</tr>
		<tr>
			<td>
				Publisher:
			</td>
			<td>
				{{ $doc->publisher }}
			</td>
		</tr>
		<tr>
			<td>
				ISBNs:
			</td>
			<td>
				<ul>
					@foreach ($doc->isbns as $val)
					<li>
						{{ $val }}
					</li>
					@endforeach
				</ul>
			</td>
		</tr>
		<tr>
			<td>
				Creators:
			</td>
			<td>
				<ul>
				@foreach ($doc->authors as $val)
					<li>
						@if (isset($val['bibsys_identifier']))
							<a href="http://tools.wmflabs.org/bsaut/show/{{ array_get($val, 'bibsys_identifier', '') }}">{{ $val['name'] }}</a>
						@else
							{{ $val['name'] }}
						@endif
						({{ array_get($val, 'role', 'unknown role') }})
					</li>
					@endforeach
				</ul>
			</td>
		</tr>
		@if (count($doc->series))
		<tr>
			<td>
				Series:
			</td>
			<td>
				<ul>
				@foreach ($doc->series as $val)
					<li>
						{{ array_get($val, 'title', 'no title') }}
					</li>
				@endforeach
				</ul>
			</td>
		</tr>
		@endif
	</table>
</div>
</div>

{{-- ========================== QUALITY CHECK ========================== --}}

<?php
$problems = array();
if (count($doc->classes) == 0) {
	$problems[] = 'No classification numbers assigned yet.';
}
if (count($doc->subjects) == 0) {
	$problems[] = 'No subject headings assigned yet.';
}
foreach ($doc->holdings as $h) {
	if (isset($h['callcode']) && $h['callcode'] == '-') {
		$problems[] = 'No call code assigned to the copy ' . $h['id'] . ' yet.';
	}
}
?>

@if (count($problems) != 0)

<div class="panel panel-default inlinelists">
  <!-- Default panel contents -->
  <div class="panel-heading">Record quality</div>

  <div class="panel-body">

	@foreach ($problems as $problem)

	<div class="text-danger">
		<span class="fa fa-warning"></span>
		<em>{{ $problem }}</em>
	</div>

	@endforeach
  </div>

</div>
@endif

{{-- ====================== SUBJECTS AND CLASSES ====================== --}}

<?php

$subjectsAndClasses = [];

foreach ($doc->subjects as $subj) {

	if (count($subjectsAndClasses) == 0 || $subjectsAndClasses[count($subjectsAndClasses) - 1]['code'] != $subj['vocabulary']) {
		$subjectsAndClasses[] = array(
			'code' => $subj['vocabulary'],
			'name' => array_get(Subject::$vocabularies, $subj['vocabulary'], $subj['vocabulary']),
			'items' => array(),
		);
	}

	$el = array(
		'url' => URL::to($subj['uri']),
		'term' => isset($subj['indexTerm']) ? $subj['indexTerm'] : 'n/a',
		'extras' => array(),
	);

	$subjectsAndClasses[count($subjectsAndClasses) - 1]['items'][] = $el;
}

foreach ($doc->classes as $class) {

	if (count($subjectsAndClasses) == 0 || $subjectsAndClasses[count($subjectsAndClasses) - 1]['code'] != $class['system']) {
		$subjectsAndClasses[] = array(
			'code' => $class['system'],
			'name' => $class['system'],
			'items' => array(),
		);
	}

	$el = array(
		'url' => URL::to($class['uri']),
		'term' => isset($class['number']) ? $class['number'] : 'n/a',
		'extras' => array(),
	);
	if (isset($class['edition'])) {
		$el['extras'][] = 'ed. ' . $class['edition'];
	}
	if (isset($class['assigning_agency'])) {
		$el['extras'][] = 'assigned by ' . $class['assigning_agency'];
	}

	$subjectsAndClasses[count($subjectsAndClasses) - 1]['items'][] = $el;
}

?>
<div class="panel panel-default inlinelists">
  <!-- Default panel contents -->
  <div class="panel-heading">Subjects and classes</div>

  <div class="panel-body">

    @foreach ($subjectsAndClasses as $voc)
		<div style="margin-top:.5em">
			<span style="color:#888; font-size:80%; font-style:italic; padding-left:.3em;display:inline-block;">
				{{ $voc['name'] }}
			</span>
			<ul>
			    @foreach ($voc['items'] as $itm)
					<li>
						<a href="{{ $itm['url'] }}"><span class="fa fa-tag"></span> {{ $itm['term'] }}</a>
						@if (count($itm['extras']) != 0)
							<span style="color:#888;">
								({{ implode(', ', $itm['extras']) }})
							</span>
						@endif
					</li>
			    @endforeach
			</ul>
		</div>
    @endforeach

  </div>

</div>

{{-- ============================= HOLDINGS ============================= --}}

<div class="panel panel-default">
  <!-- Default panel contents -->
  <div class="panel-heading">Copies</div>

  <!-- List group -->
  <ul class="list-group">

	@foreach ($doc->holdings as $holding)
	<li class="list-group-item">
		<span class="fa {{ $holding['circulation_status'] == 'Available' ? 'fa-check-circle text-success' : 'fa-times-circle text-warning' }}"></span>

		{{ $holding['location'] }} –
		{{ $holding['shelvinglocation'] }}
		{{ isset($holding['callcode']) ? '– ' . $holding['callcode'] : '' }} :
		{{
			$holding['circulation_status']
		}}{{
			isset($holding['use_restrictions']) ? ', ' . $holding['use_restrictions'] : ''
		}}
		<br>ID: {{ $holding['id'] }}{{
			isset($holding['acquired']) ? ', acquired: ' . $holding['acquired']->toDateString() : ''
		}}
		@foreach ($holding['public_notes'] as $note)
		   <br>Note: {{ $note }}
		@endforeach
		@foreach ($holding['fulltext'] as $fulltext)
			<br>
			<a href="{{ $fulltext['url'] }}">
			Fulltext from {{ $fulltext['provider'] }}
			</a>
		@endforeach
	</li>
	@endforeach
  </ul>
</div>


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


<hr>
<p style="font-size: 10px">
	{{$doc->served_by == 'local_db' ? 'Record served from local database updated [add date here]'
	 : 'Record not found in local DB, served directly from BIBSYS SRU service'}}
</p>

@stop