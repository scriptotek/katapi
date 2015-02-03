<?php

class TestCase extends Illuminate\Foundation\Testing\TestCase {

	/**
	 * Creates the application.
	 *
	 * @return \Symfony\Component\HttpKernel\HttpKernelInterface
	 */
	public function createApplication()
	{
		$unitTesting = true;
		$testEnvironment = 'testing';
		return require __DIR__ . '/../../bootstrap/start.php';
	}

	/**
	 * Test that the array $value contains the keys $keys
	 *
	 * @return \Symfony\Component\HttpKernel\HttpKernelInterface
	 */
	public function assertArrayKeysEquals($keys, $value)
	{
		$actualKeys = array_keys($value);
		sort($actualKeys);
		sort($keys);
		$this->assertEquals($keys, $actualKeys);
	}

}
