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
			$collection->unique('bibliographic.id');
			$collection->index('bibliographic.isbns');
			$collection->index('holdings.id');
			$collection->index('holdings.barcode');

			// TODO: Would be more effective to have these as sparse indexes,
			// but then we must fix it so simplemarcparser doesn't return
			// null values for the id fields, otherwise we'd gain nothing.
			$collection->index('holdings.part_of.id');
			$collection->index('holdings.series.id');
		});
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
