<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->integer('id_on_api')->unique();
            $table->integer('league_id');
            $table->string('league_name');
            $table->string('league_country');
            $table->integer('home_team_id');
            $table->string('home_team_name');
            $table->integer('away_team_id');
            $table->string('away_team_name');
            $table->dateTime('kickoff');
            $table->string('status_short');
            $table->string('status_long');
            $table->integer('status_elapsed')->nullable();
            $table->integer('goals_home')->nullable();
            $table->integer('goals_away')->nullable();
            $table->integer('halftime_home')->nullable();
            $table->integer('halftime_away')->nullable();
            $table->integer('fulltime_home')->nullable();
            $table->integer('fulltime_away')->nullable();
            $table->json('odds');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};