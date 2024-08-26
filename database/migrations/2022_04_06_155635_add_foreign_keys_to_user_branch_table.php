<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToUserbranchTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('branch_user', function(Blueprint $table)
		{
			$table->foreign('user_id', 'branch_user_user_id')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
			$table->foreign('branch_id', 'branch_user_branch_id')->references('id')->on('branches')->onUpdate('RESTRICT')->onDelete('RESTRICT');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('branch_user', function(Blueprint $table)
		{
			$table->dropForeign('branch_user_user_id');
			$table->dropForeign('branch_user_branch_id');
		});
	}

}
