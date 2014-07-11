<?php

class ExamplesController extends BaseController {


	public function getSamling42()
	{

		$documentCount = Document::where('sets', 'urealSamling42')->count();		

		$documentInstanceCount = DB::collection('documents')->raw(function($collection)
		{
			// db.documents.aggregate( {$match: {"sets": "urealSamling42"}}, { $unwind: "$holdings" }, { $match: {"holdings.shelvinglocation": "UREAL Samling 42" }}, { $group: { _id: null, count: { $sum: 1 }}})';
			return $collection->aggregate(array(

				// Limit by the name of the OAI set
				array('$match' => array(
					'sets' => 'urealSamling42'
				)),

				// Unwind holdings
				array('$unwind' => '$holdings'),

				// Then limit holdings by those that are part of the 42 collection
				array('$match' => array(
					'holdings.shelvinglocation' => 'UREAL Samling 42'
				)),

				// Finally sum
				array('$group' => array(
					'_id' => null, 
					'count' => array('$sum' => 1)
				)),
			));
		})['result'][0]['count'];

		$subjectCount = DB::collection('documents')->raw(function($collection)
		{
			// db.documents.aggregate( {$match: {"sets": "urealSamling42"}}, { $unwind: "$subject_ids" }, { $project: { 'subject_id' : '$subject_ids' }}, { $group: { _id: '$subject_id' }}, { $group: { _id: null, count: {$sum: 1}}} )
			return $collection->aggregate(array(

				// Limit by the name of the OAI set
				array('$match' => array(
					'sets' => 'urealSamling42'
				)),

				// Unwind holdings
				array('$unwind' => '$subject_ids'),

				// Save some memory (or try do so at least)
				array('$project' => array(
					'subject_id' => '$subject_ids'
				)),

				// Group by subject_id
				array('$group' => array(
					'_id' => '$subject_id', 
				)),

				// And count
				array('$group' => array(
					'_id' => null, 
					'count' => array('$sum' => 1)
				)),

			));
		})['result'][0]['count'];

		$subjectInstanceCount = DB::collection('documents')->raw(function($collection)
		{
			// db.documents.aggregate( {$match: {"sets": "urealSamling42"}}, { $unwind: "$holdings" }, { $match: {"holdings.shelvinglocation": "UREAL Samling 42" }}, { $group: { _id: null, count: { $sum: 1 }}})';
			return $collection->aggregate(array(

				// Limit by the name of the OAI set
				array('$match' => array(
					'sets' => 'urealSamling42'
				)),

				// Unwind holdings
				array('$unwind' => '$subject_ids'),

				// Finally sum
				array('$group' => array(
					'_id' => null, 
					'count' => array('$sum' => 1)
				)),
			));
		})['result'][0]['count'];

		return View::make('examples.samling42', array(
			'documentCount' => $documentCount,
			'documentInstanceCount' => $documentInstanceCount,
			'subjectCount' => $subjectCount,
			'subjectInstanceCount' => $subjectInstanceCount,
		));
	}

}
