<?php

namespace App\Http\Controllers;

use App\Models\Fixture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class MockAdminController extends Controller
{
    protected function authorizeToken(string $token): void
    {
        abort_unless($token === config('app.mock_api_token'), 404);
    }

    public function show(string $token)
    {
        $this->authorizeToken($token);
        return response()->json($this->snapshot());
    }

    public function reset(string $token)
    {
        $this->authorizeToken($token);
        Artisan::call('db:seed', ['--class' => 'FixtureSeeder', '--force' => true]);
        return response()->json($this->snapshot());
    }

    public function advance(string $token)
    {
        $this->authorizeToken($token);

        $transitions = ['NS' => '1H', '1H' => 'HT', 'HT' => '2H', '2H' => 'FT'];

        // dd("dsd");
        $fixtures = Fixture::orderBy('id_on_api')->take(20)->get();

        // return $fixtures->count();

        foreach ($fixtures as $i => $f) {
            $next = $transitions[$f->status_short] ?? null;
            if ($next === null) {
                continue;
            }

            $f->status_short = $next;
            $f->status_long = match ($next) {
                '1H' => 'First Half',
                'HT' => 'Halftime',
                '2H' => 'Second Half',
                'FT' => 'Match Finished',
            };
            $f->status_elapsed = match ($next) {
                '1H' => 1,
                'HT' => 45,
                '2H' => 46,
                'FT' => 90,
            };

            // Set halftime score when we hit HT or beyond for the first time.
            if (in_array($next, ['HT', '2H', 'FT']) && $f->halftime_home === null) {
                $f->halftime_home = $i % 3;
                $f->halftime_away = ($i + 1) % 2;
            }

            if ($next === 'FT') {
                $f->goals_home = $i % 3;
                $f->goals_away = ($i + 1) % 2;
                $f->fulltime_home = $f->goals_home;
                $f->fulltime_away = $f->goals_away;
                $f->status_elapsed = 90;
            }

            $f->save();
        }

        return response()->json($this->snapshot());
    }

    public function score(Request $request, string $token, int $id)
    {
        $this->authorizeToken($token);

        $data = $request->validate([
            'home' => 'required|integer',
            'away' => 'required|integer',
        ]);

        $f = Fixture::where('id_on_api', $id)->firstOrFail();
        $f->status_short = 'FT';
        $f->status_long = 'Match Finished';
        $f->status_elapsed = 90;
        $f->goals_home = $data['home'];
        $f->goals_away = $data['away'];
        $f->fulltime_home = $data['home'];
        $f->fulltime_away = $data['away'];
        if ($f->halftime_home === null) {
            $f->halftime_home = $data['home'];
            $f->halftime_away = $data['away'];
        }
        $f->save();

        return response()->json($this->snapshot());
    }

    public function status(Request $request, string $token, int $id)
    {
        $this->authorizeToken($token);

        $data = $request->validate(['status' => 'required|string']);

        $f = Fixture::where('id_on_api', $id)->firstOrFail();
        $f->status_short = $data['status'];
        $f->status_long = match ($data['status']) {
            'NS' => 'Not Started',
            '1H' => 'First Half',
            'HT' => 'Halftime',
            '2H' => 'Second Half',
            'FT' => 'Match Finished',
            default => $data['status'],
        };
        $f->save();

        return response()->json($this->snapshot());
    }

    protected function snapshot(): array
    {
        return Fixture::orderBy('id_on_api')->get()->map(function (Fixture $f) {
            return [
                'id' => $f->id_on_api,
                'match' => "{$f->home_team_name} vs {$f->away_team_name}",
                'league' => $f->league_name,
                'status' => $f->status_short,
                'elapsed' => $f->status_elapsed,
                'score' => [
                    'home' => $f->goals_home,
                    'away' => $f->goals_away,
                ],
            ];
        })->all();
    }
}