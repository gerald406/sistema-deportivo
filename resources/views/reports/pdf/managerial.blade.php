{{--
    TEMPLATE PDF: Reporte Gerencial (adaptado de pichanga/reports/pdf/managerial)

    A4 LANDSCAPE para acomodar las tablas anchas como la de posiciones.

    SECCIONES:
    1. Resultados de la última jornada completada + sus observaciones
    2. Tabla de posiciones (solo si la competencia tiene enfrentamientos: $showStandings)
    3. Programación de la próxima jornada + sus observaciones
    4. Tabla de goleadores (Top 20) (solo si $showScorers)
    5. Participantes con sanciones pendientes
    6. Participantes con 2+ tarjetas amarillas

    Lo que en la referencia era un if por deporte (sport === 'volley') aquí lo deciden los
    datos (ManagerialReportService): el formato efectivo de los partidos
    (discipline.format_type ?? sport.format_type) y si se juega por sets.

    Variables: $gamesEdition, $season, $organizer, $scoring, $generatedAt,
    $lastMatchday, $lastResults, $nextMatchday, $nextGames, $showStandings,
    $standings, $standingColumns, $showScorers, $topScorers, $showSuspensions, $suspensions, $showYellows,
    $yellowCardRisks.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Gerencial - {{ $gamesEdition->name }} - {{ $season->name }}</title>
    <style>
        @page {
            margin-top: 1.5cm;
            margin-bottom: 1.5cm;
            margin-left: 1cm;
            margin-right: 1cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            color: #1a1a1a;
            line-height: 1.3;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #4338ca;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header h1 { font-size: 16px; color: #312e81; text-transform: uppercase; letter-spacing: 1px; }
        .header h2 { font-size: 12px; color: #4338ca; margin-top: 2px; }
        .header .subtitle { font-size: 9px; color: #6b7280; margin-top: 2px; }

        .info-bar {
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 12px;
            font-size: 8px;
            color: #4b5563;
        }
        .info-bar table { width: 100%; }
        .info-bar td { padding: 1px 0; }
        .info-bar .label { font-weight: bold; color: #1f2937; }

        .section-title {
            background-color: #4338ca;
            color: white;
            padding: 6px 12px;
            border-radius: 4px 4px 0 0;
            font-size: 11px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 0;
        }
        .section-subtitle { font-size: 8px; font-weight: normal; color: #c7d2fe; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table thead th {
            background-color: #e0e7ff;
            color: #312e81;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 6px;
            text-align: left;
            border-bottom: 2px solid #4338ca;
        }
        .data-table tbody td {
            padding: 4px 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 8px;
            vertical-align: middle;
        }
        .data-table tbody tr:nth-child(even) { background-color: #f9fafb; }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }

        .standings-table thead th { text-align: center; padding: 5px 4px; }
        .standings-table tbody td { text-align: center; padding: 4px; }
        .standings-table .team-name { text-align: left; font-weight: bold; }
        .standings-table .pts-cell { background-color: #eef2ff; font-weight: bold; font-size: 10px; color: #4338ca; }

        .pos-indicator {
            display: inline-block;
            width: 16px;
            height: 16px;
            line-height: 16px;
            text-align: center;
            border-radius: 50%;
            font-size: 7px;
            font-weight: bold;
            color: white;
        }
        .pos-1 { background-color: #f59e0b; }
        .pos-2 { background-color: #9ca3af; }
        .pos-3 { background-color: #b45309; }
        .pos-default { background-color: #d1d5db; color: #4b5563; }

        .score-display { font-size: 12px; font-weight: bold; color: #312e81; text-align: center; }
        .team-local { text-align: right; font-weight: bold; }
        .team-visit { text-align: left; font-weight: bold; }
        .walkover-badge { background-color: #fef2f2; color: #991b1b; padding: 1px 5px; border-radius: 3px; font-size: 7px; font-weight: bold; }

        .goals-badge {
            display: inline-block;
            background-color: #d1fae5;
            color: #065f46;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
        }

        .yellow-badge {
            display: inline-block;
            background-color: #fbbf24;
            color: #78350f;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }
        .red-badge {
            display: inline-block;
            background-color: #ef4444;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
        }

        .empty-message { text-align: center; color: #9ca3af; padding: 15px; font-style: italic; font-size: 9px; }

        .signature-section { margin-top: 40px; page-break-inside: avoid; }
        .signature-box { width: 280px; margin: 0 auto; text-align: center; }
        .signature-line { border-top: 1px solid #1a1a1a; margin-top: 45px; padding-top: 4px; }
        .signature-name { font-size: 10px; font-weight: bold; }
        .signature-role { font-size: 8px; color: #6b7280; }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding: 4px 0;
        }
        .footer .page-number:after { content: counter(page); }

        .page-break { page-break-after: always; }
        .mt-10 { margin-top: 10px; }
        .mb-5 { margin-bottom: 5px; }

        /* Estilo para las observaciones */
        .observations-box {
            background-color: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 8px 12px;
            margin: 8px 0 12px 0;
            font-size: 8px;
            color: #78350f;
            line-height: 1.4;
        }
        .observations-title {
            font-weight: bold;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
    </style>
</head>
<body>

    <div class="footer">
        {{ mb_strtoupper(config('app.name')) }} &bull; Reporte Gerencial &bull; {{ $gamesEdition->name }} &bull; {{ $season->name }} &bull;
        Generado: {{ $generatedAt }} &bull; Página <span class="page-number"></span>
    </div>

    {{-- ENCABEZADO --}}
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <h2>Reporte Gerencial de la Competencia</h2>
        <div class="subtitle">Documento generado automáticamente</div>
    </div>

    <div class="info-bar">
        <table>
            <tr>
                <td class="label" style="width: 120px;">Olimpiada:</td>
                <td>{{ $gamesEdition->name }} &mdash; {{ $season->name }} ({{ $season->sport->name }})</td>
                <td class="label" style="text-align:right; width: 120px;">Fecha emisión:</td>
                <td style="text-align:right;">{{ $generatedAt }}</td>
            </tr>
            <tr>
                <td class="label">Organizador:</td>
                <td>{{ $organizer->name }}</td>
                <td class="label" style="text-align:right;">Puntuación:</td>
                <td style="text-align:right;">
                    @if($scoring)
                        G:{{ $scoring->pts_win }} / E:{{ $scoring->pts_draw }} / P:{{ $scoring->pts_loss }} / WO:{{ $scoring->pts_walkover }}
                        ({{ $scoring->phase_name }})
                    @else
                        Sin configurar
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ============================================ --}}
    {{-- SECCIÓN 1: RESULTADOS ÚLTIMA JORNADA + OBSERVACIONES --}}
    {{-- ============================================ --}}
    <div class="section-title">
        Resultados de la Última Jornada Completada
        @if($lastMatchday)
            <span class="section-subtitle">
                &mdash; {{ $lastMatchday->name }}
                @if($lastMatchday->start_date) ({{ $lastMatchday->start_date->format('d/m/Y') }}) @endif
            </span>
        @endif
    </div>

    @if($lastMatchday && $lastResults->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 30px;" class="text-center">#</th>
                    <th style="text-align: right;">Equipo Local / Prueba</th>
                    <th style="width: 80px; text-align: center;">Resultado</th>
                    <th>Equipo Visitante / Ganador</th>
                    <th style="width: 100px;">Estado</th>
                    <th style="width: 140px;">Sede</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lastResults as $game)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    @if($game['isHeadToHead'])
                        <td class="team-local">{{ $game['home'] }}</td>
                        <td class="score-display">
                            @if($game['status'] === \App\Enums\MatchStatus::Walkover)
                                <span class="walkover-badge">W.O.</span>
                            @else
                                {{ $game['homeScore'] ?? '-' }} - {{ $game['awayScore'] ?? '-' }}
                            @endif
                        </td>
                        <td class="team-visit">{{ $game['away'] }}</td>
                    @else
                        <td class="team-local">{{ $game['event'] }} <span style="font-weight: normal; color: #6b7280;">({{ $game['participantsCount'] }} participantes)</span></td>
                        <td class="score-display" style="font-size: 9px;">1.º</td>
                        <td class="team-visit">{{ $game['winner'] ?? 'Sin puestos' }}</td>
                    @endif
                    <td>@if($game['status'] === \App\Enums\MatchStatus::Walkover) <span class="walkover-badge">WALKOVER</span> @else Jugado @endif</td>
                    <td>{{ $game['venue']?->name ?? 'Sin definir' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-message" style="border: 1px solid #e5e7eb; border-top: none;">No hay resultados registrados. Ninguna jornada ha sido completada.</div>
    @endif

    {{-- Observaciones de la última jornada (justo después de sus resultados) --}}
    @if($lastMatchday && $lastMatchday->observations)
    <div class="observations-box">
        <div class="observations-title">Comunicado de la Última Jornada</div>
        <div>{{ $lastMatchday->observations }}</div>
        <div style="margin-top: 5px; font-size: 7px; color: #b45309; text-align: right;">Publicado por: {{ $organizer->name }}</div>
    </div>
    @endif


    {{-- ============================================ --}}
    {{-- SECCIÓN 2: TABLA DE POSICIONES (solo enfrentamientos) --}}
    {{-- ============================================ --}}
    @if($showStandings)
        <div class="section-title mt-10">Tabla de Posiciones Acumulada</div>

        @if(count($standings) > 0)
            {{-- Columnas desde los datos (SeasonOverviewService::standingColumns), las mismas que la web pública. --}}
            <table class="data-table standings-table">
                <thead>
                    <tr>
                        <th style="width: 35px;">Pos</th>
                        <th style="text-align: left;">Equipo</th>
                        @foreach($standingColumns as $col)
                            <th style="width: 35px;" title="{{ $col['title'] }}">{{ $col['label'] }}</th>
                        @endforeach
                        <th style="width: 45px;">PTS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($standings as $row)
                    <tr>
                        <td>
                            <span class="pos-indicator {{ $loop->iteration <= 3 ? 'pos-'.$loop->iteration : 'pos-default' }}">{{ $loop->iteration }}</span>
                        </td>
                        <td class="team-name">{{ $row->seasonTeam->team->name }}</td>
                        @foreach($standingColumns as $col)
                            <td>{{ data_get($row, $col['key'], 0) }}</td>
                        @endforeach
                        <td class="pts-cell">{{ $row->points }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-message" style="border: 1px solid #e5e7eb; border-top: none;">No hay datos de posiciones disponibles.</div>
        @endif
    @endif


    {{-- ============================================ --}}
    {{-- SECCIÓN 3: PROGRAMACIÓN PRÓXIMA JORNADA + OBSERVACIONES --}}
    {{-- ============================================ --}}
    <div class="section-title">
        Programación de la Próxima Jornada
        @if($nextMatchday)
            <span class="section-subtitle">
                &mdash; {{ $nextMatchday->name }}
                @if($nextMatchday->start_date) ({{ $nextMatchday->start_date->format('d/m/Y') }}
                @if($nextMatchday->end_date) al {{ $nextMatchday->end_date->format('d/m/Y') }} @endif ) @endif
            </span>
        @endif
    </div>

    @if($nextMatchday && $nextGames->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 30px;" class="text-center">#</th>
                    <th style="text-align: right;">Equipo Local / Prueba</th>
                    <th style="width: 40px; text-align: center;">VS</th>
                    <th>Equipo Visitante</th>
                    <th style="width: 130px;">Fecha y Hora</th>
                    <th style="width: 140px;">Sede</th>
                    <th style="width: 70px;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($nextGames as $game)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    @if($game['isHeadToHead'])
                        <td class="team-local">{{ $game['home'] }}</td>
                        <td class="text-center text-bold" style="color: #4338ca;">VS</td>
                        <td class="team-visit">{{ $game['away'] }}</td>
                    @else
                        <td class="team-local">{{ $game['event'] }}</td>
                        <td class="text-center" style="color: #4338ca;">&mdash;</td>
                        <td class="team-visit" style="font-weight: normal;">{{ $game['participantsCount'] }} participantes</td>
                    @endif
                    <td>{{ $game['scheduledAt'] ? $game['scheduledAt']->format('d/m/Y H:i') : 'Por definir' }}</td>
                    <td>{{ $game['venue']?->name ?? 'Sin definir' }}</td>
                    <td>{{ $game['status']->label() }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-message" style="border: 1px solid #e5e7eb; border-top: none;">No hay jornada próxima programada.</div>
    @endif

    {{-- Observaciones de la próxima jornada (justo después de su programación) --}}
    @if($nextMatchday && $nextMatchday->observations)
    <div class="observations-box">
        <div class="observations-title">Comunicado de la Próxima Jornada</div>
        <div>{{ $nextMatchday->observations }}</div>
        <div style="margin-top: 5px; font-size: 7px; color: #b45309; text-align: right;">Publicado por: {{ $organizer->name }}</div>
    </div>
    @endif


    {{-- ============================================ --}}
    {{-- SECCIÓN 4: GOLEADORES (solo si el formato y el deporte lo soportan) --}}
    {{-- ============================================ --}}
    @if($showScorers)
        <div class="section-title mt-10">Tabla de Goleadores (Top 20)</div>

        @if($topScorers->count() > 0)
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 30px;" class="text-center">Pos</th>
                        <th style="width: 50px; text-align: center;">Dorsal</th>
                        <th>Participante</th>
                        <th>Equipo</th>
                        <th style="width: 60px; text-align: center;">Goles</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topScorers as $scorer)
                    <tr>
                        <td class="text-center text-bold">{{ $loop->iteration }}</td>
                        <td class="text-center" style="color: #4338ca; font-weight: bold;">{{ $scorer['entry']->shirt_number ?? '-' }}</td>
                        <td class="text-bold">{{ $scorer['entry']->player->last_name }}, {{ $scorer['entry']->player->first_name }}</td>
                        <td>{{ $scorer['entry']->seasonTeam->team->name }}</td>
                        <td class="text-center"><span class="goals-badge">{{ $scorer['total'] }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-message" style="border: 1px solid #e5e7eb; border-top: none;">No hay goles registrados en esta competencia.</div>
        @endif
    @endif


    {{-- ============================================ --}}
    {{-- SECCIÓN 5: SANCIONES PENDIENTES (si el deporte genera sanciones o la competencia ya tiene) --}}
    {{-- ============================================ --}}
    @if($showSuspensions)
    <div class="section-title">Participantes Suspendidos (Sanciones Activas)</div>

    @if($suspensions->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 30px;" class="text-center">#</th>
                    <th>Participante</th>
                    <th>Equipo</th>
                    <th>Motivo</th>
                    <th style="width: 100px;">Partido Origen</th>
                    <th style="width: 80px; text-align: center;">Fechas Susp.</th>
                    <th style="width: 80px; text-align: center;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($suspensions as $sus)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td class="text-bold">{{ $sus->seasonTeamPlayer->player->last_name ?? '' }}, {{ $sus->seasonTeamPlayer->player->first_name ?? '' }}</td>
                    <td>{{ $sus->seasonTeamPlayer->seasonTeam->team->name ?? 'N/A' }}</td>
                    <td>@if(str_contains(mb_strtolower($sus->reason), 'roja')) <span class="red-badge">{{ $sus->reason }}</span> @else <span class="yellow-badge">{{ $sus->reason }}</span> @endif</td>
                    <td>{{ $sus->match->matchday->name ?? 'N/A' }}</td>
                    <td class="text-center text-bold">{{ $sus->matches_suspended }}</td>
                    <td class="text-center"><span class="red-badge">Pendiente</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-message" style="border: 1px solid #e5e7eb; border-top: none;">No hay participantes suspendidos actualmente.</div>
    @endif
    @endif


    {{-- ============================================ --}}
    {{-- SECCIÓN 6: PARTICIPANTES CON 2+ AMARILLAS (si el deporte registra amarillas) --}}
    {{-- ============================================ --}}
    @if($showYellows)
    <div class="section-title mt-10">
        Participantes con Acumulación de Tarjetas Amarillas (2+)
        <span class="section-subtitle">&mdash; En observación</span>
    </div>

    @if($yellowCardRisks->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 30px;" class="text-center">#</th>
                    <th style="width: 50px; text-align: center;">Dorsal</th>
                    <th>Participante</th>
                    <th>Equipo</th>
                    <th style="width: 100px; text-align: center;">Total Amarillas</th>
                    <th style="width: 100px; text-align: center;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($yellowCardRisks as $risk)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td class="text-center text-bold" style="color: #4338ca;">{{ $risk['entry']->shirt_number ?? '-' }}</td>
                    <td class="text-bold">{{ $risk['entry']->player->last_name }}, {{ $risk['entry']->player->first_name }}</td>
                    <td>{{ $risk['entry']->seasonTeam->team->name }}</td>
                    <td class="text-center"><span class="yellow-badge">{{ $risk['total'] }} amarillas</span></td>
                    {{-- Las amarillas no se acumulan: "Suspendido" solo si ya tiene una sanción pendiente. --}}
                    <td class="text-center">@if($risk['suspended']) <span class="red-badge">Suspendido</span> @else <span class="yellow-badge">En riesgo</span> @endif</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-message" style="border: 1px solid #e5e7eb; border-top: none;">No hay participantes con acumulación de tarjetas amarillas.</div>
    @endif
    @endif


    {{-- FIRMA --}}
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                <div class="signature-name">{{ $organizer->name }}</div>
                <div class="signature-role">Organizador de la Olimpiada</div>
                <div class="signature-role">{{ $gamesEdition->name }}</div>
            </div>
        </div>
    </div>

</body>
</html>
