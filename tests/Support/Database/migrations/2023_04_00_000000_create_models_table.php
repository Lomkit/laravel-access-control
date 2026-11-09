<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lomkit\Access\Tests\Support\Models\Client;
use Lomkit\Access\Tests\Support\Models\User;

return new class extends Migration
{
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
            $table->foreignIdFor(User::class, 'author_id')->nullable()->constrained();
            $table->foreignIdFor(Client::class, 'client_id')->nullable()->constrained();
            $table->timestamps();
        });
    }
};
