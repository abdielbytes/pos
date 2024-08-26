<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\branch;
use App\Models\User;

class CreateUserbranchTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('branch_user', function(Blueprint $table)
		{
			$table->engine = 'InnoDB';
			$table->integer('user_id')->index('branch_user_user_id');
			$table->integer('branch_id')->index('branch_user_branch_id');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('branch_user');
	}

}
