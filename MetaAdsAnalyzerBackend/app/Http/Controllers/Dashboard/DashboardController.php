<?php


namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MetaAdsData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $apiKey = $request->header('API_KEY');
        $expectedApiKey = env('API_KEY');
        if ($apiKey !== $expectedApiKey) {
            return response()->json(['error' => 'API Key inválida.'], 401);
        }

        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        $adSetName = $request->query('adSetName');

        $query = MetaAdsData::query();

        if ($startDate) {
            $query->where('InicioInforme', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('FinInforme', '<=', $endDate);
        }
        if ($adSetName) {
            $query->where('NombreConjuntoAnuncios', $adSetName);
        }

        $data = $query->get();

        $adSetNames = MetaAdsData::distinct()->pluck('NombreConjuntoAnuncios');
        $lastUploadDate = MetaAdsData::max('FechaCarga');

        $totalImporteGastado = $data->sum('ImporteGastadoUSD');
        $totalAlcance = $data->sum('Alcance');
        $totalImpresiones = $data->sum('Impresiones');
        $totalResultados = $data->sum('Resultados');

        return response()->json([
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'SelectedAdSet' => $adSetName,
            'AvailableAdSets' => $adSetNames,
            'UltimaCarga' => $lastUploadDate,
            'TotalImporteGastado' => $totalImporteGastado,
            'TotalAlcance' => $totalAlcance,
            'TotalImpresiones' => $totalImpresiones,
            'TotalResultados' => $totalResultados,
            'CostoPromedioResultado' => $totalResultados > 0 ? $totalImporteGastado / $totalResultados : 0,
            'AlcanceVsImpresiones' => $totalImpresiones > 0 ? $totalAlcance / $totalImpresiones : 0,
            'TasaConversion' => $totalImpresiones > 0 ? $totalResultados / $totalImpresiones : 0,
            'CostoPorMilImpresiones' => $totalImpresiones > 0 ? ($totalImporteGastado / $totalImpresiones) * 1000 : 0,
            'PresupuestoDiarioPorConjunto' => $data->groupBy('NombreConjuntoAnuncios')->map->sum('PresupuestoConjuntoAnuncios'),
            'ImporteGastadoPorConjunto' => $data->groupBy('NombreConjuntoAnuncios')->map->sum('ImporteGastadoUSD'),
        ]);
    }

    public function monthly(Request $request)
    {
        $apiKey = $request->header('API_KEY');
        $expectedApiKey = env('API_KEY');
        if ($apiKey !== $expectedApiKey) {
            return response()->json(['error' => 'API Key inválida.'], 401);
        }

        $year = $request->query('year', date('Y'));
        $data = MetaAdsData::whereYear('InicioInforme', $year)->get();

        $result = [
            'ImporteGastado' => array_fill(0, 12, 0),
            'Alcance' => array_fill(0, 12, 0),
            'Impresiones' => array_fill(0, 12, 0),
            'Resultados' => array_fill(0, 12, 0),
        ];

        foreach ($data as $item) {
            $month = date('n', strtotime($item->InicioInforme)) - 1;
            $result['ImporteGastado'][$month] += $item->ImporteGastadoUSD ?? 0;
            $result['Alcance'][$month] += $item->Alcance ?? 0;
            $result['Impresiones'][$month] += $item->Impresiones ?? 0;
            $result['Resultados'][$month] += $item->Resultados ?? 0;
        }

        return response()->json($result);
    }

    public function adsets()
    {
        $data = MetaAdsData::all();

        $grouped = $data->groupBy('NombreConjuntoAnuncios');

        $result = [
            'ImporteGastado' => [],
            'Alcance' => [],
            'Impresiones' => [],
            'Resultados' => [],
            'CostoPorResultado' => [],
        ];

        foreach ($grouped as $adSet => $items) {
            $importe = $items->sum(fn($i) => $i->ImporteGastadoUSD ?? 0);
            $alcance = $items->sum(fn($i) => $i->Alcance ?? 0);
            $impresiones = $items->sum(fn($i) => $i->Impresiones ?? 0);
            $resultados = $items->sum(fn($i) => $i->Resultados ?? 0);
            $costoPorResultado = $resultados > 0 ? $importe / $resultados : 0;

            $result['ImporteGastado'][$adSet] = $importe;
            $result['Alcance'][$adSet] = $alcance;
            $result['Impresiones'][$adSet] = $impresiones;
            $result['Resultados'][$adSet] = $resultados;
            $result['CostoPorResultado'][$adSet] = $costoPorResultado;
        }

        return response()->json($result);
    }

    ////Analisis con IA

    public function generateAnalysis(Request $request)
    {
        $apiKey = $request->header('API_KEY');
        $expectedApiKey = env('API_KEY');
        if ($apiKey !== $expectedApiKey) {
            return response()->json(['error' => 'API Key inválida.'], 401);
        }

        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');
        $adSetName = $request->input('adSetName');

        $query = MetaAdsData::query();
        if ($startDate) {
            $query->where('InicioInforme', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('FinInforme', '<=', $endDate);
        }
        if ($adSetName) {
            $query->where('NombreConjuntoAnuncios', 'like', '%' . $adSetName . '%');
        }
        $campaignData = $query->get();

        if ($campaignData->isEmpty()) {
            return response()->json(['error' => 'No hay datos disponibles para los filtros seleccionados.'], 404);
        }

        $summary = [
            'TotalSpent' => $campaignData->sum('ImporteGastadoUSD'),
            'TotalReach' => $campaignData->sum('Alcance'),
            'TotalImpressions' => $campaignData->sum('Impresiones'),
            'TotalResults' => $campaignData->sum('Resultados'),
            'AdSetCount' => $campaignData->pluck('NombreConjuntoAnuncios')->unique()->count(),
            'CostPerResult' => $campaignData->sum('Resultados') > 0
                ? $campaignData->sum('ImporteGastadoUSD') / $campaignData->sum('Resultados')
                : 0
        ];

        $analysisResult = $adSetName
            ? $this->getAdSetAnalysis($adSetName, $campaignData)
            : $this->getCampaignAnalysis($campaignData);

        $adSets = MetaAdsData::distinct()->pluck('NombreConjuntoAnuncios');

        return response()->json([
            'Analysis' => $analysisResult,
            'HasAnalysis' => true,
            'Summary' => $summary,
            'AvailableAdSets' => $adSets,
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'SelectedAdSet' => $adSetName
        ]);
    }

    private function getAdSetAnalysis($adSetName, $campaignData)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $apiKey = env('OPENAI_API_KEY');
            $apiUrl = 'https://api.openai.com/v1/chat/completions';

            $jsonData = json_encode($campaignData);
            $prompt = "Analiza los siguientes datos del conjunto de anuncios '{$adSetName}' de Meta/Facebook Ads y proporciona insights detallados y recomendaciones específicas para mejorar su rendimiento:\n\n{$jsonData}";

            $body = [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un experto en marketing digital especializado en análisis de campañas de Meta/Facebook Ads. Proporciona análisis detallados e insights accionables basados en los datos proporcionados. Centra tu análisis en datos cuantitativos como CTR, CPC, tasa de conversión, y rendimiento relativo.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 1500
            ];

            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($body)
            ]);

            $result = json_decode($response->getBody(), true);
            return $result['choices'][0]['message']['content'] ?? 'No se pudo generar el análisis.';
        } catch (\Exception $ex) {
            Log::error('Error al obtener análisis específico desde OpenAI: ' . $ex->getMessage());
            return 'Error al generar el análisis: ' . $ex->getMessage();
        }
    }

    private function getCampaignAnalysis($campaignData)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $apiKey = env('OPENAI_API_KEY');
            $apiUrl = 'https://api.openai.com/v1/chat/completions';

            $jsonData = json_encode($campaignData);
            $prompt = "Analiza los siguientes datos de campañas de Meta/Facebook Ads y proporciona insights detallados y recomendaciones para mejorar el rendimiento:\n\n{$jsonData}";

            $body = [
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un experto en marketing digital especializado en análisis de campañas de Meta/Facebook Ads. Proporciona análisis detallados e insights accionables basados en los datos proporcionados.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 1500
            ];

            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($body)
            ]);

            $result = json_decode($response->getBody(), true);
            return $result['choices'][0]['message']['content'] ?? 'No se pudo generar el análisis.';
        } catch (\Exception $ex) {
            \Log::error('Error al obtener análisis de campañas desde OpenAI: ' . $ex->getMessage());
            return 'Error al generar el análisis: ' . $ex->getMessage();
        }
    }



}
