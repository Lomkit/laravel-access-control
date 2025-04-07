<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Execute the migration to create the 'users' table.
     *
     * This migration defines the 'users' table with columns for a unique identifier, name, email (with an optional verification timestamp), and password.
     * It also adds boolean flag columns (should_shared, should_global, should_client, should_own) for additional user preferences, along with columns
     * for a remember token and automatic timestamps for record creation and updates.
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
