<?php

class DocumentTest extends TestCase {

	public function setUp()
	{
		parent::setUp();
		Artisan::call('migrate:refresh');
		//$this->seed();
	}

	public function documentFactory($attrs = [])
	{
		$doc = new Document;
		$doc->bibliographic = array_get($attrs, 'bibliographic', []);
		$doc->subjects = array_get($attrs, 'subjects', []);
		$doc->classifications = array_get($attrs, 'classifications', []);
		return $doc;
	}

	/**
	 * Document::link should throw Exception if unsaved, since no id has been assigned yet.
	 *
	 * @expectedException Exception
	 */
	public function testLinkAttributeOfUnsavedDocument()
	{
		$doc = new Document;
		$doc->link;
	}

	/**
	 * Document::link should return a link.
	 */
	public function testLinkAttributeOfSavedDocument()
	{
		$doc = $this->documentFactory();
		$saved = $doc->save();

		$this->assertTrue($saved);
		$this->assertTrue($doc->exists);
		$this->assertNotNull($doc->id);
		$this->assertEquals('http://localhost/documents/' . $doc->id, $doc->link);
	}

	/**
	 * Document::toArray() should include the expected keys
	 */
	public function testArraySerializationUsingMinimalDocument()
	{
		$doc = $this->documentFactory();
		$doc->save();

		$expectedKeys = ['_id', 'bibliographic', 'subjects', 'classifications',
			  'created_at', 'updated_at', 'link'];

		$this->assertArrayKeysEquals($expectedKeys, $doc->toArray());
	}

	/**
	 * subjects should be stored in DB
	 */
	public function testSubjectStorage()
	{
		$doc = $this->documentFactory([
			'subjects' => array(
				array('vocabulary' => 'noubomn', 'indexTerm' => 'Grevlinger'),
				array('vocabulary' => 'tekord', 'indexTerm' => 'Grevlinger', 'uri' => 'http://test'),
			)
		]);
		$doc->save();

		// Test that subject has been saved properly
		$subjects = $doc->getSubjects();
		$this->assertEquals('noubomn', $subjects[0]['vocabulary']);
		$this->assertEquals('Grevlinger', $subjects[0]['indexTerm']);
		$this->assertInternalType('string', $subjects[0]['assigned']);
		$this->assertArrayNotHasKey('uri', $subjects[0]);
		$this->assertEquals('http://test', $subjects[1]['uri']);

		$this->assertArrayKeysEquals(['vocabulary','indexTerm','assigned','internal_id','link'], $subjects[0]);
		$this->assertArrayKeysEquals(['vocabulary','indexTerm','assigned','internal_id','link', 'uri'], $subjects[1]);

		// Add additional data to the subject
		$subjects[0]['uri'] = 'http://some-uri';
		$doc->subjects = $subjects;
		$doc->save();

		// Test that the extra data was stored in the 'subjects collection'
		$subject = Subject::find($subjects[0]['internal_id']);
		$this->assertEquals('http://some-uri', $subject->uri);

		// Test that the new attribute is now available from the Document
		$subjects = $doc->getSubjects();
		$this->assertEquals('http://some-uri', $subjects[0]['uri']);
		$this->assertArrayKeysEquals(['vocabulary','indexTerm','assigned','internal_id','link','uri'], $subjects[0]);

		// Update the document:
		$doc->subjects = $subjects;
		$doc->save();

		// Testi that the new attribute wasn't overwritten:
		$subjects = $doc->getSubjects();
		$this->assertEquals('http://some-uri', $subjects[0]['uri']);
		$this->assertArrayKeysEquals(['vocabulary','indexTerm','assigned','internal_id','link', 'uri'], $subjects[0]);		
	}

	/**
	 * classifications should be stored in DB
	 */
	public function testClassificationsStorage()
	{
		$doc = $this->documentFactory([
			'classifications' => array(
				array('system' => 'ddc', 'number' => '530.12', 'edition' => '23'),
				array('system' => 'udc', 'number' => '530.12', 'edition' => null, 'uri' => 'http://test'),
			)
		]);
		$doc->save();

		// Test that subject has been saved properly
		$classes = $doc->getClassifications();
		$this->assertEquals('ddc', $classes[0]['system']);
		$this->assertEquals('530.12', $classes[0]['number']);
		$this->assertEquals('23', $classes[0]['edition']);

		$this->assertInternalType('string', $classes[0]['assigned']);
		$this->assertArrayNotHasKey('uri', $classes[0]);
		$this->assertEquals('http://test', $classes[1]['uri']);
		$this->assertNull($classes[1]['edition']);

		$this->assertArrayKeysEquals(['system','number','edition','assigned','internal_id','link'], $classes[0]);
		$this->assertArrayKeysEquals(['system','number','edition','assigned','internal_id','link', 'uri'], $classes[1]);

		// Add additional data to the subject
		$classes[0]['uri'] = 'http://some-uri';
		$doc->classifications = $classes; // TODO: fix
		$doc->save();

		// Test that the extra data was stored in the 'subjects collection'
		$cl = Classification::find($classes[0]['internal_id']);
		$this->assertEquals('http://some-uri', $cl->uri);

		// Test that the new attribute is now available from the Document
		$classes = $doc->getClassifications();
		$this->assertEquals('http://some-uri', $classes[0]['uri']);
		$this->assertArrayKeysEquals(['system','number','edition','assigned','internal_id','link','uri'], $classes[0]);

		// Update the document:
		$doc->classifications = $classes;
		$doc->save();

		// Testi that the new attribute wasn't overwritten:
		$classes = $doc->getClassifications();
		$this->assertEquals('http://some-uri', $classes[0]['uri']);
		$this->assertArrayKeysEquals(['system','number','edition','assigned','internal_id','link', 'uri'], $classes[0]);		
	}

}
