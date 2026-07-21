<?php

use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Departments become the permission groups: what a member may do follows from
 * the department they work in, rather than from a generic membership role.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->json('abilities')->nullable()->after('status');
        });

        // Give the departments that already exist the preset matching their
        // name, so nobody has to reconfigure a working hotel by hand.
        DB::table('departments')->orderBy('id')->chunkById(100, function ($departments) {
            foreach ($departments as $department) {
                DB::table('departments')
                    ->where('id', $department->id)
                    ->update([
                        'abilities' => json_encode(
                            Department::presetAbilitiesFor((string) $department->name)
                        ),
                    ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('abilities');
        });
    }
};
