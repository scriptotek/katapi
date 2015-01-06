<?php

class BaseController extends Controller {

    protected $validExtensions = array('.json', '.html');

    /**
	 * Setup the layout used by the controller.
	 *
	 * @return void
	 */
	protected function setupLayout()
	{
		if ( ! is_null($this->layout))
		{
			$this->layout = View::make($this->layout);
		}
	}

    /**
     * @param $format
     * @return null|string
     */
    public function getRequestFormat($format = null)
	{
		$m = preg_match('#.*/.*?\.([a-z.]{2,4})$#', Request::path(), $matches);
		if ($m) {
			return $matches[1];
		}
		if (!is_null($format)) {
			return $format;
		}
		if (Request::has('format')) {
			return Request::get('format');
		}
		foreach(Request::getAcceptableContentTypes() as $type){
	        $format = Request::getFormat($type);
	        return $format;
	    }
	}

    protected function getFormat($value) {
        $ext = substr($value, strrpos($value, '.'));
        if (in_array($ext, $this->validExtensions)) {
            $value = substr($value, 0, strlen($value) - strlen($ext));
            return array($value, $ext);
        }
        return array($value, null);
    }

	public function negotiateContentType($controller, $routeParams, $extendable)
	{
		switch ($this->getRequestFormat()) {

			case 'json':
				$routeParams[$extendable] .= '.json';
				break;

			case 'html':
				$routeParams[$extendable] .= '.html';
				break;

			case 'rdf.xml':
			case 'rdf.nt':
			case 'rdf.n3':
			case 'rdf.jsonld':
				App::abort(400, 'Format not supported yet');

			default:
				App::abort(400, 'Unknown format requested');
		}

		return Redirect::action($controller . '@getShow', $routeParams, 303);
	}

	public function abort($code, $msg)
	{
		$format = $this->getRequestFormat();

		if ($format == 'html') {
			return Response::view('errors.missing', array(
				'message' => $msg ?: 'Page not found',
				'code' => $code,
			), $code);
		} else if ($format == 'json') {
			return Response::json(array(
				'error' => array(
					'message' => $msg ?: 'Page not found',
					'code' => $code,
				)
			), $code);
		}
	}

}