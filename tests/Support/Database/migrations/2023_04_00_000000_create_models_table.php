<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
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
