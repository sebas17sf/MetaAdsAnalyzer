<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use League\Csv\Reader;
use App\Models\MetaAdsData;
use App\Models\FileUpload;
use Carbon\Carbon;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Por favor, seleccione un archivo CSV para cargar.'
            ], 400);
        }

        try {
            $file = $request->file('file');
            $userId = $request->input('userId');

            $userId = User::find($userId) ? $userId : null;

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo identificar al usuario actual.'
                ], 401);
            }

            $result = $this->processCsvFile($file, $userId);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    private function processCsvFile($file, $userId)
    {
        if ($file->getClientOriginalExtension() !== 'csv') {
            return [
                'success' => false,
                'message' => 'El archivo debe ser de tipo CSV.',
                'recordsProcessed' => 0
            ];
        }

        try {
            $csv = Reader::createFromPath($file->getRealPath(), 'r');
            $csv->setHeaderOffset(0);
            $records = $csv->getRecords();

            $dataList = [];
            foreach ($records as $record) {
                $ultimoCambio = $record['Último cambio significativo'] ?? null;
                if ($ultimoCambio) {
                    try {
                        $ultimoCambio = Carbon::parse($ultimoCambio)->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        $ultimoCambio = null;
                    }
                }

                $dataList[] = [
                    'InicioInforme' => $record['Inicio del informe'] ?? null,
                    'FinInforme' => $record['Fin del informe'] ?? null,
                    'NombreConjuntoAnuncios' => $record['Nombre del conjunto de anuncios'] ?? null,
                    'EntregaConjuntoAnuncios' => $record['Entrega del conjunto de anuncios'] ?? null,
                    'Puja' => $record['Puja'] ?? null,
                    'TipoPuja' => $record['Tipo de puja'] ?? null,
                    'PresupuestoConjuntoAnuncios' => $record['Presupuesto del conjunto de anuncios'] ?? null,
                    'TipoPresupuestoConjuntoAnuncios' => $record['Tipo de presupuesto del conjunto de anuncios'] ?? null,
                    'UltimoCambioSignificativo' => $ultimoCambio,
                    'ConfiguracionAtribucion' => $record['Configuración de atribución'] ?? null,
                    'Resultados' => $record['Resultados'] ?? null,
                    'IndicadorResultado' => $record['Indicador de resultado'] ?? null,
                    'Alcance' => $record['Alcance'] ?? null,
                    'Impresiones' => $record['Impresiones'] ?? null,
                    'CostoPorResultados' => $record['Costo por resultados'] ?? null,
                    'ImporteGastadoUSD' => $record['Importe gastado (USD)'] ?? null,
                    'Finalizacion' => $record['Finalización'] ?? null,
                    'Inicio' => $record['Inicio'] ?? null,
                    'CargadoPor' => $userId
                ];
            }

            if (empty($dataList)) {
                return [
                    'success' => false,
                    'message' => 'No se pudieron extraer datos del archivo CSV. Verifica el formato.',
                    'recordsProcessed' => 0
                ];
            }

            DB::beginTransaction();
            try {
                $fileName = $file->getClientOriginalName();
                $fileUpload = FileUpload::where('FileName', $fileName)->first();

                if ($fileUpload) {
                    MetaAdsData::where('CargadoPor', $userId)
                        ->where('FechaCarga', $fileUpload->CreatedAt ?? null)
                        ->delete();

                    MetaAdsData::insert($dataList);

                    $fileUpload->RecordsProcessed = count($dataList);
                    $fileUpload->Status = 'Actualizado';
                    $fileUpload->save();
                } else {
                    MetaAdsData::insert($dataList);

                    FileUpload::create([
                        'FileName' => $fileName,
                        'UploadedBy' => $userId,
                        'RecordsProcessed' => count($dataList),
                        'Status' => 'Completado'
                    ]);
                }

                DB::commit();
                return [
                    'success' => true,
                    'message' => 'Se han importado ' . count($dataList) . ' registros correctamente.',
                    'recordsProcessed' => count($dataList)
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Error al guardar los datos: ' . $e->getMessage(),
                    'recordsProcessed' => 0
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al leer el archivo CSV: ' . $e->getMessage(),
                'recordsProcessed' => 0
            ];
        }
    }
}
