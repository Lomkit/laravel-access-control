<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Create the 'models' table with the updated schema.
     *
     * This migration sets up the 'models' table with the following columns:
     * - id: Auto-incrementing primary key.
     * - name: Required model name.
     * - string: Optional text field.
     * - unique: Optional field with a unique constraint.
     * - number: Big integer field.
     * - allowed_methods: Optional field for allowed methods.
     * - is_shared: Boolean indicating whether the model is shared.
     * - is_global: Boolean indicating global status.
     * - is_client: Boolean indicating client-specific status.
     * - is_own: Boolean indicating ownership.
     * - timestamps: Automatic tracking of creation and update times.
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
