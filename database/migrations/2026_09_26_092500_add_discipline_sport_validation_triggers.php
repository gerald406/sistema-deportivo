<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_matches_before_insert_validate_discipline
            BEFORE INSERT ON matches
            FOR EACH ROW
            BEGIN
                DECLARE v_discipline_sport_id BIGINT UNSIGNED;
                DECLARE v_season_sport_id BIGINT UNSIGNED;

                IF NEW.discipline_id IS NOT NULL THEN
                    SELECT sport_id INTO v_discipline_sport_id FROM disciplines WHERE id = NEW.discipline_id;
                    SELECT sport_id INTO v_season_sport_id FROM seasons WHERE id = NEW.season_id;

                    IF v_discipline_sport_id <> v_season_sport_id THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'La disciplina no pertenece al deporte de la temporada del partido.';
                    END IF;
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_matches_before_update_validate_discipline
            BEFORE UPDATE ON matches
            FOR EACH ROW
            BEGIN
                DECLARE v_discipline_sport_id BIGINT UNSIGNED;
                DECLARE v_season_sport_id BIGINT UNSIGNED;

                IF NEW.discipline_id IS NOT NULL THEN
                    SELECT sport_id INTO v_discipline_sport_id FROM disciplines WHERE id = NEW.discipline_id;
                    SELECT sport_id INTO v_season_sport_id FROM seasons WHERE id = NEW.season_id;

                    IF v_discipline_sport_id <> v_season_sport_id THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'La disciplina no pertenece al deporte de la temporada del partido.';
                    END IF;
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_matches_before_insert_validate_discipline');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_matches_before_update_validate_discipline');
    }
};
