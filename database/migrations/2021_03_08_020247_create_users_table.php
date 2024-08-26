<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('users', function(Blueprint $table)
		{
			$table->engine = 'InnoDB';
			$table->integer('id', true);
			$table->string('firstname');
			$table->string('lastname');
			$table->string('username', 192);
            $table->string('address', 192);

            $table->string('email', 192);
			$table->string('password');
			$table->string('avatar')->nullable();
            $table->integer('assignedbranches')->nullable()->index('assignedbranches');
            $table->string('g1avatar')->nullable();
            $table->string('g2avatar')->nullable();
            $table->string('house')->nullable();


            $table->string('phone', 192);
			$table->integer('role_id');
			$table->boolean('statut')->default(1);
			$table->timestamps(6);
			$table->softDeletes();
            $table->string('bank')->nullable();
            $table->string('account_number')->nullable();


            $table->string('Guarantor1_name')->nullable();
            $table->string('Guarantor1_phone')->nullable();
            $table->string('Guarantor1_house')->nullable();
            $table->string('Guarantor1_image')->nullable();
            $table->string('Guarantor1_house_image')->nullable();

            $table->string('Guarantor2_name')->nullable();
            $table->string('Guarantor2_phone')->nullable();
            $table->string('Guarantor2_house')->nullable();
            $table->string('Guarantor2_image')->nullable();
            $table->string('Guarantor2_house_image')->nullable();
		});
}



	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('users');
	}

}
