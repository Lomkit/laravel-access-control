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
        Schema::create('models_shared_with_users', function (Blueprint $table) {
            $table->foreignIdFor(\Lomkit\Access\Tests\Support\Models\Model::class)->constrained();
            $table->foreignIdFor(\Lomkit\Access\Tests\Support\Models\User::class)->constrained();
            $table->primary(['model_id', 'user_id']);
            $table->timestamps();
        });
    }
};
