<?php

use Scriptotek\Sru\Client as SruClient;
use Scriptotek\SimpleMarcParser\Parser;
use \Guzzle\Http\Client as HttpClient;

class DocumentsController extends BaseController {

	protected $userAgent = 'KatApi/0.1';

	protected $query = 'rec.identifier="{{id}}"';

	public function getIndex()
	{
		// show search box?
		return View::make('hello');
	}

	/**
	 *  Does content negotiation and 303 redirect
	 */
	public function getId($id)
	{
		return parent::negotiateContentType('DocumentsController', array(
			'id' => $id
		));
	}

	public function getShow($id, $format)
    {
        $live = (Request::get('live', 'true') == 'true');

		$id = strtolower(trim($id));
		$id = str_replace('-', '', $id);
		$id = filter_var($id, FILTER_VALIDATE_REGEXP, array('options' => array('regexp'=>'([a-z0-9]+)')));
		if (empty($id)) {
			return $this->abort(404, 'Empty or invalid id');
		}

        $rec = array('id' => $id);

        if ($live) {
            $doc = null;
        } else {
            $doc = Document::where('bibsys_id', '=', $id)  // objektid
                           ->orWhere('barcode', '=', $id)  // knyttid
                           ->first();
        }
		if ($doc) {

			// Document is fetched from our local DB
			$doc->served_by = 'local_db';

		} else {

			// Document is fetched from the BIBSYS SRU service

			Clockwork::startEvent('fetchRecord', 'Fetch record from BIBSYS');
			try {
				$bs = new BibsysService;
				$record = $bs->lookupId($id);
            } catch (\Guzzle\Http\Exception\CurlException $e) {
				return $this->abort(503, 'Sorry, no contact with BIBSYS at the moment');
            }
			Clockwork::endEvent('fetchRecord');

            if (is_null($record)) {
            	return $this->abort(404, 'Record not found');
            }

			Clockwork::startEvent('parseRecord', 'Parse record into Document model');

			$doc = new Document();
			$doc->served_by = 'bibsys_sru';

			// Assign a temporary ID. This is done so we can attach subjects
			// Without an ID, $doc->subjects() will return all subjects not
			// attached to any documents!
			$doc->_id = new MongoId;

			try {
				$doc->import($record);
			} catch (Exception $e) {
				$msg = "[$id] Failed to parse record. Exception '" . $e->getMessage() . "' in: " . $e->getFile() . ":" . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString();
				Log::error($msg);
				return $this->abort(503, 'Failed to parse record. More info in the server log.');
			}
			Clockwork::endEvent('parseRecord');

		}

		switch ($format) {

			case 'rdf.xml':
			case 'rdf.nt':
			case 'rdf.n3':
			case 'rdf.jsonld':
				App::abort(400, 'Format not supported yet');

			case 'json':
				$res = $doc->toArray();
				return Response::json($res);

			case 'html':
				return View::make('documents.show', array(
					'doc' => $doc
				));

			default:
				App::abort(400, 'Unknown format requested');
		}

	}

	public function getSearch()
	{

		// TODO
		if (!isset($_GET['query'])) {
			App::abort(404, 'No query given.');
		}
		$cql = Input::get('query');

		$nextRecordPosition = intval(Input::get('continue', '1'));

		Clockwork::startEvent('fetchRecords', 'Fetching records from BIBSYS');
		$bs = new BibsysService;
		$res = $bs->search($cql, $nextRecordPosition, 25);

		Clockwork::endEvent('fetchRecords');

		if (isset($res->error)) {
			$results = array(
				'documents' => array(),
				'error' => $res->error,
			);
			return Response::json($results);
		}

		Clockwork::startEvent('parseRecords', 'Parsing records into our Document model');
		$records = array_map(function($res) {

			$doc = new Document();
			$doc->served_by = 'bibsys_sru';

			// Assign a temporary ID. This is done so we can attach subjects
			// Without an ID, $doc->subjects() will return all subjects not
			// attached to any documents!
			$doc->_id = new MongoId;

			try {
				$doc->import($res->data);
			} catch (Exception $e) {
				print "Failed to parse record. Exception '" . $e->getMessage() . "' in: " . $e->getFile() . ":" . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString();

				Log::error("Failed to parse record. Exception '" . $e->getMessage() . "' in: " . $e->getFile() . ":" . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString());
				App::abort(503, 'Failed to parse record. More info in the server log.');
			}

			return $doc;

		}, $res->records);
		Clockwork::endEvent('parseRecords');

		Clockwork::startEvent('serializeRecords', 'Serializing records to JSON');
		$docs = array_map(function($doc) {

			return $doc->toArray();

		}, $records);
		Clockwork::endEvent('serializeRecords');

		$results = array(
			'numberOfRecords' => $res->numberOfRecords,
			'nextRecordPosition' => $res->nextRecordPosition,
			'documents' => $docs,
		);

		return Response::json($results);
	}

}
