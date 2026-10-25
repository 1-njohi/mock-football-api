<?php

namespace Database\Seeders;

use App\Models\Fixture;
use Illuminate\Database\Seeder;

class FixtureSeeder extends Seeder
{
    public function run(): void
    {
        Fixture::query()->delete();

        $leagues = [
            ['id' => 39, 'name' => 'Premier League', 'country' => 'England'],
            ['id' => 140, 'name' => 'La Liga', 'country' => 'Spain'],
        ];

        // [api_id, league_index, home_id, home_name, away_id, away_name]
        $fixtures = [
            // ---- Premier League (10) ----
            [3001, 0, 33, 'Manchester United', 34, 'Newcastle'],
            [3002, 0, 40, 'Liverpool', 50, 'Manchester City'],
            [3003, 0, 42, 'Arsenal', 47, 'Tottenham'],
            [3004, 0, 49, 'Chelsea', 51, 'Brighton'],
            [3005, 0, 66, 'Aston Villa', 55, 'Brentford'],
            [3006, 0, 36, 'Fulham', 45, 'Everton'],
            [3007, 0, 48, 'West Ham', 52, 'Crystal Palace'],
            [3008, 0, 46, 'Leicester', 39, 'Wolves'],
            [3009, 0, 41, 'Southampton', 35, 'Bournemouth'],
            [3010, 0, 57, 'Ipswich', 65, 'Nottingham Forest'],

            // ---- La Liga (10) ----
            [4001, 1, 529, 'Barcelona', 541, 'Real Madrid'],
            [4002, 1, 530, 'Atletico Madrid', 533, 'Sevilla'],
            [4003, 1, 532, 'Valencia', 536, 'Villarreal'],
            [4004, 1, 531, 'Athletic Club', 543, 'Real Betis'],
            [4005, 1, 548, 'Real Sociedad', 538, 'Celta Vigo'],
            [4006, 1, 540, 'Espanyol', 546, 'Getafe'],
            [4007, 1, 547, 'Girona', 539, 'Leganes'],
            [4008, 1, 727, 'Osasuna', 728, 'Rayo Vallecano'],
            [4009, 1, 715, 'Mallorca', 798, 'Alaves'],
            [4010, 1, 720, 'Valladolid', 542, 'Almeria'],
        ];

        $base = now()->addDay()->setTime(12, 0, 0);

        foreach ($fixtures as $index => [$apiId, $leagueIdx, $homeId, $homeName, $awayId, $awayName]) {
            $league = $leagues[$leagueIdx];

            Fixture::create([
                'id_on_api' => $apiId,
                'league_id' => $league['id'],
                'league_name' => $league['name'],
                'league_country' => $league['country'],
                'home_team_id' => $homeId,
                'home_team_name' => $homeName,
                'away_team_id' => $awayId,
                'away_team_name' => $awayName,
                // $index % 10 keeps both leagues on the same 12:00–21:00 window
                // tomorrow, so nothing spills into the following day.
                'kickoff' => $base->copy()->addMinutes(($index % 10) * 60),
                'status_short' => 'NS',
                'status_long' => 'Not Started',
                'status_elapsed' => null,
                'goals_home' => null,
                'goals_away' => null,
                'halftime_home' => null,
                'halftime_away' => null,
                'fulltime_home' => null,
                'fulltime_away' => null,
                'odds' => $this->oddsFor($apiId),
            ]);
        }
    }

    protected function oddsFor(int $seed): array
    {
        $base = 1.5 + ($seed % 10) / 10;

        return [
            ['market' => 'Match Winner', 'value' => 'Home', 'odd' => round($base + 0.6, 2)],
            ['market' => 'Match Winner', 'value' => 'Draw', 'odd' => round($base + 1.4, 2)],
            ['market' => 'Match Winner', 'value' => 'Away', 'odd' => round($base + 1.0, 2)],
            ['market' => 'Both Teams Score', 'value' => 'Yes', 'odd' => round($base + 0.2, 2)],
            ['market' => 'Both Teams Score', 'value' => 'No', 'odd' => round($base + 0.7, 2)],
            ['market' => 'Goals Over/Under', 'value' => 'Over 2.5', 'odd' => round($base + 0.3, 2)],
            ['market' => 'Goals Over/Under', 'value' => 'Under 2.5', 'odd' => round($base + 0.9, 2)],
            ['market' => 'Double Chance', 'value' => 'Home/Draw', 'odd' => round($base + 0.1, 2)],
            ['market' => 'Double Chance', 'value' => 'Home/Away', 'odd' => round($base + 0.5, 2)],
            ['market' => 'Double Chance', 'value' => 'Draw/Away', 'odd' => round($base + 0.4, 2)],
        ];
    }
}