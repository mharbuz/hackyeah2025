<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport Prognozy Emerytalnej - ZUS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, rgb(0, 65, 110) 0%, rgb(63, 132, 210) 100%);
            color: white;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .header h1 {
            font-size: 24pt;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 11pt;
            opacity: 0.9;
        }

        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .section-title {
            background-color: rgb(63, 132, 210);
            color: white;
            padding: 10px;
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .card {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }

        .card-title {
            font-size: 12pt;
            font-weight: bold;
            color: rgb(0, 65, 110);
            margin-bottom: 10px;
            border-bottom: 2px solid rgb(255, 179, 79);
            padding-bottom: 5px;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 5px 10px 5px 0;
            width: 50%;
            color: #555;
        }

        .info-value {
            display: table-cell;
            padding: 5px 0;
            color: rgb(0, 65, 110);
            font-weight: bold;
        }

        .highlight-box {
            background: rgb(255, 179, 79);
            color: rgb(0, 65, 110);
            padding: 15px;
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            margin: 15px 0;
            border-radius: 5px;
        }

        .comparison-box {
            background: rgb(0, 153, 63);
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 11pt;
            margin: 10px 0;
        }

        .warning-box {
            background: rgb(240, 94, 94);
            color: white;
            padding: 10px;
            text-align: center;
            font-size: 11pt;
            margin: 10px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 9pt;
        }

        table thead {
            background-color: rgb(63, 132, 210);
            color: white;
        }

        table th {
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }

        table td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .educational-box {
            background-color: #e8f4f8;
            border-left: 4px solid rgb(63, 132, 210);
            padding: 12px;
            margin: 10px 0;
            font-size: 9pt;
        }

        .educational-box strong {
            color: rgb(0, 65, 110);
        }

        .chart-bar {
            background: linear-gradient(to right, rgb(0, 153, 63), rgb(63, 132, 210));
            height: 20px;
            margin: 5px 0;
            position: relative;
        }

        .chart-label {
            position: absolute;
            right: 5px;
            top: 2px;
            color: white;
            font-size: 9pt;
            font-weight: bold;
        }


        .page-break {
            page-break-after: always;
        }

        .stat-grid {
            display: table;
            width: 100%;
            margin: 15px 0;
        }

        .stat-item {
            display: table-cell;
            width: 33.33%;
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
            background-color: #f0f0f0;
        }

        .stat-value {
            font-size: 16pt;
            font-weight: bold;
            color: rgb(0, 65, 110);
        }

        .stat-label {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>

    <!-- Nagłówek -->
    <div class="header">
        <h1>📊 RAPORT PROGNOZY EMERYTALNEJ</h1>
        <p>Szczegółowa analiza Twojej przyszłej emerytury</p>
        <p style="font-size: 9pt; margin-top: 10px;">Wygenerowano: {{ $generation_date }}</p>
    </div>

    <!-- SEKCJA 1: PROFIL UŻYTKOWNIKA -->
    <div class="section">
        <div class="section-title">👤 Twój profil emerytalny</div>
        <div class="card">
            <div class="card-title">Dane podstawowe</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Wiek:</div>
                    <div class="info-value">{{ $profile['age'] }} lat</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Płeć:</div>
                    <div class="info-value">{{ $profile['gender'] === 'male' ? 'Mężczyzna' : 'Kobieta' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Wiek rozpoczęcia pracy:</div>
                    <div class="info-value">{{ $profile['work_start_age'] ?? 'Nie podano' }} lat</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Rok przejścia na emeryturę:</div>
                    <div class="info-value">{{ $profile['retirement_year'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Aktualne wynagrodzenie brutto:</div>
                    <div class="info-value">{{ number_format($profile['current_gross_salary'], 2, ',', ' ') }} zł</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Wskaźnik indeksacji wynagrodzeń:</div>
                    <div class="info-value">{{ $profile['wage_indexation_rate'] }}%</div>
                </div>
                @if(isset($profile['account_balance']) && $profile['account_balance'] > 0)
                <div class="info-row">
                    <div class="info-label">Stan konta ZUS:</div>
                    <div class="info-value">{{ number_format($profile['account_balance'], 2, ',', ' ') }} zł</div>
                </div>
                @endif
                @if(isset($profile['subaccount_balance']) && $profile['subaccount_balance'] > 0)
                <div class="info-row">
                    <div class="info-label">Stan subkonta ZUS:</div>
                    <div class="info-value">{{ number_format($profile['subaccount_balance'], 2, ',', ' ') }} zł</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- SEKCJA 2: GŁÓWNE WYNIKI -->
    <div class="section">
        <div class="section-title">💰 Wyniki prognozy emerytalnej</div>
        
        <div class="highlight-box">
            Twoja prognozowana emerytura: 
            {{ number_format($simulation_results['monthly_pension'], 2, ',', ' ') }} zł brutto
        </div>

        @if(isset($simulation_results['real_pension_value']))
        <div class="comparison-box">
            ✅ Emerytura urealniona (po inflacji): 
            {{ number_format($simulation_results['real_pension_value'], 2, ',', ' ') }} zł
        </div>
        @endif

        @if(isset($simulation_results['economic_context']))
        <div class="card">
            <div class="card-title">📈 Kontekst ekonomiczny</div>
            <div class="info-grid">
                @if(isset($simulation_results['economic_context']['avg_pension_retirement_year']))
                <div class="info-row">
                    <div class="info-label">Średnia krajowa emerytura w {{ $profile['retirement_year'] }}:</div>
                    <div class="info-value">{{ number_format($simulation_results['economic_context']['avg_pension_retirement_year'], 2, ',', ' ') }} zł</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Twoja emerytura vs średnia:</div>
                    <div class="info-value">
                        @if($simulation_results['monthly_pension'] > $simulation_results['economic_context']['avg_pension_retirement_year'])
                            <span style="color: rgb(0, 153, 63);">
                                ✅ Wyższa o {{ number_format($simulation_results['monthly_pension'] - $simulation_results['economic_context']['avg_pension_retirement_year'], 2, ',', ' ') }} zł
                            </span>
                        @else
                            <span style="color: rgb(240, 94, 94);">
                                ⚠️ Niższa o {{ number_format($simulation_results['economic_context']['avg_pension_retirement_year'] - $simulation_results['monthly_pension'], 2, ',', ' ') }} zł
                            </span>
                        @endif
                    </div>
                </div>
                @endif
                @if(isset($simulation_results['economic_context']['replacement_rate']))
                <div class="info-row">
                    <div class="info-label">Stopa zastąpienia:</div>
                    <div class="info-value">{{ number_format($simulation_results['economic_context']['replacement_rate'], 1) }}%</div>
                </div>
                @endif
            </div>
            
            @if(isset($simulation_results['economic_context']['replacement_rate']))
            <div class="educational-box">
                <strong>💡 Co to znaczy?</strong> 
                Stopa zastąpienia {{ number_format($simulation_results['economic_context']['replacement_rate'], 0) }}% oznacza, że na emeryturze będziesz mieć około {{ number_format($simulation_results['economic_context']['replacement_rate'], 0) }}% swojego ostatniego wynagrodzenia. 
                @if($simulation_results['economic_context']['replacement_rate'] < 50)
                    Jest to poziom niższy niż 50%, co może wymagać dodatkowych oszczędności emerytalnych.
                @else
                    To dobry wynik, zapewniający względnie komfortową emeryturę.
                @endif
            </div>
            @endif
        </div>
        @endif

        <!-- Kapitał końcowy -->
        <div class="card">
            <div class="card-title">🏦 Zgromadzony kapitał emerytalny</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Konto główne:</div>
                    <div class="info-value">{{ number_format($simulation_results['account_balance'], 2, ',', ' ') }} zł</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Subkonto:</div>
                    <div class="info-value">{{ number_format($simulation_results['subaccount_balance'], 2, ',', ' ') }} zł</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Kapitał łączny:</div>
                    <div class="info-value" style="font-size: 12pt; color: rgb(0, 153, 63);">
                        {{ number_format($simulation_results['account_balance'] + $simulation_results['subaccount_balance'], 2, ',', ' ') }} zł
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="page-break"></div>

    <!-- SEKCJA 3: WPŁYW ZWOLNIEŃ CHOROBOWYCH -->
    @if(isset($simulation_results['sick_leave_impact']) && $simulation_results['sick_leave_impact']['total_sick_days'] > 0)
    <div class="section">
        <div class="section-title">🏥 Wpływ zwolnień chorobowych na emeryturę</div>
        
        <div class="warning-box">
            ⚠️ Łącznie {{ $simulation_results['sick_leave_impact']['total_sick_days'] }} dni zwolnienia
            (średnio {{ $simulation_results['sick_leave_impact']['average_days_per_year'] }} dni rocznie)
        </div>

        <div class="card">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Zwolnienia historyczne:</div>
                    <div class="info-value">{{ $simulation_results['sick_leave_impact']['total_historical_sick_days'] }} dni</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Prognozowane zwolnienia przyszłe:</div>
                    <div class="info-value">{{ $simulation_results['sick_leave_impact']['total_future_sick_days'] }} dni</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Szacowana redukcja emerytury:</div>
                    <div class="info-value" style="color: rgb(240, 94, 94);">
                        -{{ $simulation_results['sick_leave_impact']['estimated_pension_reduction_percent'] }}%
                    </div>
                </div>
            </div>
        </div>

        <div class="educational-box">
            <strong>📚 Ciekawostka:</strong> Średni Polak spędza na zwolnieniu chorobowym około 16 dni rocznie. 
            Każdy dzień zwolnienia obniża składkę emerytalną o około 80%, co w skali całej kariery może obniżyć kapitał emerytalny o kilka procent.
        </div>
    </div>
    @endif

    <!-- SEKCJA 4: ODROCZENIE EMERYTURY -->
    @if(isset($simulation_results['delayed_retirement_options']) && count($simulation_results['delayed_retirement_options']) > 0)
    <div class="section">
        <div class="section-title">⏰ Co jeśli przepracujesz dłużej?</div>
        
        <div class="card">
            <div class="card-title">Scenariusze odroczenia emerytury</div>
            <table>
                <thead>
                    <tr>
                        <th>Przepracuj dłużej</th>
                        <th>Nowy wiek</th>
                        <th>Kapitał</th>
                        <th>Emerytura miesięczna</th>
                        <th>Wzrost</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($simulation_results['delayed_retirement_options'] as $option)
                    <tr>
                        <td><strong>+{{ $option['additional_years'] }} {{ $option['additional_years'] == 1 ? 'rok' : ($option['additional_years'] < 5 ? 'lata' : 'lat') }}</strong></td>
                        <td>{{ $option['retirement_age'] }} lat</td>
                        <td>{{ number_format($option['total_capital'], 0, ',', ' ') }} zł</td>
                        <td><strong>{{ number_format($option['monthly_pension'], 2, ',', ' ') }} zł</strong></td>
                        <td style="color: rgb(0, 153, 63);">
                            <strong>+{{ number_format($option['monthly_pension'] - $simulation_results['monthly_pension'], 2, ',', ' ') }} zł</strong>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="educational-box">
            <strong>💡 Rekomendacja:</strong> 
            @php
                $bestOption = collect($simulation_results['delayed_retirement_options'])->sortByDesc('monthly_pension')->first();
                $increase = $bestOption['monthly_pension'] - $simulation_results['monthly_pension'];
            @endphp
            Jeśli chcesz zwiększyć emeryturę o około {{ number_format($increase, 0, ',', ' ') }} zł miesięcznie, 
            rozważ przepracowanie o {{ $bestOption['additional_years'] }} {{ $bestOption['additional_years'] == 1 ? 'rok' : ($bestOption['additional_years'] < 5 ? 'lata' : 'lat') }} dłużej.
        </div>
    </div>
    @endif

    <div class="page-break"></div>

    <!-- SEKCJA 5: WZROST KAPITAŁU W CZASIE -->
    @if(isset($simulation_results['account_growth_forecast']) && count($simulation_results['account_growth_forecast']) > 0)
    <div class="section">
        <div class="section-title">📊 Wzrost kapitału emerytalnego rok po roku</div>
        
        @php
            $growthData = $simulation_results['account_growth_forecast'];
            $maxBalance = max(array_column($growthData, 'total_balance'));
            $totalContributions = array_sum(array_column($growthData, 'annual_contribution'));
            $finalBalance = end($growthData)['total_balance'];
            $initialBalance = $growthData[0]['total_balance'];
            $totalGrowth = $finalBalance - $initialBalance;
            $growthPercent = $initialBalance > 0 ? (($finalBalance / $initialBalance - 1) * 100) : 0;
        @endphp

        <div class="stat-grid">
            <div class="stat-item">
                <div class="stat-value">{{ number_format($totalGrowth / count($growthData), 0, ',', ' ') }} zł</div>
                <div class="stat-label">Średni wzrost roczny</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">{{ number_format($totalContributions, 0, ',', ' ') }} zł</div>
                <div class="stat-label">Łączne składki</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">{{ number_format($growthPercent, 1) }}%</div>
                <div class="stat-label">Wzrost całkowity</div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Szczegółowa tabela wzrostu (wybrane lata)</div>
            <table>
                <thead>
                    <tr>
                        <th>Rok</th>
                        <th>Wiek</th>
                        <th>Konto główne</th>
                        <th>Subkonto</th>
                        <th>Roczna składka</th>
                        <th>Kapitał łączny</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($growthData as $index => $item)
                        @if($index % max(1, floor(count($growthData) / 20)) == 0 || $index == count($growthData) - 1)
                        <tr>
                            <td><strong>{{ $item['year'] }}</strong></td>
                            <td>{{ $item['age'] }} lat</td>
                            <td>{{ number_format($item['account_balance'], 0, ',', ' ') }} zł</td>
                            <td>{{ number_format($item['subaccount_balance'], 0, ',', ' ') }} zł</td>
                            <td>{{ number_format($item['annual_contribution'], 0, ',', ' ') }} zł</td>
                            <td><strong>{{ number_format($item['total_balance'], 0, ',', ' ') }} zł</strong></td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot style="background-color: rgb(0, 153, 63); color: white;">
                    <tr>
                        <td colspan="5" style="text-align: right; padding: 10px;"><strong>KAPITAŁ KOŃCOWY:</strong></td>
                        <td style="padding: 10px;"><strong style="font-size: 12pt;">{{ number_format($finalBalance, 0, ',', ' ') }} zł</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    <!-- SEKCJA 6: DANE HISTORYCZNE -->
    @if(count($historical_data) > 0)
    <div class="section">
        <div class="section-title">📜 Historia zatrudnienia (dane wprowadzone)</div>
        <table style="font-size: 8pt;">
            <thead>
                <tr>
                    <th>Rok</th>
                    <th>Wynagrodzenie brutto (miesięcznie)</th>
                    <th>Zwolnienia chorobowe (dni)</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($historical_data, 0, 30) as $item)
                <tr>
                    <td>{{ $item['year'] }}</td>
                    <td>{{ number_format($item['gross_salary'], 2, ',', ' ') }} zł</td>
                    <td>{{ $item['sick_leave_days'] ?? 0 }} dni</td>
                </tr>
                @endforeach
                @if(count($historical_data) > 30)
                <tr>
                    <td colspan="3" style="text-align: center; font-style: italic; color: #999;">
                        ... oraz {{ count($historical_data) - 30 }} więcej lat
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
    @endif

    <!-- SEKCJA 7: DANE PRZYSZŁOŚCIOWE -->
    @if(count($future_data) > 0)
    <div class="section">
        <div class="section-title">🔮 Prognoza przyszłych wynagrodzeń (dane wprowadzone)</div>
        <table style="font-size: 8pt;">
            <thead>
                <tr>
                    <th>Rok</th>
                    <th>Prognozowane wynagrodzenie brutto</th>
                    <th>Prognozowane zwolnienia (dni)</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($future_data, 0, 30) as $item)
                <tr>
                    <td>{{ $item['year'] }}</td>
                    <td>{{ number_format($item['gross_salary'], 2, ',', ' ') }} zł</td>
                    <td>{{ $item['sick_leave_days'] ?? 0 }} dni</td>
                </tr>
                @endforeach
                @if(count($future_data) > 30)
                <tr>
                    <td colspan="3" style="text-align: center; font-style: italic; color: #999;">
                        ... oraz {{ count($future_data) - 30 }} więcej lat
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
    @endif

    <!-- PODSUMOWANIE KOŃCOWE -->
    <div class="section">
        <div class="section-title">✨ Podsumowanie i zalecenia</div>
        
        <div class="educational-box">
            <strong>📌 Najważniejsze informacje:</strong><br><br>
            
            <strong>1. Twoja emerytura:</strong> {{ number_format($simulation_results['monthly_pension'], 2, ',', ' ') }} zł brutto miesięcznie<br>
            
            @if(isset($simulation_results['economic_context']['replacement_rate']))
            <strong>2. Stopa zastąpienia:</strong> {{ number_format($simulation_results['economic_context']['replacement_rate'], 1) }}% 
            - oznacza to, że na emeryturze będziesz mieć {{ number_format($simulation_results['economic_context']['replacement_rate'], 0) }}% swojego ostatniego wynagrodzenia.<br>
            @endif
            
            @if(isset($simulation_results['economic_context']['avg_pension_retirement_year']))
            <strong>3. Porównanie ze średnią:</strong> 
            @if($simulation_results['monthly_pension'] > $simulation_results['economic_context']['avg_pension_retirement_year'])
                Twoja emerytura będzie wyższa od średniej krajowej o {{ number_format($simulation_results['monthly_pension'] - $simulation_results['economic_context']['avg_pension_retirement_year'], 2, ',', ' ') }} zł.<br>
            @else
                Twoja emerytura będzie niższa od średniej krajowej o {{ number_format($simulation_results['economic_context']['avg_pension_retirement_year'] - $simulation_results['monthly_pension'], 2, ',', ' ') }} zł. 
                Rozważ dodatkowe oszczędności emerytalne.<br>
            @endif
            @endif
            
            @if(isset($simulation_results['sick_leave_impact']) && isset($simulation_results['sick_leave_impact']['total_sick_days']) && $simulation_results['sick_leave_impact']['total_sick_days'] > 0)
            <strong>4. Wpływ zwolnień:</strong> Zwolnienia chorobowe obniżają Twoją emeryturę o około {{ $simulation_results['sick_leave_impact']['estimated_pension_reduction_percent'] }}%.<br>
            @endif
            
            @if(isset($simulation_results['delayed_retirement_options']) && count($simulation_results['delayed_retirement_options']) > 0)
            @php
                $firstDelayed = $simulation_results['delayed_retirement_options'][0];
                $increase = $firstDelayed['monthly_pension'] - $simulation_results['monthly_pension'];
            @endphp
            <strong>5. Korzyść z odroczenia:</strong> Przepracowanie o {{ $firstDelayed['additional_years'] }} {{ $firstDelayed['additional_years'] == 1 ? 'rok' : 'lata' }} zwiększy Twoją emeryturę o {{ number_format($increase, 2, ',', ' ') }} zł miesięcznie.<br>
            @endif
        </div>

        <div class="card" style="background: linear-gradient(135deg, rgb(255, 179, 79) 0%, rgb(255, 179, 79) 100%); border: none; color: rgb(0, 65, 110);">
            <div style="text-align: center; font-size: 11pt; font-weight: bold;">
                🎯 Ten raport został wygenerowany na podstawie aktualnych przepisów i założeń ekonomicznych. 
                Rzeczywista emerytura może się różnić w zależności od zmian w prawie i sytuacji gospodarczej.
            </div>
        </div>
    </div>

    <!-- Stopka -->
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Strona {PAGE_NUM} z {PAGE_COUNT}";
            $size = 8;
            $font = $fontMetrics->getFont("DejaVu Sans");
            $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 30;
            $pdf->page_text($x, $y, $text, $font, $size, array(0.5, 0.5, 0.5));
            
            $reportText = "Raport wygenerowany przez System Prognozowania Emerytur ZUS | {{ $generation_date }}";
            $reportWidth = $fontMetrics->get_text_width($reportText, $font, $size) / 2;
            $reportX = ($pdf->get_width() - $reportWidth) / 2;
            $pdf->page_text($reportX, $y - 12, $reportText, $font, $size, array(0.6, 0.6, 0.6));
        }
    </script>

</body>
</html>

