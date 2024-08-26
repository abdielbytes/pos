<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToTransfersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('transfers', function(Blueprint $table)
		{
			$table->foreign('from_branch_id', 'from_branch_id')->references('id')->on('branches')->onUpdate('RESTRICT')->onDelete('RESTRICT');
			$table->foreign('to_branch_id', 'to_branch_id')->references('id')->on('branches')->onUpdate('RESTRICT')->onDelete('RESTRICT');
			$table->foreign('user_id', 'user_id_transfers')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('transfers', function(Blueprint $table)
		{
			$table->dropForeign('from_branch_id');
			$table->dropForeign('to_branch_id');
			$table->dropForeign('user_id_transfers');
		});
	}

}
