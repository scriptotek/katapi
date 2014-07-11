@extends('layouts.master')

@section('content')

<h2>Samling 42</h2>

Dokumenter (BIBSYS: Objekter) i samlingen: {{ $documentCount }}<br>
Dokumentinstanser (BIBSYS: Dokumenter): {{ $documentInstanceCount }}<br>

Emneord (unike): {{ $subjectCount }}<br>
Emneordsinstanser: {{ $subjectInstanceCount }}<br>
Antall emneord per dokument (snitt): {{ sprintf('%.1f', $subjectInstanceCount / $documentCount) }}

@stop