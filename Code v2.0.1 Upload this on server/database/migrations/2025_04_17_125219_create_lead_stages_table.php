<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lead_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('order')->default(0);
            $table->string('color')->default('primary');
            $table->timestamps();
        });

        DB::table('lead_stages')->insert([
            [
                'name' => 'New',
                'slug' => 'new',
                'order' => 1,
                'color' => 'info',
                'workspace_id' => null,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Contacted',
                'slug' => 'contacted',
                'order' => 2,
                'color' => 'warning',
                'workspace_id' => null,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Qualified',
                'slug' => 'qualified',
                'order' => 3,
                'color' => 'primary',
                'workspace_id' => null,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Won',
                'slug' => 'won',
                'order' => 4,
                'color' => 'success',
                'workspace_id' => null,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Lost',
                'slug' => 'lost',
                'order' => 5,
                'color' => 'danger',
                'workspace_id' => null,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_stages');
    }
};
