<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hotel = Team::query()->firstOrCreate(
            ['slug' => 'anns-haven'],
            ['name' => "Ann's Haven", 'is_personal' => false],
        );

        $owner = $this->upsertUser('Mr. Ndiana', 'admin@annshaven.com');
        $owner2 = $this->upsertUser('Mr. Ndiana', 'admin2@annshaven.com');
        $ayakang = $this->upsertUser('Ayakang', 'ayakang@annshaven.com');
        $edidiong = $this->upsertUser('Edidiong', 'edidiong@annshaven.com');
        $otobong = $this->upsertUser('Otobong', 'otobong@annshaven.com');
        $imaobong = $this->upsertUser('Ima-Obong', 'imaobong@annshaven.com');

        $this->assignToTeam($owner, $hotel, TeamRole::Owner);
        $this->assignToTeam($owner2, $hotel, TeamRole::Owner);
        $this->assignToTeam($ayakang, $hotel, TeamRole::Member);
        $this->assignToTeam($edidiong, $hotel, TeamRole::Member);
        $this->assignToTeam($otobong, $hotel, TeamRole::Member);
        $this->assignToTeam($imaobong, $hotel, TeamRole::Member);

        $owner->switchTeam($hotel);
        $owner2->switchTeam($hotel);
        $ayakang->switchTeam($hotel);
        $edidiong->switchTeam($hotel);
        $otobong->switchTeam($hotel);
        $imaobong->switchTeam($hotel);
    }

    private function upsertUser(string $name, string $email): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->fill([
            'name' => $name,
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $user->save();

        return $user;
    }

    private function assignToTeam(User $user, Team $team, TeamRole $role): void
    {
        $user->teamMemberships()->updateOrCreate(
            ['team_id' => $team->id],
            ['role' => $role->value],
        );
    }
}
