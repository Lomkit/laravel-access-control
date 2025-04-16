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
            $table->foreignIdFor(\Lomkit\Access\Tests\Support\Models\User::class, 'author_id')->nullable()->constrained();
            $table->foreignIdFor(\Lomkit\Access\Tests\Support\Models\Client::class, 'client_id')->nullable()->constrained();
            $table->timestamps();
        });
    }
};
