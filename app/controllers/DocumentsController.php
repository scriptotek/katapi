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
	 * @api {get} /documents/show/:id Request Document information
	 * @apiName GetDocument
	 * @apiGroup Document
	 *
	 * @apiParam {Number} ID of the document. Can be Bibsys dokid, objektid or knyttid, or ISBN.
	 * @apiExample Example usage:
	 * curl -i http://localhost/documents/show/132038137
	 *
	 * @apiSuccess {String} served_by Indicates if the Document was found in, and retrieved from the local database ('local_db'),
	 *                                or fetched directly from SRU ('bibsys_sru')
	 * @apiSuccess {String} bibsys_id The Bibsys 'objektid'
	 * @apiSuccess {String} material The material type. TODO: Document all the possible values
	 * @apiSuccess {Boolean} electronic Whether the document is electronic (true) or physical (false)
	 * @apiSuccess {Object[]} authors List of authors/creators
	 * @apiSuccess {String} authors.name Author/creator name
	 * @apiSuccess {String} authors.role E.g. 'main', 'aut', 'edt', 'added', ...
	 * @apiSuccess {String} authors.authority Bibsys authority identifier
	 *
	 */
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

			try {
				$doc->import($record);
			} catch (Exception $e) {
				Log::error("[$record->identifier] Failed to parse record. Exception '" . $e->getMessage() . "' in: " . $e->getFile() . ":" . $e->getLine() . "\nStack trace:\n" . $e->getTraceAsString());
				App::abort(503, 'Failed to parse record. More info in the server log.');
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
