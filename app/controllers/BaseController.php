<?php

class BaseController extends Controller {

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

	public function getRequestFormat()
	{
		if (Request::has('format')) {
			return Request::get('format');
		}
		foreach(Request::getAcceptableContentTypes() as $type){
	        $format = Request::getFormat($type);
	        return $format;
	    }
	}

}