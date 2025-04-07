<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migration to create the "models" table.
     *
     * Creates the "models" table with an auto-incrementing primary key and the following columns:
     * - "name": a required string.
     * - "string": a nullable string.
     * - "unique": a nullable string that must be unique.
     * - "number": a big integer.
     * - "allowed_methods": a nullable string.
     * - "is_shared", "is_global", "is_client", and "is_own": booleans without default values.
     * - "created_at" and "updated_at": timestamps.
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
