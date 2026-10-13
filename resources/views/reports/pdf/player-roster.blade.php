<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Padrón de Jugadores - {{ $gamesEdition->name }} - {{ $season->name }}</title>
    <style>
        /* 1. Márgenes de la hoja: Dejamos espacio arriba y abajo para el header y footer fijos */
        @page {
            margin: 150px 30px 110px 30px; 
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            line-height: 1.4;
            padding: 0;
            margin: 0;
        }

        /* ============================================
           ELEMENTOS FIJOS (REPETIDOS EN CADA PÁGINA)
           ============================================ */
        header {
            position: fixed;
            top: -130px; /* Sube hacia el margen del @page */
            left: 0;
            right: 0;
            height: 110px;
        }

        footer {
            position: fixed;
            bottom: -90px; /* Baja hacia el margen del @page */
            left: 0;
            right: 0;
            height: 70px;
        }

        .footer-line {
            border-top: 1px solid #e5e7eb;
            padding-top: 5px;
        }

        .footer-text {
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
        }

        /* La referencia usaba ".footer" (clase inexistente) y el numero no salia. */
        footer .page-number:after { content: counter(page); }

        /* ============================================
           ESTILOS DEL ENCABEZADO
           ============================================ */
        .header-brand-tbl { width: 100%; margin-bottom: 6px; }
        .header-brand-tbl td { vertical-align: middle; }

        .header-logo-cell { width: 60px; text-align: center; }
        .header-logo-box {
            width: 50px;
            height: 50px;
            margin: 0 auto;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            overflow: hidden;
            text-align: center;
        }
        .header-logo-box img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .header-logo-placeholder {
            padding-top: 18px;
            font-size: 6px;
            color: #c7cdd6;
            text-transform: uppercase;
        }

        .header-title { text-align: center; }

        .header-title h1 {
            font-size: 18px;
            color: #312e81;
            text-transform: uppercase;
            margin: 0 0 2px 0;
        }

        .header-title h2 {
            font-size: 14px;
            color: #4338ca;
            margin: 0 0 2px 0;
        }

        .header-rule {
            border-bottom: 3px solid #4338ca;
            margin-bottom: 8px;
        }

        .info-bar {
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 5px 10px;
            font-size: 9px;
            color: #4b5563;
          margin-bottom: 8px;
        }

        .info-bar table { width: 100%; border-collapse: collapse; }
        .info-bar td { padding: 2px 0; }
        .info-bar .label { font-weight: bold; color: #1f2937; width: 100px; }

        /* ============================================
           ESTILOS DEL CONTENIDO PRINCIPAL
           ============================================ */
        main {
            width: 100%;
        }

        .team-section {
            margin-top: 25px;
            page-break-inside: avoid;
        }

        .team-header {
            background-color: #4338ca;
            color: white;
            padding: 6px 12px;
            border-radius: 4px 4px 0 0;
        }

        .team-header-tbl { width: 100%; border-collapse: collapse; }
        .team-header-tbl td { vertical-align: middle; padding: 0; }

        .team-logo-cell { width: 30px; }
        .team-logo-box {
            width: 24px;
            height: 24px;
            border-radius: 3px;
            overflow: hidden;
            background: #ffffff;
            text-align: center;
        }
        .team-logo-box img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .team-logo-placeholder {
            width: 24px;
            height: 24px;
            border-radius: 3px;
            background: rgba(255,255,255,0.15);
        }

        .team-name-cell { text-align: center; padding-left: 8px; }
        .team-name-cell .team-name-text {
            font-size: 16px;
            font-weight: bold;
        }

        .team-header .team-meta {
            display: block;
            font-size: 9px;
            font-weight: normal;
            color: #c7d2fe;
            margin-top: 1px;
        }

        .players-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .players-table thead th {
            background-color: #e0e7ff;
            color: #312e81;
            font-size: 12px;
            text-transform: uppercase;
            padding: 6px;
            text-align: left;
            border-bottom: 2px solid #4338ca;
        }

        .players-table tbody td {
            padding: 5px 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
            vertical-align: middle;
        }

        .players-table tbody tr:nth-child(even) { background-color: #f9fafb; }
        .number-cell { text-align: center; font-weight: bold; color: #4338ca; }

        .team-footer {
            text-align: right;
            font-size: 9px;
            color: #6b7280;
            padding: 4px 8px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-top: none;
        }

        .team-footer .active-count { color: #059669; font-weight: bold; }
        .team-footer .inactive-count { color: #dc2626; }

        /* ============================================
           SECCIÓN DE FIRMAS AL FINAL DE CADA EQUIPO
           ============================================ */
        .signature-section {
            margin-top: 30px;
            width: 100%;
            page-break-inside: avoid;
        }

        .signature-box { width: 220px; margin: 0 auto; text-align: center; }
        .signature-line { border-top: 1px solid #1a1a1a; padding-top: 5px; margin-top: 40px; }
        .signature-name { font-size: 11px; font-weight: bold; color: #1f2937; }
        .signature-role { font-size: 9px; color: #6b7280; }

        .page-break { page-break-after: always; }
    </style>
</head>
<body>

    <!-- 1. ENCABEZADO FIJO EN TODAS LAS PÁGINAS -->
    <header>
        <table class="header-brand-tbl">
            <tr>
                <td class="header-logo-cell">
                    <div class="header-logo-box">
                        @if(!empty($gamesEditionLogoBase64))
                            <img src="{{ $gamesEditionLogoBase64 }}" alt="Logo de la olimpiada">
                        @else
                            <div class="header-logo-placeholder">Sin&nbsp;logo</div>
                        @endif
                    </div>
                </td>
                <td class="header-title">
                    <h1>{{ $gamesEdition->name }}</h1>
                    <h2>
                        Padrón Oficial de Participantes
                        @if($isSingleTeam && $headerTeamName)
                            &mdash; {{ $headerTeamName }}
                        @endif
                    </h2>
                </td>
                <td class="header-logo-cell">
                    @if($isSingleTeam)
                        <div class="header-logo-box">
                            @if(!empty($headerTeamLogoBase64))
                                <img src="{{ $headerTeamLogoBase64 }}" alt="Logo de {{ $headerTeamName }}">
                            @else
                                <div class="header-logo-placeholder">Sin&nbsp;logo</div>
                            @endif
                        </div>
                    @endif
                </td>
            </tr>
        </table>
        <div class="header-rule"></div>
        <div class="info-bar">
            <table>
                <tr>
                    <td class="label">Olimpiada:</td>
                    <td><strong>{{ $gamesEdition->name }}</strong> &mdash; {{ $season->name }}</td>
                    <td class="label" style="text-align: right;">Fecha emisión:</td>
                    <td style="text-align: right;">{{ $generatedAt }}</td>
                </tr>
                <tr>
                    <td class="label">Organizador:</td>
                    <td>{{ $organizer->name }}</td>
                </tr>
            </table>
        </div>
    </header>

    <!-- 2. PIE DE PÁGINA FIJO EN TODAS LAS PÁGINAS -->
    <footer>
        <div class="footer-line"></div>
        @if(!empty($organizerSignatureBase64))
            <div style="text-align:center;">
                <img src="{{ $organizerSignatureBase64 }}" style="height: 22px; display: inline-block;" alt="Firma del organizador">
            </div>
        @endif
        <div class="footer-text">
            {{ config('app.name') }} &bull; Padrón de Jugadores &bull; {{ $gamesEdition->name }} &bull; {{ $season->name }} &bull;
            Generado: {{ $generatedAt }} &bull; Página <span class="page-number"></span>
        </div>
    </footer>

    <!-- 3. CONTENIDO PRINCIPAL DINÁMICO: una sección por inscripción (season_team) -->
    <main>
        @forelse($teams as $block)
            <div class="team-section">
                <!-- Cabecera del Equipo -->
                <div class="team-header">
                    <table class="team-header-tbl">
                        <tr>
                            @unless($isSingleTeam)
                                <td class="team-logo-cell">
                                    <div class="team-logo-box">
                                        @if(!empty($block['logoBase64']))
                                            <img src="{{ $block['logoBase64'] }}" alt="Logo de {{ $block['team']->name }}">
                                        @else
                                            <div class="team-logo-placeholder"></div>
                                        @endif
                                    </div>
                                </td>
                            @endunless
                            <td class="team-name-cell">
                                <span class="team-name-text">{{ $block['team']->name }}</span>
                            </td>
                            @unless($isSingleTeam)
                                <td class="team-logo-cell"></td>
                            @endunless
                        </tr>
                    </table>
                </div>

                <!-- Tabla de Jugadores (plantel de la temporada) -->
                <table class="players-table">
                    <thead>
                        <tr>
                            <th style="width: 25px; text-align: center;">#</th>
                            <th style="width: 80px;">Documento</th>
                            <th style="width: 145px;">Apellidos y Nombres</th>
                            <th style="width: 65px; text-align: center;">F. Nac.</th>
                            <th style="width: 45px; text-align: center;">Dorsal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($block['entries'] as $entry)
                            <tr style="{{ $entry->status !== \App\Enums\SeasonTeamPlayerStatus::Active ? 'opacity: 0.6;' : '' }}">
                                <td style="text-align: center; color: #9ca3af;">{{ $loop->iteration }}</td>
                                <td>{{ $entry->player->dni }}</td>
                                <td><strong>{{ mb_strtoupper($entry->player->last_name, 'UTF-8') }}</strong>, {{ mb_strtoupper($entry->player->first_name, 'UTF-8') }}</td>
                                <td style="text-align: center;">
                                    {{ $entry->player->birth_date ? $entry->player->birth_date->format('d/m/Y') : '-' }}
                                </td>
                                <td class="number-cell">{{ $entry->shirt_number ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; color: #9ca3af; padding: 15px;">
                                    No hay jugadores inscritos en este equipo.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="team-footer">
                    Total jugadores: <strong>{{ $block['entries']->count() }}</strong>
                    @if($block['activeCount'] > 0)
                        | <span class="active-count">Activos: {{ $block['activeCount'] }}</span>
                    @endif
                    @if($block['inactiveCount'] > 0)
                        | <span class="inactive-count">Inactivos: {{ $block['inactiveCount'] }}</span>
                    @endif
                </div>

                <!-- Firmas: delegado efectivo de la temporada (si hay) + organizador -->
                <div class="signature-section">
                    <table style="width: 100%; border: none; border-collapse: collapse;">
                        <tr>
                            @if($block['delegate'])
                                <td style="width: 50%; text-align: center; vertical-align: bottom;">
                                    <div class="signature-box">
                                        <div class="signature-line">
                                            <div class="signature-name">{{ $block['delegate']->name }}</div>
                                            <div class="signature-role">Delegado de Equipo</div>
                                            <div class="signature-role">{{ $block['team']->name }}</div>
                                        </div>
                                    </div>
                                </td>
                            @endif

                            <td style="width: {{ $block['delegate'] ? '50%' : '100%' }}; text-align: center; vertical-align: bottom;">
                                <div class="signature-box">
                                    <div class="signature-line">
                                        <div class="signature-name">{{ $organizer->name }}</div>
                                        <div class="signature-role">Organizador de la Olimpiada</div>
                                        <div class="signature-role">{{ $gamesEdition->name }}</div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Salto de página entre equipos -->
            @if(!$loop->last)
                <div class="page-break"></div>
            @endif

        @empty
            <div style="text-align: center; padding: 40px; color: #9ca3af;">
                <p style="font-size: 14px;">No se encontraron equipos inscritos para el filtro seleccionado.</p>
            </div>
        @endforelse
    </main>

</body>
</html>
