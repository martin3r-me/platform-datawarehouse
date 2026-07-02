<?php

namespace Platform\Datawarehouse\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Services\StreamImportService;

/**
 * Datei-Upload-Ingestion für manuelle Streams: xlsx/csv hochladen, zu
 * Array-of-assoc-Rows parsen, dann
 *  - onboarding-Stream: Sample stashen → weiter ins Onboarding (Spalten-Mapping),
 *  - aktiver Stream: direkt via StreamImportService importieren.
 * Kein neuer Writer-/Mapping-Code — nutzt die bestehende Pipeline.
 */
class StreamUpload extends Component
{
    use WithFileUploads;

    public DatawarehouseStream $stream;
    public $file;
    public ?string $flash = null;
    public ?string $error = null;

    public function mount(DatawarehouseStream $stream): void
    {
        abort_unless($stream->team_id === Auth::user()->currentTeam->id, 403);
        $this->stream = $stream;
    }

    public function importFile(): void
    {
        $this->flash = null;
        $this->error = null;

        $this->validate([
            'file' => 'required|file|mimes:xlsx,csv,txt|max:20480', // 20 MB
        ]);

        try {
            $rows = $this->parseFile($this->file->getRealPath(), strtolower($this->file->getClientOriginalExtension()));
        } catch (\Throwable $e) {
            $this->error = 'Datei konnte nicht gelesen werden: ' . $e->getMessage();
            return;
        }

        if (empty($rows)) {
            $this->error = 'Keine Datenzeilen gefunden.';
            return;
        }

        // Onboarding: Sample stashen und ins Onboarding leiten (Spalten-Mapping)
        if ($this->stream->status === 'onboarding' || !$this->stream->table_created) {
            $metadata = $this->stream->metadata ?? [];
            $metadata['sample_payload'] = array_slice($rows, 0, 200);
            $metadata['sample_fetched_at'] = now()->toISOString();
            $this->stream->update(['metadata' => $metadata]);

            $this->redirect(route('datawarehouse.stream.onboarding', $this->stream));
            return;
        }

        // Aktiver Stream: direkt importieren (mappt + schreibt via bestehende Pipeline)
        $import = app(StreamImportService::class)->importFromPayload($this->stream, $rows, Auth::id());

        $this->reset('file');
        $this->flash = sprintf(
            'Import %s: %d empfangen, %d importiert, %d übersprungen.',
            $import->status,
            (int) $import->rows_received,
            (int) $import->rows_imported,
            (int) $import->rows_skipped,
        );
    }

    /**
     * Parse an uploaded file into an array of associative rows
     * (header row → keys). Supports xlsx (openspout) and csv/txt (league/csv
     * with a str_getcsv fallback).
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseFile(string $path, string $ext): array
    {
        if ($ext === 'xlsx') {
            return $this->parseXlsx($path);
        }
        return $this->parseCsv($path);
    }

    private function parseXlsx(string $path): array
    {
        if (!class_exists(\OpenSpout\Reader\XLSX\Reader::class)) {
            throw new \RuntimeException('XLSX-Reader (openspout) ist nicht installiert. Bitte als CSV hochladen oder composer update ausführen.');
        }

        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $reader->open($path);

        $headers = [];
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $i => $row) {
                $cells = array_map(function ($c) {
                    $v = $c->getValue();
                    return $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v;
                }, $row->getCells());

                if ($i === 1) {
                    $headers = $this->dedupeHeaders($cells);
                    continue;
                }
                if ($this->isEmptyRow($cells)) {
                    continue;
                }
                $rows[] = $this->combine($headers, $cells);
            }
            break; // nur das erste Sheet
        }
        $reader->close();

        return $rows;
    }

    private function parseCsv(string $path): array
    {
        // Read raw (no header mode) so duplicate header names don't throw and
        // can be de-duplicated positionally like the xlsx path.
        $delimiter = $this->detectDelimiter($path);
        $rows = [];
        $headers = [];
        if (($h = fopen($path, 'r')) !== false) {
            $i = 0;
            while (($cells = fgetcsv($h, 0, $delimiter)) !== false) {
                if ($i === 0) {
                    $headers = $this->dedupeHeaders($cells);
                } elseif (!$this->isEmptyRow($cells)) {
                    $rows[] = $this->combine($headers, $cells);
                }
                $i++;
            }
            fclose($h);
        }
        return $rows;
    }

    /**
     * Trim headers and make duplicates unique positionally (e.g. two "Betrieb"
     * columns → "Betrieb", "Betrieb_2") so no column silently overwrites another.
     */
    private function dedupeHeaders(array $headers): array
    {
        $seen = [];
        $out = [];
        foreach ($headers as $h) {
            $h = trim((string) $h);
            if ($h === '') {
                $out[] = '';
                continue;
            }
            $key = mb_strtolower($h);
            if (isset($seen[$key])) {
                $seen[$key]++;
                $out[] = $h . '_' . $seen[$key];
            } else {
                $seen[$key] = 1;
                $out[] = $h;
            }
        }
        return $out;
    }

    private function detectDelimiter(string $path): string
    {
        $line = '';
        if (($h = fopen($path, 'r')) !== false) {
            $line = (string) fgets($h);
            fclose($h);
        }
        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    private function combine(array $headers, array $cells): array
    {
        $row = [];
        foreach ($headers as $idx => $key) {
            if ($key === '') {
                continue;
            }
            $row[$key] = $cells[$idx] ?? null;
        }
        return $row;
    }

    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $c) {
            if ($c !== null && trim((string) $c) !== '') {
                return false;
            }
        }
        return true;
    }

    public function render()
    {
        return view('datawarehouse::livewire.stream-upload')
            ->layout('platform::layouts.app');
    }
}
