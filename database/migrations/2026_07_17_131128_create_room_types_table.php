<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The types every team starts with — the list that used to be hardcoded.
     *
     * @var list<array{slug: string, name: string}>
     */
    private array $defaults = [
        ['slug' => 'single', 'name' => 'Single'],
        ['slug' => 'double', 'name' => 'Double'],
        ['slug' => 'suite', 'name' => 'Suite'],
        ['slug' => 'deluxe', 'name' => 'Deluxe'],
        ['slug' => 'penthouse', 'name' => 'Penthouse'],
    ];

    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            // Rooms store the slug, so it stays stable while the name is editable.
            $table->string('slug');
            $table->string('name');
            $table->timestamps();

            $table->unique(['team_id', 'slug']);
        });

        // Backfill existing teams with the previously hardcoded types so nothing
        // that already references them breaks.
        $now = now();

        $rows = DB::table('teams')->pluck('id')
            ->flatMap(fn ($teamId) => array_map(fn (array $type) => [
                'team_id' => $teamId,
                'slug' => $type['slug'],
                'name' => $type['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $this->defaults))
            ->all();

        if ($rows !== []) {
            DB::table('room_types')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
