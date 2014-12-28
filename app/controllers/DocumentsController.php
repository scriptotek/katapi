<?php

use Scriptotek\Sru\Client as SruClient;
use Scriptotek\SimpleMarcParser\Parser;
use \Guzzle\Http\Client as HttpClient;

class DocumentsController extends BaseController {

	protected $userAgent = 'KatApi/0.1';

	protected $query = 'rec.identifier="{{id}}"';

	/**
	 * @api {get} /documents/show/:id Request single document
	 * @apiName getShow
	 * @apiGroup Document
	 *
	 * @apiParam {Number} id ID of the document. Can be Bibsys dokid, objektid or knyttid, or ISBN.
	 * @apiParam {String} [format=json] Format
	 * @apiExample Example usage:
	 * curl -i http://katapi.biblionaut.net/documents/show/132038137
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
	 * @apiSuccessExample {json} Success-Response:
	 *     HTTP/1.1 200 OK
	 *     {
     *       "served_by": "bibsys_sru",
     *       "bibsys_id": "050117416",
     *       "material": "Book",
     *       "electronic": false,
     *       "agency": "NO-TrBIB",
     *       "record_modified": "2014-11-05 10:54:51",
     *       "record_created": "2014-11-05 00:00:00",
     *       "is_series": false,
     *       "is_multivolume": false,
     *       "catalogingRules": "katreg",
     *       "title": "Scattering : scattering and inverse scattering in pure and applied science",
     *       "part_no": "Vol. 1",
     *       ...
     *     }
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

	/**
	 * @api {get} /documents Search for documents
	 * @apiName getIndex
	 * @apiGroup Document
	 *
	 * @apiParam {String} [q] Query
	 * @apiParam {String} [format=json] Format
	 * @apiExample Example usage:
	 * curl -i http://katapi.biblionaut.net/documents?q=Hello
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
	 * @apiSuccessExample {json} Success-Response:
	 *     HTTP/1.1 200 OK
	 *     {
	 *       "numberOfRecords": 10,
	 *       "nextRecordPosition": null,
	 *       "documents": [
     *          {
     *            "bibsys_id": "940535580",
     *            "material": "Book"
     *            "title": "Reflections on the Higgs system",
     *            ...
     *          },
     *          {
     *            "bibsys_id": "960793933",
     *            "material": "Book"
     *            "title": "Search for the standard model Higgs boson at LEP200 with the DELPHI detector",
     *            ...
     *          },
     *          ...
	 *       ]
	 *     }
	 * @apiErrorExample {json} Error-Response:
	 *     HTTP/1.1 200 OK
	 *     {
	 *       "numberOfRecords": 0,
	 *       "nextRecordPosition": null,
	 *       "documents": []
	 *     }
	 *
	 *
	 */
	public function getIndex()
	{

		// TODO: Some default action, show search box?
		if (!isset($_GET['q'])) {
			App::abort(404, 'No query given.');
		}
		$cql = Input::get('q');

		$nextRecordPosition = intval(Input::get('continue', '1'));
		$count = intval(Input::get('count', '25'));

		Clockwork::startEvent('fetchRecords', 'Fetching records from BIBSYS');
		$bs = new BibsysService;
		$res = $bs->search($cql, $nextRecordPosition, $count);

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
