<?php

use Illuminate\Database\Migrations\Migration;
use Jenssegers\Mongodb\Schema\Blueprint;

class CreateSubjectsCollection extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('subjects', function(Blueprint $collection) {
		    $collection->unique(array('vocabulary', 'indexTerm'));
		});

		// Prøve Aria?
		// DB::unprepared('ALTER TABLE `tablename` ENGINE=`Aria` TRANSACTIONAL=1;');
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('subjects');
	}

}