<?php

namespace App\Http\Controllers;

use App\Models\Fixture;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MockApiController extends Controller
{
    public function fixtures(Request $request)
    {
        $query = Fixture::query();

        if ($id = $request->query('id')) {
            $query->where('id_on_api', $id);
        }

        if ($date = $request->query('date')) {
            $query->whereDate('kickoff', $date);
        }

        if ($status = $request->query('status')) {
            $query->where('status_short', $status);
        }

        $fixtures = $query->orderBy('id_on_api')->get();

        return $this->envelope(
            'fixtures',
            $request->query(),
            $fixtures->map(fn($f) => $this->fixtureEnvelope($f))->all()
        );
    }

    public function odds(Request $request)
    {
        $query = Fixture::query();

        if ($fixture = $request->query('fixture')) {
            $query->where('id_on_api', $fixture);
        }

        $fixtures = $query->orderBy('id_on_api')->get();

        $response = $fixtures->map(function (Fixture $f) {
            return [
                'league' => [
                    'id' => $f->league_id,
                    'name' => $f->league_name,
                    'country' => $f->league_country,
                    'logo' => null,
                    'flag' => null,
                    'season' => 2026,
                ],
                'fixture' => [
                    'id' => $f->id_on_api,
                    'timezone' => 'UTC',
                    'date' => $f->kickoff->toIso8601String(),
                    'timestamp' => $f->kickoff->timestamp,
                ],
                'bookmakers' => [
                    [
                        'id' => 1,
                        'name' => 'MockBook',
                        'bets' => $this->betsFor($f),
                    ],
                ],
            ];
        })->all();

        return $this->envelope('odds', $request->query(), $response);
    }

    public function leagues(Request $request)
    {
        $leagues = Fixture::query()
            ->select('league_id', 'league_name', 'league_country')
            ->distinct()
            ->orderBy('league_id')
            ->get()
            ->map(fn($l) => [
                'league' => [
                    'id' => $l->league_id,
                    'name' => $l->league_name,
                    'type' => 'League',
                    'logo' => null,
                ],
                'country' => [
                    'name' => $l->league_country,
                    'code' => null,
                    'flag' => null,
                ],
                'seasons' => [
                    ['year' => 2026, 'start' => '2026-08-01', 'end' => '2027-05-31', 'current' => true],
                ],
            ])
            ->all();

        return $this->envelope('leagues', $request->query(), $leagues);
    }

    public function teams(Request $request)
    {
        $teams = [];

        foreach (Fixture::orderBy('id_on_api')->get() as $f) {
            $teams[$f->home_team_id] = [
                'team' => ['id' => $f->home_team_id, 'name' => $f->home_team_name, 'code' => null, 'country' => $f->league_country, 'founded' => null, 'national' => false, 'logo' => null],
                'venue' => ['id' => 1, 'name' => 'Mock Arena', 'address' => null, 'city' => 'Testville', 'capacity' => 50000, 'surface' => 'grass', 'image' => null],
            ];
            $teams[$f->away_team_id] = [
                'team' => ['id' => $f->away_team_id, 'name' => $f->away_team_name, 'code' => null, 'country' => $f->league_country, 'founded' => null, 'national' => false, 'logo' => null],
                'venue' => ['id' => 1, 'name' => 'Mock Arena', 'address' => null, 'city' => 'Testville', 'capacity' => 50000, 'surface' => 'grass', 'image' => null],
            ];
        }

        return $this->envelope('teams', $request->query(), array_values($teams));
    }

    protected function envelope(string $endpoint, array $params, array $response): array
    {
        return [
            'get' => $endpoint,
            'parameters' => $params,
            'errors' => [],
            'results' => count($response),
            'paging' => ['current' => 1, 'total' => 1],
            'response' => $response,
        ];
    }

    protected function fixtureEnvelope(Fixture $f): array
    {
        $winner = null;
        if (in_array($f->status_short, ['FT', 'AET', 'PEN'])) {
            if ($f->goals_home > $f->goals_away)
                $winner = true;
            elseif ($f->goals_home < $f->goals_away)
                $winner = false;
        }

        return [
            'fixture' => [
                'id' => $f->id_on_api,
                'referee' => null,
                'timezone' => 'UTC',
                'date' => $f->kickoff->toIso8601String(),
                'timestamp' => $f->kickoff->timestamp,
                'periods' => ['first' => null, 'second' => null],
                'venue' => ['id' => 1, 'name' => 'Mock Arena', 'city' => 'Testville'],
                'status' => [
                    'long' => $f->status_long,
                    'short' => $f->status_short,
                    'elapsed' => $f->status_elapsed,
                    'extra' => null,
                ],
            ],
            'league' => [
                'id' => $f->league_id,
                'name' => $f->league_name,
                'country' => $f->league_country,
                'logo' => null,
                'flag' => null,
                'season' => 2026,
                'round' => 'Regular Season - 1',
            ],
            'teams' => [
                'home' => ['id' => $f->home_team_id, 'name' => $f->home_team_name, 'logo' => null, 'winner' => $winner],
                'away' => ['id' => $f->away_team_id, 'name' => $f->away_team_name, 'logo' => null, 'winner' => $winner === null ? null : !$winner],
            ],
            'goals' => ['home' => $f->goals_home, 'away' => $f->goals_away],
            'score' => [
                'halftime' => ['home' => $f->halftime_home, 'away' => $f->halftime_away],
                'fulltime' => ['home' => $f->fulltime_home, 'away' => $f->fulltime_away],
                'extratime' => ['home' => null, 'away' => null],
                'penalty' => ['home' => null, 'away' => null],
            ],
        ];
    }

    protected function betsFor(Fixture $f): array
    {
        // Base offset applied to every odd (kept from the original template).
        $base = 0;

        // Full 32-market template. IDs match the app's `markets` catalogue
        // (see MarketSettlementService::SETTLEABLE_MARKET_IDS), which mirrors
        // API-Football's canonical bet IDs — a sparse set with gaps at 4, 11,
        // 18, 19, 23, 27, 28, 31, 33, 35, 36, 37, 39, 45, etc.
        $template = [
            [
                'id' => 1,
                'name' => 'Match Winner',
                'values' => [
                    ['value' => 'Home', 'odd' => 1.80],
                    ['value' => 'Draw', 'odd' => 3.60],
                    ['value' => 'Away', 'odd' => 4.20],
                ]
            ],
            [
                'id' => 2,
                'name' => 'Home/Away',
                'values' => [
                    ['value' => 'Home', 'odd' => 1.55],
                    ['value' => 'Away', 'odd' => 2.40],
                ]
            ],
            [
                'id' => 3,
                'name' => 'Second Half Winner',
                'values' => [
                    ['value' => 'Home', 'odd' => 2.10],
                    ['value' => 'Draw', 'odd' => 2.50],
                    ['value' => 'Away', 'odd' => 4.50],
                ]
            ],
            [
                'id' => 5,
                'name' => 'Goals Over/Under',
                'values' => [
                    ['value' => 'Over 1.5', 'odd' => 1.30],
                    ['value' => 'Under 1.5', 'odd' => 3.40],
                    ['value' => 'Over 2.5', 'odd' => 1.90],
                    ['value' => 'Under 2.5', 'odd' => 1.95],
                    ['value' => 'Over 3.5', 'odd' => 3.10],
                    ['value' => 'Under 3.5', 'odd' => 1.38],
                ]
            ],
            [
                'id' => 6,
                'name' => 'Goals Over/Under First Half',
                'values' => [
                    ['value' => 'Over 0.5', 'odd' => 1.44],
                    ['value' => 'Under 0.5', 'odd' => 2.70],
                    ['value' => 'Over 1.5', 'odd' => 2.80],
                    ['value' => 'Under 1.5', 'odd' => 1.44],
                    ['value' => 'Over 2.5', 'odd' => 6.00],
                    ['value' => 'Under 2.5', 'odd' => 1.12],
                ]
            ],
            [
                'id' => 7,
                'name' => 'HT/FT Double',
                'values' => [
                    ['value' => 'Home/Home', 'odd' => 2.60],
                    ['value' => 'Home/Draw', 'odd' => 15.00],
                    ['value' => 'Home/Away', 'odd' => 41.00],
                    ['value' => 'Draw/Home', 'odd' => 4.33],
                    ['value' => 'Draw/Draw', 'odd' => 6.50],
                    ['value' => 'Draw/Away', 'odd' => 13.00],
                    ['value' => 'Away/Home', 'odd' => 21.00],
                    ['value' => 'Away/Draw', 'odd' => 19.00],
                    ['value' => 'Away/Away', 'odd' => 12.00],
                ]
            ],
            [
                'id' => 8,
                'name' => 'Both Teams Score',
                'values' => [
                    ['value' => 'Yes', 'odd' => 1.85],
                    ['value' => 'No', 'odd' => 1.90],
                ]
            ],
            [
                'id' => 9,
                'name' => 'Handicap Result',
                'values' => [
                    ['value' => 'Home -1', 'odd' => 2.20],
                    ['value' => 'Draw -1', 'odd' => 3.75],
                    ['value' => 'Away -1', 'odd' => 2.62],
                    ['value' => 'Home -2', 'odd' => 4.00],
                    ['value' => 'Draw -2', 'odd' => 4.33],
                    ['value' => 'Away -2', 'odd' => 1.62],
                    ['value' => 'Home +1', 'odd' => 1.11],
                    ['value' => 'Draw +1', 'odd' => 9.00],
                    ['value' => 'Away +1', 'odd' => 19.00],
                ]
            ],
            [
                'id' => 10,
                'name' => 'Exact Score',
                'values' => [
                    ['value' => '0:0', 'odd' => 12.00],
                    ['value' => '1:0', 'odd' => 7.50],
                    ['value' => '0:1', 'odd' => 19.00],
                    ['value' => '1:1', 'odd' => 8.00],
                    ['value' => '2:0', 'odd' => 7.00],
                    ['value' => '0:2', 'odd' => 34.00],
                    ['value' => '2:1', 'odd' => 8.50],
                    ['value' => '1:2', 'odd' => 21.00],
                    ['value' => '2:2', 'odd' => 17.00],
                    ['value' => '3:0', 'odd' => 10.00],
                    ['value' => '0:3', 'odd' => 81.00],
                    ['value' => '3:1', 'odd' => 12.00],
                    ['value' => '1:3', 'odd' => 51.00],
                    ['value' => '3:2', 'odd' => 26.00],
                    ['value' => '2:3', 'odd' => 51.00],
                ]
            ],
            [
                'id' => 12,
                'name' => 'Double Chance',
                'values' => [
                    ['value' => 'Home/Draw', 'odd' => 1.10],
                    ['value' => 'Home/Away', 'odd' => 1.18],
                    ['value' => 'Draw/Away', 'odd' => 2.75],
                ]
            ],
            [
                'id' => 13,
                'name' => 'First Half Winner',
                'values' => [
                    ['value' => 'Home', 'odd' => 1.95],
                    ['value' => 'Draw', 'odd' => 2.40],
                    ['value' => 'Away', 'odd' => 6.50],
                ]
            ],
            [
                'id' => 14,
                'name' => 'Team To Score First',
                'values' => [
                    ['value' => 'Home', 'odd' => 1.40],
                    ['value' => 'Away', 'odd' => 3.00],
                    ['value' => 'No goal', 'odd' => 13.00],
                ]
            ],
            [
                'id' => 15,
                'name' => 'Team To Score Last',
                'values' => [
                    ['value' => 'Home', 'odd' => 1.44],
                    ['value' => 'Away', 'odd' => 3.00],
                    ['value' => 'No goal', 'odd' => 12.00],
                ]
            ],
            [
                'id' => 16,
                'name' => 'Total - Home',
                'values' => [
                    ['value' => 'Over 0.5', 'odd' => 1.10],
                    ['value' => 'Under 0.5', 'odd' => 7.00],
                    ['value' => 'Over 1.5', 'odd' => 1.53],
                    ['value' => 'Under 1.5', 'odd' => 2.38],
                    ['value' => 'Over 2.5', 'odd' => 2.62],
                    ['value' => 'Under 2.5', 'odd' => 1.44],
                ]
            ],
            [
                'id' => 17,
                'name' => 'Total - Away',
                'values' => [
                    ['value' => 'Over 0.5', 'odd' => 1.67],
                    ['value' => 'Under 0.5', 'odd' => 2.10],
                    ['value' => 'Over 1.5', 'odd' => 4.33],
                    ['value' => 'Under 1.5', 'odd' => 1.20],
                    ['value' => 'Over 2.5', 'odd' => 15.00],
                    ['value' => 'Under 2.5', 'odd' => 1.03],
                ]
            ],
            [
                'id' => 20,
                'name' => 'Double Chance - First Half',
                'values' => [
                    ['value' => 'Home/Draw', 'odd' => 1.10],
                    ['value' => 'Home/Away', 'odd' => 1.53],
                    ['value' => 'Draw/Away', 'odd' => 1.80],
                ]
            ],
            [
                'id' => 21,
                'name' => 'Odd/Even',
                'values' => [
                    ['value' => 'Odd', 'odd' => 1.85],
                    ['value' => 'Even', 'odd' => 1.85],
                ]
            ],
            [
                'id' => 22,
                'name' => 'Odd/Even - First Half',
                'values' => [
                    ['value' => 'Odd', 'odd' => 2.05],
                    ['value' => 'Even', 'odd' => 1.70],
                ]
            ],
            [
                'id' => 24,
                'name' => 'Results/Both Teams Score',
                'values' => [
                    ['value' => 'Home/Yes', 'odd' => 3.10],
                    ['value' => 'Draw/Yes', 'odd' => 5.80],
                    ['value' => 'Away/Yes', 'odd' => 12.00],
                    ['value' => 'Home/No', 'odd' => 2.50],
                    ['value' => 'Draw/No', 'odd' => 12.00],
                    ['value' => 'Away/No', 'odd' => 13.00],
                ]
            ],
            [
                'id' => 25,
                'name' => 'Result/Total Goals',
                'values' => [
                    ['value' => 'Home/Over 1.5', 'odd' => 1.73],
                    ['value' => 'Home/Under 1.5', 'odd' => 7.00],
                    ['value' => 'Draw/Over 1.5', 'odd' => 5.50],
                    ['value' => 'Draw/Under 1.5', 'odd' => 13.00],
                    ['value' => 'Away/Over 1.5', 'odd' => 8.50],
                    ['value' => 'Away/Under 1.5', 'odd' => 15.00],
                    ['value' => 'Home/Over 2.5', 'odd' => 2.20],
                    ['value' => 'Home/Under 2.5', 'odd' => 3.75],
                    ['value' => 'Draw/Over 2.5', 'odd' => 13.00],
                    ['value' => 'Draw/Under 2.5', 'odd' => 5.50],
                    ['value' => 'Away/Over 2.5', 'odd' => 10.00],
                    ['value' => 'Away/Under 2.5', 'odd' => 12.00],
                ]
            ],
            [
                'id' => 26,
                'name' => 'Goals Over/Under - Second Half',
                'values' => [
                    ['value' => 'Over 0.5', 'odd' => 1.18],
                    ['value' => 'Under 0.5', 'odd' => 4.50],
                    ['value' => 'Over 1.5', 'odd' => 1.83],
                    ['value' => 'Under 1.5', 'odd' => 1.83],
                    ['value' => 'Over 2.5', 'odd' => 3.75],
                    ['value' => 'Under 2.5', 'odd' => 1.25],
                ]
            ],
            [
                'id' => 29,
                'name' => 'Win to Nil - Home',
                'values' => [
                    ['value' => 'Yes', 'odd' => 2.38],
                    ['value' => 'No', 'odd' => 1.50],
                ]
            ],
            [
                'id' => 30,
                'name' => 'Win to Nil - Away',
                'values' => [
                    ['value' => 'Yes', 'odd' => 9.70],
                    ['value' => 'No', 'odd' => 1.02],
                ]
            ],
            [
                'id' => 32,
                'name' => 'Win Both Halves',
                'values' => [
                    ['value' => 'Home', 'odd' => 3.50],
                    ['value' => 'Away', 'odd' => 26.00],
                ]
            ],
            [
                'id' => 34,
                'name' => 'Both Teams Score - First Half',
                'values' => [
                    ['value' => 'Yes', 'odd' => 5.00],
                    ['value' => 'No', 'odd' => 1.17],
                ]
            ],
            [
                'id' => 38,
                'name' => 'Exact Goals Number',
                'values' => [
                    ['value' => '0', 'odd' => 12.00],
                    ['value' => '1', 'odd' => 5.50],
                    ['value' => '2', 'odd' => 3.75],
                    ['value' => '3', 'odd' => 4.00],
                    ['value' => '4', 'odd' => 5.50],
                    ['value' => '5', 'odd' => 9.00],
                    ['value' => 'more 6', 'odd' => 17.00],
                ]
            ],
            [
                'id' => 40,
                'name' => 'Home Team Exact Goals Number',
                'values' => [
                    ['value' => '0', 'odd' => 6.50],
                    ['value' => '1', 'odd' => 3.25],
                    ['value' => '2', 'odd' => 3.25],
                    ['value' => '3', 'odd' => 4.60],
                    ['value' => 'more 4', 'odd' => 5.00],
                ]
            ],
            [
                'id' => 41,
                'name' => 'Away Team Exact Goals Number',
                'values' => [
                    ['value' => '0', 'odd' => 2.10],
                    ['value' => '1', 'odd' => 2.50],
                    ['value' => '2', 'odd' => 5.50],
                    ['value' => '3', 'odd' => 15.00],
                    ['value' => 'more 4', 'odd' => 51.00],
                ]
            ],
            [
                'id' => 42,
                'name' => 'Second Half Exact Goals Number',
                'values' => [
                    ['value' => '0', 'odd' => 4.00],
                    ['value' => '1', 'odd' => 2.80],
                    ['value' => '2', 'odd' => 3.50],
                    ['value' => '3', 'odd' => 6.00],
                    ['value' => 'more 4', 'odd' => 9.50],
                ]
            ],
            [
                'id' => 43,
                'name' => 'Home Team Score a Goal',
                'values' => [
                    ['value' => 'Yes', 'odd' => 1.10],
                    ['value' => 'No', 'odd' => 6.00],
                ]
            ],
            [
                'id' => 44,
                'name' => 'Away Team Score a Goal',
                'values' => [
                    ['value' => 'Yes', 'odd' => 1.62],
                    ['value' => 'No', 'odd' => 2.20],
                ]
            ],
            [
                'id' => 46,
                'name' => 'Exact Goals Number - First Half',
                'values' => [
                    ['value' => '0', 'odd' => 3.50],
                    ['value' => '1', 'odd' => 2.62],
                    ['value' => '2', 'odd' => 3.75],
                    ['value' => '3', 'odd' => 8.00],
                    ['value' => '4', 'odd' => 21.00],
                    ['value' => 'more 5', 'odd' => 41.00],
                ]
            ],
        ];

        $bets = [];

        foreach ($template as $market) {
            $values = [];
            foreach ($market['values'] as $v) {
                $values[] = [
                    'value' => $v['value'],
                    'odd' => (string) round($base + $v['odd'], 2),
                ];
            }

            $bets[] = [
                'id' => $market['id'],
                'name' => $market['name'],
                'values' => $values,
            ];
        }

        return $bets;
    }
}