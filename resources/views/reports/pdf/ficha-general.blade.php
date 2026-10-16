<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ficha de Inscripción General</title>
<style>
/* Ficha de inscripcion GENERAL por delegacion y disciplina (bases VII.c y VII.d).
   A4, blanco y negro, una hoja por copia; DomPDF: solo tablas y CSS simple. */
@page { size: 210mm 297mm; margin: 10mm 12mm; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; font-size: 8.4pt; line-height: 1.3; }

.sheet { page-break-after: always; border: 1.2pt solid #000; padding: 5mm 7mm; }
.sheet:last-child { page-break-after: auto; }

.brand-row { width: 100%; margin-bottom: 1.5mm; }
.brand { font-size: 6.3pt; font-weight: bold; letter-spacing: 1.6pt; text-transform: uppercase; color: #fff; background: #1a1a1a; padding: 1mm 2.2mm; }
.copy { text-align: right; font-size: 8pt; font-weight: bold; letter-spacing: 1.2pt; }

.hdr { width: 100%; border-top: 1.2pt solid #000; border-bottom: 1.2pt solid #000; }
.hdr td { vertical-align: middle; text-align: center; padding: 1.5mm 0; }
.logo { width: 20mm; }
.logo img { max-width: 17mm; max-height: 17mm; }
.logo-ph { width: 17mm; height: 17mm; border: 0.8pt dashed #888; font-size: 5.5pt; color: #888; text-transform: uppercase; padding-top: 7mm; margin: 0 auto; }
.ed-name { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; }
.ed-sub { font-size: 7.5pt; color: #444; margin-top: 0.6mm; }

.title { text-align: center; font-size: 11.5pt; font-weight: bold; letter-spacing: 1pt; text-transform: uppercase; margin: 3mm 0 0.5mm; }
.subtitle { text-align: center; font-size: 7pt; color: #555; margin-bottom: 2.5mm; }

.data { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
.data td { border: 0.7pt solid #000; padding: 1.2mm 1.8mm; }
.data .k { width: 24%; background: #ececec; font-weight: bold; font-size: 7.3pt; text-transform: uppercase; }

.list { width: 100%; border-collapse: collapse; }
.list th { background: #1a1a1a; color: #fff; font-size: 6.8pt; text-transform: uppercase; padding: 1.3mm 1mm; border: 0.7pt solid #000; }
.list td { border: 0.7pt solid #000; padding: 1.2mm 1.2mm; font-size: 7.8pt; }
.list tr:nth-child(even) td { background: #f4f4f4; }
.c { text-align: center; }
.empty-row td { height: 5.5mm; }

.decl { margin-top: 3.5mm; border: 0.7pt solid #000; padding: 2mm 2.5mm; font-size: 7.8pt; text-align: justify; }
.decl b { text-transform: uppercase; }

.signs { width: 100%; margin-top: 15mm; }
.signs td { text-align: center; vertical-align: top; }
.signs .sign-cell { width: 50%; padding: 0 6mm; }
.sign-line { border-top: 0.9pt solid #000; padding-top: 1.2mm; }
.sign-name { font-weight: bold; font-size: 8pt; min-height: 3.5mm; }
.sign-role { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.6pt; color: #333; }
.sign-hint { font-size: 6.3pt; color: #777; }

.foot { margin-top: 4mm; font-size: 6.5pt; color: #555; width: 100%; }
.foot td { vertical-align: top; }
</style>
</head>
<body>
@foreach ($forms as $form)
    @foreach (\App\Services\GeneralRegistrationFormService::COPIES as $copy)
    <div class="sheet">
        <table class="brand-row">
            <tr>
                <td><span class="brand">{{ config('app.name') }}</span></td>
                <td class="copy">{{ $copy }}</td>
            </tr>
        </table>

        <table class="hdr">
            <tr>
                <td class="logo">
                    @if (! empty($form['editionLogo']))
                        <img src="{{ $form['editionLogo'] }}" alt="Olimpiada">
                    @else
                        <div class="logo-ph">Olimpiada</div>
                    @endif
                </td>
                <td>
                    <div class="ed-name">{{ $form['edition']->name }}</div>
                    <div class="ed-sub">
                        @if ($form['edition']->start_date)
                            Del {{ $form['edition']->start_date->format('d/m/Y') }} al {{ $form['edition']->end_date?->format('d/m/Y') }}
                        @endif
                        · Organiza: {{ $form['edition']->organizer?->name }}
                    </div>
                </td>
                <td class="logo">
                    @if (! empty($form['teamLogo']))
                        <img src="{{ $form['teamLogo'] }}" alt="Institución">
                    @else
                        <div class="logo-ph">Institución</div>
                    @endif
                </td>
            </tr>
        </table>

        <div class="title">Ficha de Inscripción General</div>
        <div class="subtitle">Por delegación y disciplina (bases, VII.c) · Se presenta por duplicado</div>

        <table class="data">
            <tr>
                <td class="k">Institución</td>
                <td colspan="3"><b>{{ $form['team']->name }}</b></td>
            </tr>
            <tr>
                <td class="k">Disciplina</td>
                <td colspan="3"><b>{{ $form['season']->name }}</b></td>
            </tr>
            <tr>
                <td class="k">Deporte / prueba</td>
                <td>{{ $form['season']->sport->name }}{{ $form['season']->discipline ? ' — '.$form['season']->discipline->name : '' }}</td>
                <td class="k">Nivel / género</td>
                <td>{{ $form['season']->category?->name ?? '—' }} · {{ $form['season']->gender?->label() ?? 'Sin género' }}</td>
            </tr>
            <tr>
                <td class="k">Delegado titular</td>
                <td>{{ $form['delegateName'] ?? '—' }}</td>
                <td class="k">Delegado accesitario</td>
                <td>{{ $form['substituteName'] ?? '—' }}</td>
            </tr>
        </table>

        <table class="list">
            <thead>
                {{-- D30: solo estas cuatro columnas; el DNI llega enmascarado desde el Service --}}
                <tr>
                    <th style="width:8%">N.º</th>
                    <th>Apellidos y nombres</th>
                    <th style="width:18%">DNI</th>
                    <th style="width:10%">Edad</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($form['participants'] as $p)
                    <tr>
                        <td class="c">{{ $p['number'] }}</td>
                        <td>{{ $p['name'] }}</td>
                        <td class="c">{{ $p['dni'] }}</td>
                        <td class="c">{{ $p['age'] ?? '—' }}</td>
                    </tr>
                @endforeach
                {{-- Filas en blanco hasta 12 para que la hoja quede pareja en las disciplinas cortas --}}
                @for ($i = $form['participants']->count(); $i < 12; $i++)
                    <tr class="empty-row"><td class="c">{{ $i + 1 }}</td><td></td><td></td><td></td></tr>
                @endfor
            </tbody>
        </table>

        {{-- D30: texto de la declaracion jurada pedido por la organizacion --}}
        @php($single = $form['participants']->count() === 1)
        <div class="decl">
            <b>DECLARACIÓN JURADA DE LA DIRECCIÓN (BASES, VII.D).</b>
            El(la) Director(a) o los(las) Directores(as) de las instituciones que integran la delegación, de común acuerdo,
            otorgan representación al delegado designado para que, en nombre de la delegación, declare bajo juramento que
            {{ $single ? 'el' : 'los' }} {{ $form['participantsInWords'] }} {{ $single ? 'participante consignado' : 'participantes consignados' }}
            en la presente ficha {{ $single ? 'pertenece' : 'pertenecen' }} a las instituciones que representan y
            {{ $single ? 'goza' : 'gozan' }} de buena salud para participar en la disciplina de
            <b>{{ mb_strtoupper($form['season']->name) }}</b> de los {{ $form['edition']->name }}.
            Asimismo, el delegado asume la responsabilidad de consolidar, verificar y presentar la información consignada en la
            ficha, garantizando su veracidad, en coordinación con los directores de las respectivas instituciones.
        </div>

        {{-- Solo firma el delegado (D30) --}}
        <table class="signs">
            <tr>
                <td></td>
                <td class="sign-cell">
                    <div class="sign-line">
                        <div class="sign-name">{{ $form['delegateName'] ?? '' }}</div>
                        <div class="sign-role">Delegado(a) de la delegación</div>
                        <div class="sign-hint">Firma · DNI: ____________</div>
                    </div>
                </td>
                <td></td>
            </tr>
        </table>

        <table class="foot">
            <tr>
                <td>Total de participantes: {{ $form['participants']->count() }}</td>
                <td style="text-align:right">Emitido el {{ $form['issueDate'] }} · {{ $copy }}</td>
            </tr>
        </table>
    </div>
    @endforeach
@endforeach
</body>
</html>
