<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Creates the "users" table with the defined schema.
     *
     * This migration sets up the "users" table with columns for an auto-incrementing primary key,
     * user details (name, email, password), a nullable timestamp for email verification, and 
     * boolean flags for shared, global, client, and ownership permissions. It also includes a 
     * remember token and timestamps for tracking record creation and updates.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('should_shared');
            $table->boolean('should_global');
            $table->boolean('should_client');
            $table->boolean('should_own');
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
