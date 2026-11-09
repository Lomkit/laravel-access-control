<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lomkit\Access\Tests\Support\Models\Model;
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
        Schema::create('models_shared_with_users', function (Blueprint $table) {
            $table->foreignIdFor(Model::class)->constrained();
            $table->foreignIdFor(User::class)->constrained();
            $table->primary(['model_id', 'user_id']);
            $table->timestamps();
        });
    }
};
