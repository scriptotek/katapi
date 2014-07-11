<?php

use Illuminate\Database\Migrations\Migration;
use Jenssegers\Mongodb\Schema\Blueprint;

class CreateDocumentsCollection extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('documents', function(Blueprint $collection) {
		    $collection->unique('bibsys_id');
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
		Schema::drop('documents');
	}

}