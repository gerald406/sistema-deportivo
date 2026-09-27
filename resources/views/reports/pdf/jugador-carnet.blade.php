<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Fotocheck &mdash; {{ $player->fullName() }}</title>
<style>


@page { size: 210mm 297mm; margin: 0; }

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #aec3d4;
    width: 210mm;
    height: 297mm;
}

/* Centra la tarjeta horizontalmente en A4 */
.page {
    width: 210mm;
    padding-top: 62mm;
    text-align: center;
}


.card {
    display: inline-block;
    width: 54mm;
    background: #ffffff;
    border-radius: 3mm;
    text-align: left;
    border: 0.3pt solid #7090aa;
}

/* ────────────────────────────────────────────────────────────────
   ① CABECERA — Nombre del campeonato
   ──────────────────────────────────────────────────────────────── */
.hdr {
    background: #0b2342;
    padding: 2.8mm 3mm 2.5mm;
    text-align: center;
    border-radius: 3mm 3mm 0 0;
}

.hdr-app {
    color: #5ab3e6;
    font-size: 4.5pt;
    font-weight: bold;
    letter-spacing: 2pt;
    text-transform: uppercase;
    margin-bottom: 1.2mm;
}

.hdr-champ {
    color: #ffffff;
    font-size: 7.5pt;
    font-weight: bold;
    line-height: 1.3;
    text-transform: uppercase;
    letter-spacing: 0.2pt;
}

/* ────────────────────────────────────────────────────────────────
   BARRAS DECORATIVAS
   ──────────────────────────────────────────────────────────────── */
.bar-gold { height: 0.75mm; background: #e8960a; }
.bar-blue { height: 0.7mm; background: #1850cc; }

/* ────────────────────────────────────────────────────────────────
   ② NOMBRE DEL EQUIPO
   Reemplaza la antigua badge "CARNET DE JUGADOR"
   ──────────────────────────────────────────────────────────────── */
.team-section {
    background: #1a5699;
    padding: 2mm 3mm;
    text-align: center;
    border-bottom: 0.3pt solid #144a87;
}

/* Tabla: logo pequeño | nombre del equipo */
.team-tbl {
    width: 100%;
    border-collapse: collapse;
}
.team-tbl td { vertical-align: middle; padding: 0; }

.tl-logo-cell { width: 5.5mm; padding-right: 1.5mm; }

.tl-logo {
    width: 4.5mm;
    height: 4.5mm;
    border-radius: 50%;
    display: block;
}

.tl-name-cell { text-align: center; }

.team-name {
    color: #ffffff;
    font-size: 7.5pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.4pt;
    line-height: 1.2;
}

/* ────────────────────────────────────────────────────────────────
   ③ FOTO DEL JUGADOR
   Proporción 3:4 — Marco con borde azul
   ──────────────────────────────────────────────────────────────── */
.photo-wrap {
    background: #edf2f7;
    padding: 3mm 3mm 2.5mm;
    text-align: center;
    border-bottom: 0.3pt solid #c8d8e8;
}

/* Marco foto: de 36×40 → 28×31 mm  (misma proporción 9:10, ×0.78) */
.photo-frame {
    width: 26mm;      /* era 36mm */
    height: 29mm;     /* era 40mm  →  28 × (40/36) = 31.1mm */
    margin: 0 auto;
    border-radius: 2mm;
    border: 1.8pt solid #1a5699;
    background: #c8d8e8;
    overflow: hidden;
}

.photo-frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* Ajusta el texto placeholder proporcionalmente */
.photo-no {
    padding-top: 7mm;   /* era 14mm  →  14 × (28/36) ≈ 10.9mm */
    color: #8dacc4;
    font-size: 6pt;
    text-align: center;
    line-height: 1.7;
}

/* Badge capitán */
.captain-badge {
    display: inline-block;
    background: #e8960a;
    color: #ffffff;
    font-size: 6.5pt;
    font-weight: bold;
    padding: 0.6mm 2mm;
    border-radius: 5mm;
    margin-top: 2mm;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
}

/* ────────────────────────────────────────────────────────────────
   ④ NOMBRE COMPLETO DEL JUGADOR + DNI
   El DNI reemplaza el antiguo número de camiseta (p-dorsal).
   Layout via <table> HTML (DomPDF-safe).
   ──────────────────────────────────────────────────────────────── */
.name-wrap {
    background: #0d3360;
    padding: 2.5mm  2.5mm;
    text-align: center;
}

/* Nombre completo — máximo 2 líneas */
.p-name {
    color: #ffffff;
    font-size: 5.5pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.2pt;
    line-height: 1.25;
    /* margin-bottom: 1mm; */
}

/* Fila: posición (izq.) | DNI badge (der.) */
.sub-tbl {
    width: 100%;
    border-collapse: collapse;
}
.sub-tbl td { vertical-align: middle; padding: 0; }

.sub-pos-cell { text-align: left; }

.p-pos {
    color: #88c0e8;
    font-size: 5.5pt;
    font-style: italic;
}

.sub-dni-cell { text-align: right; }

/* DNI Badge: fondo semitransparente dorado */
.dni-badge {
    display: inline-block;
    background: rgba(232, 150, 10, 0.20);
    border: 0.6pt solid rgba(232, 150, 10, 0.50);
    border-radius: 4mm;
    padding: 0.4mm 2mm;
}

.dni-lbl {
    color: #e8960a;
    font-size: 4.5pt;
    font-weight: bold;
    letter-spacing: 0.5pt;
    text-transform: uppercase;
    margin-right: 0.8mm;
}

.dni-val {
    color: #fce4a8;
    font-size: 6pt;
    font-weight: bold;
    letter-spacing: 0.3pt;
}

/* ────────────────────────────────────────────────────────────────
   ⑤ CÓDIGO QR + NÚMERO DE CARNET
   2 columnas: QR (izq.) | número + fecha (der.)
   <table> HTML → garantiza renderizado correcto en DomPDF.
   ──────────────────────────────────────────────────────────────── */
.qr-wrap {
    background: #f4f8fb;
    padding: 1mm 3.5mm;
    border-top: 0.3pt solid #c8d8e8;
    border-bottom: 0.3pt solid #c8d8e8;
}

.qr-tbl {
    width: 100%;
    border-collapse: collapse;
}
.qr-tbl td { vertical-align: middle; padding: 0; }

/* Celda izquierda: imagen QR */
.qr-img-cell { width: 13mm; }

.qr-img {
    width: 13mm;
    height: 13mm;
    display: block;
}

.qr-no {
    width: 13mm;
    height: 13mm;
    background: #dde8f2;
    font-size: 4pt;
    color: #8dacc4;
    text-align: center;
    padding-top: 5mm;
    display: block;
    border-radius: 1mm;
}

/* Celda derecha: número de carnet y fecha emisión */
.qr-meta-cell {
    text-align: right;
    padding-left: 2mm;
}

.qr-lbl {
    font-size: 4.5pt;
    color: #8dacc4;
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    margin-bottom: 0.8mm;
}

.qr-num {
    font-size: 7pt;
    font-weight: bold;
    color: #0b2342;
    display: block;
    letter-spacing: 0.2pt;
}

.qr-date {
    font-size: 5pt;
    color: #5a7a96;
    display: block;
    margin-top: 1.2mm;
}

/* ────────────────────────────────────────────────────────────────
   ⑥ FOOTER — Solo nombre del organizador
   Se eliminó el bloque de texto legal de la izquierda.
   ──────────────────────────────────────────────────────────────── */
.footer {
    background: #0b2342;
    padding: 2mm 3mm;
    border-radius: 0 0 3mm 3mm;
}

.footer-tbl {
    width: 100%;
    border-collapse: collapse;
}
.footer-tbl td { vertical-align: middle; padding: 0; }

/* Indicador visual izquierdo: punto gold (reemplaza el bloque de texto) */
.ft-dot-cell { width: 4mm; }

.ft-dot {
    width: 3mm;
    height: 3mm;
    background: #e8960a;
    border-radius: 50%;
    display: block;
}

/* Nombre del organizador + firma (derecha) */
.ft-org-cell { text-align: right; }

.ft-sig {
    height: 6mm;
    display: block;
    margin-left: auto;
    margin-bottom: 0.3mm;
}

.ft-org-name {
    color: #c0d8ef;
    font-size: 5pt;
    display: block;
    text-align: right;
    letter-spacing: 0.2pt;
}
</style>
</head>
    <body>

        <div class="page">
            <div class="card">

                {{-- ══════════════════════════════════════
                    ① CABECERA: Nombre del Campeonato
                ══════════════════════════════════════ --}}
                <div class="hdr">
                    <div class="hdr-app">{{ config('app.name') }}</div>
                    <div class="hdr-champ">{{ $tournament->name }} · {{ $season->name }}</div>
                </div>

                <div class="bar-gold"></div>
                <div class="bar-blue"></div>

                {{-- ══════════════════════════════════════
                    ② NOMBRE DEL EQUIPO
                    Reemplaza la antigua badge "CARNET DE JUGADOR"
                ══════════════════════════════════════ --}}
                <div class="team-section">
                    @if (! empty($teamLogoBase64))
                        <table class="team-tbl">
                            <tr>
                                <td class="tl-logo-cell">
                                    <img src="{{ $teamLogoBase64 }}" class="tl-logo" alt="">
                                </td>
                                <td class="tl-name-cell">
                                    <span class="team-name">{{ $team->name ?? 'Equipo' }}</span>
                                </td>
                            </tr>
                        </table>
                    @else
                        <span class="team-name" style="display:block; text-align:center;">
                            {{ $team->name ?? 'Equipo' }}
                        </span>
                    @endif
                </div>

                {{-- ══════════════════════════════════════
                    ③ FOTO DEL JUGADOR
                ══════════════════════════════════════ --}}
                <div class="photo-wrap">
                    <div class="photo-frame">
                        @if (! empty($playerPhotoBase64))
                            <img src="{{ $playerPhotoBase64 }}" alt="Foto {{ $player->fullName() }}">
                        @else
                            <div class="photo-no">SIN<br>FOTO</div>
                        @endif
                    </div>
                    <div style="text-align:center;">
                        <span class="captain-badge">DNI: {{ $player->dni }}</span>
                    </div>

                </div>

                {{-- ══════════════════════════════════════
                    ④ NOMBRE DEL JUGADOR + DNI
                    • DNI reemplaza el número de camiseta
                    • Posición (izq.) | DNI badge (der.)
                ══════════════════════════════════════ --}}
                <div class="name-wrap">

                    <div class="p-name">{{ mb_strtoupper($player->fullName()) }}</div>

                </div>

                {{-- ══════════════════════════════════════
                    ⑤ CÓDIGO QR + NÚMERO DE CARNET
                    2 columnas via <table> HTML (DomPDF-safe)
                ══════════════════════════════════════ --}}
                <div class="qr-wrap">
                    <table class="qr-tbl">
                        <tr>
                            {{-- QR izquierda --}}
                            <td class="qr-img-cell">
                                @if (! empty($qrCode))
                                    <img src="{{ $qrCode }}" class="qr-img" alt="QR verificación">
                                @else
                                    <div class="qr-no">QR<br>no disp.</div>
                                @endif
                            </td>

                            {{-- Número de carnet + fecha (derecha) --}}
                            <td class="qr-meta-cell">
                                <span class="qr-lbl">Núm. de Carnet</span>
                                <span class="qr-num">{{ $cardNumber }}</span>
                                <span class="qr-date">Emisión: {{ $issueDate }}</span>
                            </td>
                        </tr>
                    </table>
                </div>

                {{-- ══════════════════════════════════════
                    ⑥ FOOTER — Solo nombre del organizador
                    Se eliminó el texto legal de la izquierda
                ══════════════════════════════════════ --}}
                <div class="footer">
                    <table class="footer-tbl">
                        <tr>
                            <td class="ft-dot-cell">
                                <span class="ft-dot"></span>
                            </td>
                            <td class="ft-org-cell">
                                @if (! empty($organizerSignatureBase64))
                                    <img src="{{ $organizerSignatureBase64 }}" class="ft-sig" alt="Firma">
                                @endif
                                <span class="ft-org-name">{{ $organizer->name ?? 'Organizador' }}</span>
                            </td>
                        </tr>
                    </table>
                </div>

            </div>{{-- /.card --}}
        </div>{{-- /.page --}}

    </body>
</html>