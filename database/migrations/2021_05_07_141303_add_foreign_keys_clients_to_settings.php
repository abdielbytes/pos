<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysClientsToSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('settings', function(Blueprint $table)
		{
            $table->foreign('client_id', 'settings_client_id')->references('id')->on('clients')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('branch_id', 'settings_branch_id')->references('id')->on('branches')->onUpdate('RESTRICT')->onDelete('RESTRICT');

		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('settings', function(Blueprint $table)
		{
            $table->dropForeign('settings_client_id');
            $table->dropForeign('settings_branch_id');
		});
    }
}
