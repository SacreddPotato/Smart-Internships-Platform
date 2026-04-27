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
        Schema::create('internships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->string('location');
            $table->string('type')->default('remote');
            $table->string('status')->default('open');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('company_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internships');
    }
};
