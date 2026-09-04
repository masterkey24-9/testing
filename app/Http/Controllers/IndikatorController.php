namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Satker;
use App\Models\Indicator;
use App\Models\IndicatorResult;
use App\Services\IkpaPdfParserService;
use App\Services\IkpaScoringService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IndicatorController extends Controller
{
    protected $pdfParser;
    protected $scoringService;

    public function __construct(IkpaPdfParserService $pdfParser, IkpaScoringService $scoringService)
    {
        $this->pdfParser = $pdfParser;
        $this->scoringService = $scoringService;
    }

    public function storeUpload(Request $request)
    {
        $request->validate([
            'periode'  => 'required|string',
            'file_pdf' => 'required|mimes:pdf|max:20480',
        ]);

        $file = $request->file('file_pdf');
        $filePath = $file->store('indicators/pdf', 'public');
        $fullPath = storage_path('app/public/' . $filePath);

        // 1. Ekstrak data dari PDF
        $parsedData = $this->pdfParser->parsePdf($fullPath);

        if (empty($parsedData)) {
            return redirect()->back()->with('error', 'Gagal membaca tabel IKPA dari file PDF. Pastikan format dokumen sesuai.');
        }

        DB::beginTransaction();
        try {
            $batchId = (string) Str::uuid();

            // 2. Buat header Indicator (sesuai field batch_id di migrasi testing)
            $indicator = Indicator::create([
                'batch_id'    => $batchId,
                'periode'     => $request->periode,
                'file_pdf'    => $filePath,
                'dibuka_pada' => now(),
            ]);

            // 3. Simpan hasil nilai per satker
            foreach ($parsedData as $row) {
                $satker = Satker::where('kode_satker', $row['kode_satker'])->first();

                if ($satker) {
                    IndicatorResult::updateOrCreate(
                        [
                            'indicator_id' => $indicator->id,
                            'satker_id'    => $satker->id,
                        ],
                        [
                            'nilai'        => $row['nilai_akhir'],
                        ]
                    );
                }
            }

            // Jika IkpaScoringService kamu memiliki kalkulasi agregat atau peringkat:
            // $this->scoringService->recalculateBatch($indicator->id);

            DB::commit();
            return redirect()->route('admin.indicators.index')->with('success', 'File PDF IKPA Satker berhasil diuraikan dan disimpan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan saat memproses data: ' . $e->getMessage());
        }
    }
}