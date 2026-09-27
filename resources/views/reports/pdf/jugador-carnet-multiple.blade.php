<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Fotochecks &mdash; Generación Masiva</title>
<style>
/*
 * ═══════════════════════════════════════════════════════════════════
 *  FOTOCHECKS MASIVOS — Laravel DomPDF
 *
 *  ▸ 3 columnas × N filas en A4 (210 × 297 mm)
 *  ▸ Margen: 9 mm c/lado → 192 mm útiles
 *  ▸ Tarjeta: 58 mm ancho | gap: 5 mm entre columnas
 *    (58 × 3) + (5 × 2) = 184 mm → cabe en 192 mm
 *
 *  ▸ Diseño idéntico al carnet individual (jugador-carnet.blade.php)
 *    escalado proporcionalmente para tarjeta de 58 mm.
 *
 *  CORRECCIONES DOMPDF:
 *  ✅ overflow:hidden ELIMINADO de .card
 *  ✅ <table> HTML en todos los layouts multicolumna
 *  ❌ NO float:left en contenedores de texto
 *  ❌ NO display:table CSS
 * ═══════════════════════════════════════════════════════════════════
 */

@page { size: 210mm 297mm; margin: 0; }

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #ffffff;
    padding: 4mm 9mm;
}

.grid-wrapper { width: 192mm; }

/* Fila separadora (clearfix) entre filas de tarjetas */
.row-break {
    clear: both;
    height: 1mm;
    display: block;
}

/*
 * ── TARJETA ──────────────────────────────────────────────────────
 * ⚠️ CRÍTICO: overflow:hidden ELIMINADO.
 *    DomPDF recortaba QR y footer con ese valor.
 *    border-radius funciona sin overflow:hidden en DomPDF.
 */
.card {
    float: left;
    width: 58mm;
    background: #ffffff;
    border-radius: 3mm;
    border: 0.3pt solid #7090aa;
    page-break-inside: avoid;
}

.card-gap { margin-right: 8mm; }

/* ────────────────────────────────────────────────────────────────
   ① CABECERA
   — Sincronizado con individual: colores y espaciado idénticos,
     tamaños de fuente reducidos proporcionalmente para 58mm.
   ──────────────────────────────────────────────────────────────── */
.hdr {
    background: #0b2342;                   /* = individual */
    padding: 2.2mm 3mm 2mm;
    text-align: center;
    border-radius: 3mm 3mm 0 0;
}

.hdr-app {
    color: #5ab3e6;                        /* = individual */
    font-size: 4pt;
    font-weight: bold;
    letter-spacing: 1.5pt;
    text-transform: uppercase;
    margin-bottom: 1mm;
}

.hdr-champ {
    color: #ffffff;                        /* = individual */
    font-size: 6pt;
    font-weight: bold;
    line-height: 1.25;
    text-transform: uppercase;
}

/* ── Barras decorativas — SINCRONIZADAS con individual ──
   individual: gold 0.75mm / blue 0.7mm
   anterior masivo: gold 1.2mm / blue 0.6mm  ← CORREGIDO
*/
.bar-gold { height: 0.75mm; background: #e8960a; }  /* = individual */
.bar-blue { height: 0.7mm;  background: #1850cc; }  /* = individual */

/* ────────────────────────────────────────────────────────────────
   ② NOMBRE DEL EQUIPO
   — Colores y lógica idénticos al individual.
   ──────────────────────────────────────────────────────────────── */
.team-section {
    background: #1a5699;                   /* = individual */
    padding: 1.8mm 3mm;
    text-align: center;
    border-bottom: 0.3pt solid #144a87;    /* = individual */
}

/* .team-tbl { width: 100%; border-collapse: collapse; }
.team-tbl td { vertical-align: middle; padding: 0; } */

/* .tl-logo-cell { width: 11mm; padding-right: 1.5mm; } 

.tl-logo {
    width: 9.5mm;
    height: 9.5mm;
    border-radius: 10%;
    display: block;
} */

/* ── Logo del equipo junto al QR ──────────────────────────────── */
.qr-logo-cell {
    width: 11mm;
    padding-left: 5.5mm;    /* gap entre QR y logo */
}

.qr-team-logo {
    width: 10.5mm;
    height: 10.5mm;
    border-radius: 10%;
    display: block;
}

/* .tl-logo-cell { width: 4.5mm; padding-right: 1.2mm; }

.tl-logo {
    width: 3.8mm;
    height: 3.8mm;
    border-radius: 50%;
    display: block;
}

.tl-name-cell { text-align: center; } */

.team-name {
    color: #ffffff;                        /* = individual */
    font-size: 6pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.3pt;
    line-height: 1.2;
}

/* ────────────────────────────────────────────────────────────────
   ③ FOTO DEL JUGADOR
   — Lógica SINCRONIZADA con individual:
     • DNI badge siempre visible debajo de la foto.
     • Se elimina el condicional de capitán (igual que individual).
   ──────────────────────────────────────────────────────────────── */
.photo-wrap {
    background: #edf2f7;                   /* = individual */
    padding: 2.5mm 3mm 2mm;
    text-align: center;
    border-bottom: 0.3pt solid #c8d8e8;   /* = individual */
}

/* Marco foto — proporción idéntica al individual (≈ 9:10) */
.photo-frame {
    width: 28mm;
    height: 31mm;                          /* 28 × (29/26) ≈ 31mm */
    margin: 0 auto;
    border-radius: 1.5mm;
    border: 1.8pt solid #1a5699;           /* = individual */
    background: #c8d8e8;                   /* = individual */
    overflow: hidden;                      /* OK: solo recorta la imagen */
}

.photo-frame img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.photo-no {
    padding-top: 10mm;
    color: #8dacc4;                        /* = individual */
    font-size: 5.5pt;
    text-align: center;
    line-height: 1.6;
}

/*
 * Badge DNI — SINCRONIZADO con individual.
 * En el individual se usa .captain-badge para mostrar el DNI
 * (sin condicional de capitán). Se replica la misma lógica.
 * Tamaño de fuente reducido proporcionalmente para 58mm.
 */
.captain-badge {
    display: inline-block;
    background: #e8960a;                   /* = individual */
    color: #ffffff;                        /* = individual */
    font-size: 5pt;                        /* individual: 6.5pt → escala ~0.78 */
    font-weight: bold;
    padding: 0.5mm 1.8mm;
    border-radius: 5mm;                    /* = individual */
    margin-top: 1.5mm;
    text-transform: uppercase;
    letter-spacing: 0.3pt;                 /* = individual */
}

/* ────────────────────────────────────────────────────────────────
   ④ NOMBRE DEL JUGADOR
   — SINCRONIZADO con individual:
     • Solo muestra el nombre completo (p-name).
     • Se eliminó la sub-tabla de posición + DNI
       (el DNI ya aparece en el badge de la foto, igual que individual).
   ──────────────────────────────────────────────────────────────── */
.name-wrap {
    background: #0d3360;                   /* = individual */
    padding: 2mm 2.5mm;
    text-align: center;
}

.p-name {
    color: #ffffff;                        /* = individual */
    font-size: 6pt;                        /* individual: 5.5pt → ligero ajuste para 58mm */
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.2pt;                 /* = individual */
    line-height: 1.25;                     /* = individual */
}

/* ────────────────────────────────────────────────────────────────
   ⑤ CÓDIGO QR + NÚMERO DE CARNET
   — Lógica, colores y estructura idénticos al individual.
     Tamaños ajustados proporcionalmente para 58mm.
   ──────────────────────────────────────────────────────────────── */
.qr-wrap {
    background: #f4f8fb;                   /* = individual */
    padding: 1mm 3mm;                      /* individual: 1mm 3.5mm */
    border-top: 0.3pt solid #c8d8e8;       /* = individual */
    border-bottom: 0.3pt solid #c8d8e8;    /* = individual */
}

.qr-tbl { width: 100%; border-collapse: collapse; }
.qr-tbl td { vertical-align: middle; padding: 0; }

/* Celda izquierda: imagen QR */
.qr-img-cell { width: 14mm; }             /* individual: 13mm → +1mm para 58mm */

.qr-img {
    width: 14mm;
    height: 14mm;
    display: block;
}

.qr-no {
    width: 14mm;
    height: 14mm;
    background: #dde8f2;                   /* = individual */
    font-size: 4pt;
    color: #8dacc4;                        /* = individual */
    text-align: center;
    padding-top: 4.5mm;
    display: block;
    border-radius: 1mm;
}

/* Celda derecha: número de carnet y fecha */
.qr-meta-cell {
    text-align: right;
    padding-left: 1.5mm;
}

.qr-lbl {
    font-size: 4pt;
    color: #8dacc4;                        /* = individual */
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.4pt;
    margin-bottom: 0.6mm;
}

.qr-num {
    font-size: 5.5pt;                      /* individual: 7pt → escala ~0.78 */
    font-weight: bold;
    color: #0b2342;                        /* = individual */
    display: block;
    letter-spacing: 0.2pt;
}

.qr-date {
    font-size: 4.5pt;                      /* individual: 5pt → escala ~0.78 */
    color: #5a7a96;                        /* = individual */
    display: block;
    margin-top: 0.8mm;
}

/* ────────────────────────────────────────────────────────────────
   ⑥ FOOTER — Solo nombre del organizador
   — Colores y estructura 100% idénticos al individual.
   ──────────────────────────────────────────────────────────────── */
.footer {
    background: #0b2342;                   /* = individual */
    padding: 1.8mm 3mm;
    border-radius: 0 0 3mm 3mm;           /* = individual */
}

.footer-tbl { width: 100%; border-collapse: collapse; }
.footer-tbl td { vertical-align: middle; padding: 0; }

.ft-dot-cell { width: 3.5mm; }

.ft-dot {
    width: 2.5mm;
    height: 2.5mm;
    background: #e8960a;                   /* = individual */
    border-radius: 50%;                    /* = individual */
    display: block;
}

.ft-org-cell { text-align: right; }

.ft-sig {
    height: 5mm;                           /* individual: 6mm → escala ~0.78 */
    display: block;
    margin-left: auto;
    margin-bottom: 0.2mm;
}

.ft-org-name {
    color: #c0d8ef;                        /* = individual */
    font-size: 4.5pt;                      /* individual: 5pt → escala ~0.78 */
    display: block;
    text-align: right;
    letter-spacing: 0.1pt;
}
</style>
</head>
<body>

    <div class="grid-wrapper">

        @foreach ($cards as $index => $card)

            @php
                $colPos      = $index % 3;
                $isLastInRow = $colPos === 2;
                $hasGap      = ! $isLastInRow;
            @endphp

            <div class="card {{ $hasGap ? 'card-gap' : '' }}">

                {{-- ══════════════════════════════════════
                    ① CABECERA
                    hdr-app: nombre de la aplicacion (config app.name)
                    igual que en jugador-carnet.blade.php
                ══════════════════════════════════════ --}}
                <div class="hdr">
                    <div class="hdr-app">{{ config('app.name') }}</div>
                    <div class="hdr-champ">{{ $card['tournament']->name }} · {{ $card['season']->name }}</div>
                </div>
                <div class="bar-gold"></div>
                <div class="bar-blue"></div>

                {{-- ══════════════════════════════════════
                    ② NOMBRE DEL EQUIPO
                ══════════════════════════════════════ --}}
                <div class="team-section">
                    <span class="team-name" style="display:block; text-align:center;">
                        {{ optional($card['team'])->name ?? 'Equipo' }}
                    </span>
                </div>

                {{-- ══════════════════════════════════════
                    ③ FOTO DEL JUGADOR
                    SINCRONIZADO con individual:
                    • DNI badge siempre visible bajo la foto.
                    • Sin condicional de capitán (= individual).
                ══════════════════════════════════════ --}}
                <div class="photo-wrap">
                    <div class="photo-frame">
                        @if (! empty($card['playerPhotoBase64']))
                            <img src="{{ $card['playerPhotoBase64'] }}" alt="">
                        @else
                            <div class="photo-no">SIN<br>FOTO</div>
                        @endif
                    </div>
                    <div style="text-align:center;">
                        <span class="captain-badge">DNI: {{ $card['player']->dni }}</span>
                    </div>
                </div>

                {{-- ══════════════════════════════════════
                    ④ NOMBRE DEL JUGADOR
                    SINCRONIZADO con individual:
                    • Solo nombre completo, sin sub-tabla.
                    • El DNI ya se muestra en el badge de la foto.
                ══════════════════════════════════════ --}}
                <div class="name-wrap">
                    <div class="p-name">{{ mb_strtoupper($card['player']->fullName()) }}</div>
                </div>

                
                {{-- ══════════════════════════════════════
                    ⑤ CÓDIGO QR + LOGO EQUIPO + NÚMERO DE CARNET
                ══════════════════════════════════════ --}}
                <div class="qr-wrap">
                    <table class="qr-tbl">
                        <tr>
                            {{-- Col 1: QR (sin cambios) --}}
                            <td class="qr-img-cell">
                                @if (! empty($card['qrCode']))
                                    <img src="{{ $card['qrCode'] }}" class="qr-img" alt="QR">
                                @else
                                    <div class="qr-no">QR<br>no disp.</div>
                                @endif
                            </td>

                            {{-- Col 2: Logo del equipo ← NUEVO --}}
                            <td class="qr-logo-cell">
                                @if (! empty($card['teamLogoBase64']))
                                    <img src="{{ $card['teamLogoBase64'] }}"
                                        class="qr-team-logo" alt="">
                                @endif
                            </td>

                            {{-- Col 3: Meta (sin cambios) --}}
                            <td class="qr-meta-cell">
                                <span class="qr-lbl">Núm. de Carnet</span>
                                <span class="qr-num">{{ $card['cardNumber'] }}</span>
                                <span class="qr-date">Emisión: {{ $card['issueDate'] }}</span>
                            </td>
                        </tr>
                    </table>
                </div>

                {{-- ══════════════════════════════════════
                    ⑥ FOOTER — Solo nombre del organizador
                ══════════════════════════════════════ --}}
                <div class="footer">
                    <table class="footer-tbl">
                        <tr>
                            <td class="ft-dot-cell">
                                <span class="ft-dot"></span>
                            </td>
                            <td class="ft-org-cell">
                                @if (! empty($card['organizerSignatureBase64']))
                                    <img src="{{ $card['organizerSignatureBase64'] }}" class="ft-sig" alt="Firma">
                                @endif
                                <span class="ft-org-name">{{ optional($card['organizer'])->name ?? 'Organizador' }}</span>
                            </td>
                        </tr>
                    </table>
                </div>

            </div>{{-- /.card --}}

            @if ($isLastInRow)
                <div class="row-break"></div>
            @endif

        @endforeach

        <div style="clear: both;"></div>

    </div>{{-- /.grid-wrapper --}}

</body>
</html>