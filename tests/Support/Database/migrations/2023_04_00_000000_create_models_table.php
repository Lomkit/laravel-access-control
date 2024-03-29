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
            $table->bigInteger('number');
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_client')->default(false);
            $table->boolean('is_site')->default(false);
            $table->boolean('is_own')->default(false);
            $table->string('string')->nullable();
            $table->string('unique')->unique()->nullable();
            $table->timestamps();
        });
    }
};
