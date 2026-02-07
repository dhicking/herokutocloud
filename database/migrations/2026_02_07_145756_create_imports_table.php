<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('heroku_app_id');
            $table->string('heroku_app_name');
            $table->string('github_repository');
            $table->string('cloud_application_id')->nullable();
            $table->string('cloud_environment_id')->nullable();
            $table->string('cloud_database_cluster_id')->nullable();
            $table->string('cloud_database_id')->nullable();
            $table->string('status')->default('pending');
            $table->json('phase1_log')->nullable();
            $table->json('phase2_log')->nullable();
            $table->json('heroku_app_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
