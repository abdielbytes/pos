<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('clients', function(Blueprint $table)
		{
			$table->engine = 'InnoDB';
			$table->integer('id', true);
			$table->string('name');
			$table->integer('code');
			$table->string('email', 192);
			$table->string('country');
			$table->string('city');
			$table->string('phone');
			$table->string('adresse');

            $table->string('tracker_id')->nullable();
            $table->string('tracker_model')->nullable();
            $table->string('google_map_address')->nullable();
            $table->string('customeravatar')->nullable();
            $table->string('g1avatar')->nullable();
            $table->string('g2avatar')->nullable();

            $table->string('client_house_image')->nullable();

            $table->string('Guarantor1_name')->nullable();
            $table->string('Guarantor1_phone')->nullable();
            $table->string('Guarantor1_house')->nullable();

            $table->string('Guarantor2_name')->nullable();
            $table->string('Guarantor2_phone')->nullable();
            $table->string('Guarantor2_house')->nullable();


            $table->timestamps(6);
			$table->softDeletes();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('clients');
	}

}
