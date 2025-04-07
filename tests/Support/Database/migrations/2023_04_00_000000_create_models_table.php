<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Creates the `models` table in the database.
     *
     * This migration sets up the `models` table with an auto-incrementing primary key, a required name,
     * nullable string fields (`string` and `unique` with a unique constraint), a big integer (`number`),
     * and an optional `allowed_methods` field. It also defines boolean columns (`is_shared`, `is_global`,
     * `is_client`, and `is_own`) without default values and includes automatically managed timestamp columns.
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
