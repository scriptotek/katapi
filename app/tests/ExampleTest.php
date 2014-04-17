<?php

class ExampleTest extends TestCase {

	/**
	 * A basic functional test example.
	 *
	 * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
	 * @return void
	 */
	public function testBasicExample()
	{
		$this->call('GET', '/documents/bibsys/0000');
	}

}