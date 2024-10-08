<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToAdjustmentsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('adjustments', function(Blueprint $table)
		{
			$table->foreign('user_id', 'user_id_adjustment')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
			$table->foreign('branch_id', 'branch_id_adjustment')->references('id')->on('branches')->onUpdate('RESTRICT')->onDelete('RESTRICT');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('adjustments', function(Blueprint $table)
		{
			$table->dropForeign('user_id_adjustment');
			$table->dropForeign('branch_id_adjustment');
		});
	}

}
