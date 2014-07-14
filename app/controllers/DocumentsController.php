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

	public function getShow($id)
	{
		$id = strtolower(trim($id));
		$id = str_replace('-', '', $id);
		$id = filter_var($id, FILTER_VALIDATE_REGEXP, array('options' => array('regexp'=>'([a-z0-9]+)')));
		if (empty($id)) {
			App::abort(404, 'Empty or invalid id');
		}

		$rec = array('id' => $id);
		$doc = Document::where('bibsys_id', '=', $id)  // objektid
		               ->orWhere('barcode', '=', $id)  // knyttid
		               ->first();
		if ($doc) {

			// Document is fetched from our local DB
			$doc->served_by = 'local_db';

		} else {

			// Document is fetched from the BIBSYS SRU service
			try {
				$bs = new BibsysService;
				$record = $bs->lookupId($id);
            } catch (\Guzzle\Http\Exception\CurlException $e) {
				App::abort(503, 'Sorry, no contact with BIBSYS at the moment');
            }
			$doc = new Document();
			$doc->served_by = 'bibsys_sru';

			// Assign a temporary ID. This is done so we can attach subjects
			// Without an ID, $doc->subjects() will return all subjects not
			// attached to any documents!
			$doc->_id = new MongoId;

			if (!$doc->import($record)) {
				App::abort(404, 'Failed to parse record. More info in the server log.');
			}

		}


		// Add links to guide the API user

		if (isset($doc->other_form) && isset($doc->other_form['id'])) {
			$of = $doc->other_form;
			$of['uri']= URL::action('DocumentsController@getShow', array($doc->other_form['id']));
			$doc->other_form = $of;
		}

		$links = array(
			array(
				'rel' => 'self',
				'uri' => URL::current()
			)
		);
		$doc->links = $links;


		switch ($this->getRequestFormat()) {
			case 'json':
				// JSON-LD...
				// $doc->{"@context"} = array(
				// 	"other_form" => array(
				// 		'uri' => array(
				// 		     "@id" => "http://katapi.biblionaut.net/docs#Document",
				// 		     "@type" => "@id",
				// 		)
				// 	)
				// );
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
		if (!isset($_GET['cql'])) {
			App::abort(404, 'No query given.');
		}
		$cql = $_GET['cql'];
		$cql = filter_var($cql, FILTER_SANITIZE_URL);

		$bs = new BibsysService;
		$results = $bs->search($cql);

		App::abort(400, 'Not implemented yet');

	}

}
