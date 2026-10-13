<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ficha de Inscripción</title>
<style>
/* ═══════════════════════════════════════════════════════════════
   FICHA DE INSCRIPCIÓN — A4, 1 jugador por página, B/N
   Versión compacta: garantiza 1 sola hoja por jugador.
   ═══════════════════════════════════════════════════════════════ */

@page { size: 210mm 297mm; margin: 10mm 14mm; }

* { box-sizing: border-box; margin: 0; padding: 0; }

html, body { width: 100%; }

body {
    font-family: Arial, Helvetica, sans-serif;
    color: #1a1a1a;
    font-size: 8.2pt;
    line-height: 1.28;
}

.sheet { page-break-after: always; }
.sheet:last-child { page-break-after: auto; }

/* Marco exterior tipo "documento oficial" */
.page-frame {
    border: 1.3pt solid #000;
    padding: 6mm 18mm;
}

.page-content { padding: 0; }

/* ── MARCA SUPERIOR ───────────────────────────────────────── */
.hdr-brand-row { width: 100%; margin-bottom: 1.8mm; }
.hdr-brand-row td { vertical-align: middle; }

.hdr-brand {
    font-size: 6.3pt; font-weight: bold; letter-spacing: 1.8pt;
    text-transform: uppercase; color: #fff;
    background: #1a1a1a;
    padding: 1.1mm 2.4mm;
    display: inline-block;
}
.hdr-doctitle-inline {
    font-size: 6.3pt; letter-spacing: 0.6pt; text-transform: uppercase;
    color: #666; text-align: right;
}

/* ── CABECERA PRINCIPAL: LOGO IZQ · TÍTULO · LOGO DER ────── */
.hdr-main {
    border-top: 1.3pt solid #000;
    border-bottom: 1.3pt solid #000;
    padding: 2mm 0;
}
.hdr-main-tbl { width: 100%; }
.hdr-main-tbl td { vertical-align: middle; text-align: center; }

.logo-cell { width: 18mm; text-align: center; }
.logo-box {
    width: 16mm;
    height: 16mm;
    margin: 0 auto;
    border: 0.7pt solid #999;
    overflow: hidden;
    text-align: center;
}
.logo-box img { width: 100%; height: 100%; object-fit: contain; display: block; }
.logo-placeholder {
    padding-top: 6mm;
    font-size: 5.3pt;
    color: #bbb;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
}

.hdr-title-cell { padding: 0 2.5mm; }
.hdr-champ {
    font-size: 12.5pt; font-weight: bold; text-transform: uppercase;
    letter-spacing: 0.2pt;
}
.hdr-champ-org {
    font-size: 7.4pt; font-style: italic; color: #333;
    margin-top: 0.6mm;
}
.hdr-champ-slogan {
    font-size: 6.8pt; font-style: italic; color: #666;
    margin-top: 0.4mm;
}

/* ── TÍTULO DE FORMULARIO ─────────────────────────────────── */
.form-title {
    text-align: center;
    font-size: 9.7pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.7pt;
    text-decoration: underline;
    margin-top: 2.2mm;
    margin-bottom: 2.5mm;
}

/* ── TÍTULOS DE SECCIÓN (barra numerada) ─────────────────── */
.section-title-tbl { width: 100%; margin-top: 3.2mm; margin-bottom: 2mm; }
.section-num {
    width: 5.2mm; height: 5.2mm;
    background: #000; color: #fff;
    text-align: center; vertical-align: middle;
    font-size: 6.8pt; font-weight: bold;
    border-radius: 50%;
}
.section-title-text {
    font-size: 7.8pt; font-weight: bold; text-transform: uppercase;
    letter-spacing: 0.6pt; padding-left: 2.1mm;
    border-bottom: 0.6pt solid #000;
    padding-bottom: 1.2mm;
}

/* ── LAYOUT EQUIPO/CATEGORIA/CODIGO + FOTO ───────────────── */
.layout-tbl { width: 100%; }
.layout-tbl > tbody > tr > td { vertical-align: top; }

.gap-cell-lg { width: 4mm; }

.photo-cell { width: 23mm; }
.photo-box {
    width: 23mm;
    height: 25mm;
    border: 1.1pt solid #000;
    text-align: center;
    overflow: hidden;
    background: #fafafa;
}
.photo-box img { width: 100%; height: 100%; object-fit: cover; display: block; }
.photo-placeholder {
    padding-top: 9mm;
    font-size: 5.6pt;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
}
.photo-caption {
    font-size: 5.4pt; color: #777; text-transform: uppercase;
    letter-spacing: 0.3pt; margin-top: 1mm; text-align: center;
    font-weight: bold;
}

/* ── SECCIÓN 1: TABLA DE 2 COLUMNAS (ETIQUETA / VALOR) ───── */
table.info-tbl {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
table.info-tbl td {
    border: 0.6pt solid #000;
    padding: 1.1mm 2mm;
    vertical-align: middle;
    white-space: nowrap;
    overflow: hidden;
}
table.info-tbl td.info-label {
    width: 34%;
    background: #f0f0f0;
    font-size: 6.3pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
    color: #444;
}
table.info-tbl td.info-value {
    width: 66%;
    font-size: 8pt;
    font-weight: bold;
    color: #1a1a1a;
}
table.info-tbl td.info-value.empty { color: #999; font-weight: normal; font-style: italic; }
table.info-tbl td.info-value.info-code { letter-spacing: 0.4pt; }

/* ── CAMPOS TIPO FORMULARIO (estilo casilla) ─────────────── */
table.data-tbl { width: 100%; border-collapse: collapse; }
table.data-tbl td { padding: 0 0 1.8mm 0; vertical-align: bottom; }

.field-label {
    display: block;
    font-size: 5.6pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
    color: #777;
    margin-bottom: 0.7mm;
}
.field-value {
    display: block;
    font-size: 8.3pt;
    font-weight: bold;
    background: #f4f4f4;
    border: 0.6pt solid #000;
    padding: 1.2mm 2mm;
    min-height: 3mm;
}
.field-value.empty { color: #999; font-weight: normal; font-style: italic; background: #fff; }
.field-value.compact { font-size: 7.8pt; text-align: center; padding: 1.2mm 1mm; }

.gap-cell { width: 3mm; }

/* ── COMPROMISO ───────────────────────────────────────────── */
.commitment-box {
    background: #fafafa;
    border: 0.6pt solid #ccc;
    padding: 2.2mm 3mm;
}
.commitment-intro { font-size: 7.3pt; margin-bottom: 1.6mm; color: #333; text-align: justify; }

.commitment-list { width: 100%; }
.commitment-list td { font-size: 7.2pt; padding: 0.4mm 0; vertical-align: top; color: #262626; line-height: 1.25; }
.commitment-list .bullet-num {
    width: 5mm; font-weight: bold; color: #000; font-size: 6.6pt;
}

.commitment-final {
    font-size: 6.9pt; font-style: italic; margin-top: 1.6mm; color: #444;
    border-top: 0.4pt solid #ccc; padding-top: 1.4mm;
}

/* ── FIRMAS ───────────────────────────────────────────────── */
.sign-tbl { width: 100%; border-collapse: collapse; margin-top: 4mm; }
.sign-tbl td { width: 50%; text-align: center; vertical-align: bottom; padding: 0 8mm; }
.sign-tbl.single td { width: 100%; padding: 0 32mm; }
.sign-space { height: 5mm; }
.sign-line { border-top: 0.7pt solid #000; padding-top: 1.2mm; }
.sign-name { font-size: 7.2pt; font-weight: bold; text-transform: uppercase; }
.sign-role { font-size: 5.6pt; color: #777; text-transform: uppercase; letter-spacing: 0.3pt; margin-top: 0.4mm; }

/* ── NOTA DE RESPONSABILIDAD ──────────────────────────────── */
.guarantee-note {
    font-size: 7pt;
    color: #333;
    line-height: 1.3;
    text-align: justify;
    margin-top: 1.2mm;
}

/* Espacio para sello / estampado oficial */
.seal-box {
    border: 0.7pt dashed #999;
    height: 11mm;
    width: 24mm;
    margin: 0 auto;
    text-align: center;
    color: #aaa;
    font-size: 5.4pt;
    text-transform: uppercase;
    letter-spacing: 0.2pt;
    padding-top: 4mm;
}

/* ── FOOTER ───────────────────────────────────────────────── */
.doc-footer {
    margin-top: 3mm;
    padding-top: 1.4mm;
    border-top: 0.6pt solid #000;
    text-align: center;
    font-size: 5.6pt;
    color: #777;
    letter-spacing: 0.2pt;
}
</style>
</head>
<body>

@foreach($cards as $card)
<div class="sheet">
<div class="page-frame">
<div class="page-content">

    {{-- ══════════════════════════════════════
         MARCA + TÍTULO DE DOCUMENTO
    ══════════════════════════════════════ --}}
    <table class="hdr-brand-row">
        <tr>
            <td><span class="hdr-brand">{{ config('app.name') }}</span></td>
            <td class="hdr-doctitle-inline">Registro Oficial &middot; Código {{ $card['cardNumber'] }}</td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         CABECERA: LOGO DEL TORNEO (IZQ) · TORNEO/TEMPORADA · LOGO DEL EQUIPO (DER)
    ══════════════════════════════════════ --}}
    <div class="hdr-main">
        <table class="hdr-main-tbl">
            <tr>
                <td class="logo-cell">
                    <div class="logo-box">
                        @if(!empty($card['gamesEditionLogoBase64']))
                            <img src="{{ $card['gamesEditionLogoBase64'] }}" alt="Logo de la olimpiada">
                        @else
                            <div class="logo-placeholder">Olimpiada</div>
                        @endif
                    </div>
                </td>
                <td class="hdr-title-cell">
                    <div class="hdr-champ">{{ $card['gamesEdition']->name }}</div>
                    <div class="hdr-champ-org">{{ $card['season']->name }} &middot; {{ $card['season']->sport->name }}</div>
                    <div class="hdr-champ-slogan">Organiza: {{ $card['organizer']->name }}</div>
                </td>
                <td class="logo-cell">
                    <div class="logo-box">

                        @if(! empty($card['teamLogoBase64']))
                            <img src="{{ $card['teamLogoBase64'] }}" alt="Logo del equipo">
                        @else
                            <div class="logo-placeholder">Equipo</div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="form-title">Ficha de Inscripción</div>

    {{-- ══════════════════════════════════════
         SECCIÓN 1: EQUIPO / CATEGORÍA / CÓDIGO + FOTO
    ══════════════════════════════════════ --}}
    <table class="section-title-tbl">
        <tr>
            <td class="section-num">1</td>
            <td class="section-title-text">Datos del Equipo y del Jugador</td>
        </tr>
    </table>

    <table class="layout-tbl">
        <tr>
            <td>
                <table class="info-tbl">
                    <tr>
                        <td class="info-label">Equipo</td>
                        <td class="info-value">{{ $card['team']->name }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Código del Jugador</td>
                        <td class="info-value info-code">{{ $card['cardNumber'] }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Apellidos y Nombres</td>
                        <td class="info-value">{{ mb_strtoupper($card['player']->last_name) }}, {{ mb_strtoupper($card['player']->first_name) }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">DNI N&deg;</td>
                        <td class="info-value">{{ $card['player']->dni ?? '' }} </td>
                    </tr>
                    <tr>
                        <td class="info-label">Fecha de Nacimiento - Edad</td>
                        <td class="info-value">{{ $card['player']->birth_date?->format('d/m/Y') ?? '' }}  ----  {{ $card['player']->birth_date?->age ?? '' }} años</td>
                    </tr>
                </table>
            </td>
            <td class="gap-cell-lg"></td>
            <td class="photo-cell">
                <div class="photo-box">
                    @if(!empty($card['playerPhotoBase64']))
                        <img src="{{ $card['playerPhotoBase64'] }}" alt="Foto del jugador">
                    @else
                        <div class="photo-placeholder">SIN<br>FOTOGRAFÍA</div>
                    @endif
                </div>
                <div class="photo-caption">Foto del jugador</div>
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         SECCIÓN 2: COMPROMISO DEL JUGADOR
    ══════════════════════════════════════ --}}
    <table class="section-title-tbl">
        <tr>
            <td class="section-num">2</td>
            <td class="section-title-text">Compromiso del Jugador</td>
        </tr>
    </table>

    <div class="commitment-box">
        <p class="commitment-intro">
            Declaro haber leído, comprendido y aceptado en su totalidad las Bases Específicas de la olimpiada "{{ $card['gamesEdition']->name }}". En ese sentido, me comprometo a:
        </p>
        <table class="commitment-list">
            <tr><td class="bullet-num">01</td><td>Cumplir todas las disposiciones establecidas en las Bases de la Olimpiada.</td></tr>
            <tr><td class="bullet-num">02</td><td>Respetar las decisiones de la Comisión Organizadora, de la comisión de justicia y de los árbitros durante el desarrollo de la olimpiada.</td></tr>
            <tr><td class="bullet-num">03</td><td>Promover el juego limpio, el respeto mutuo, la disciplina y la sana competencia entre todos los participantes.</td></tr>
            <tr><td class="bullet-num">04</td><td>Asumir la responsabilidad por mi conducta dentro y fuera del campo de juego.</td></tr>
            <tr><td class="bullet-num">05</td><td>Acatar las sanciones que correspondan en caso de incumplimiento de las Bases de la Olimpiada.</td></tr>
        </table>
        <p class="commitment-final">En señal de conformidad, suscribo el presente compromiso de manera voluntaria.</p>
    </div>

    <table class="sign-tbl single">
        <tr>
            <td>
                <div class="sign-space"></div>
                <div class="sign-line">
                    <div class="sign-name">{{ mb_strtoupper($card['player']->fullName()) }}</div>
                    <div class="sign-role">Firma del Jugador</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         SECCIÓN 3: GARANTÍA DE LA PROMOCIÓN
    ══════════════════════════════════════ --}}
    <table class="section-title-tbl">
        <tr>
            <td class="section-num">3</td>
            <td class="section-title-text">Garantía de la Promoción</td>
        </tr>
    </table>

    <p class="guarantee-note">
        La promoción y su Presidente y/o Delegado se hacen responsables de la identidad del jugador firmante en esta ficha de inscripción y lo autorizan a registrarse oficialmente como su jugador.
    </p>

    <table class="data-tbl" style="margin-top:2mm;">
        <tr>
            <td style="width:68%;">
                <span class="field-label">Nombres y Apellidos</span>
                @if($card['delegateName'] ?? null)
                    <span class="field-value">{{ mb_strtoupper($card['delegateName']) }}</span>
                @else
                    <span class="field-value empty">No registrado</span>
                @endif
            </td>
            <td class="gap-cell"></td>
            <td style="width:29%;">
                <span class="field-label">DNI N&deg;</span>
                @if($card['delegateDni'] ?? null)
                    <span class="field-value">{{ $card['delegateDni'] }}</span>
                @else
                    <span class="field-value empty">Sin registrar</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="sign-tbl single">
        <tr>
            <td>
                <div class="sign-space"></div>
                <div class="sign-line">
                    <div class="sign-name">&nbsp;</div>
                    <div class="sign-role">Firma del Delegado</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════
         SECCIÓN 4: REGISTRO OFICIAL
    ══════════════════════════════════════ --}}
    <table class="section-title-tbl">
        <tr>
            <td class="section-num">4</td>
            <td class="section-title-text">Registro Oficial</td>
        </tr>
    </table>

    <p class="guarantee-note">
        El jugador firmante de esta Ficha de Inscripción queda registrado oficialmente en el "{{ $card['gamesEdition']->name }}".
    </p>

    <table class="sign-tbl single">
        <tr>
            <td>
                <div class="seal-box" style="margin-bottom: 1.5mm;">Sello de la<br>Olimpiada</div>
                <div class="sign-line">
                    <div class="sign-name">{{ $card['organizer']->name }}</div>
                    <div class="sign-role">Organizador de la Olimpiada</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="doc-footer">
        {{ config('app.name') }} &middot; Documento generado el {{ $card['issueDate'] }} &middot; Código {{ $card['cardNumber'] }}
    </div>

</div>{{-- /.page-content --}}
</div>{{-- /.page-frame --}}
</div>{{-- /.sheet --}}
@endforeach

</body>
</html>