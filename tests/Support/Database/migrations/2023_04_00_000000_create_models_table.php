<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run migrations to create the 'models' table.
     *
     * This method creates the 'models' table with an auto-incrementing primary key, various string
     * and numeric columns, and boolean flags for shared, global, client, and ownership status. It also
     * includes timestamp columns for record tracking.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('string')->nullable();
            $table->string('unique')->unique()->nullable();
            $table->bigInteger('number');
            $table->string('allowed_methods')->nullable();
            $table->boolean('is_shared');
            $table->boolean('is_global');
            $table->boolean('is_client');
            $table->boolean('is_own');
            $table->timestamps();
        });
    }
};
