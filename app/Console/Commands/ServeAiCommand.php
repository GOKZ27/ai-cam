<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Throwable;

class ServeAiCommand extends Command
{
    protected $signature = 'ai:serve
                            {--host=127.0.0.1 : Host layanan AI}
                            {--port=8001 : Port layanan AI}';

    protected $description = 'Jalankan layanan AI Python (FastAPI: YOLO + deskripsi)';

    /** @var array<int, string> */
    protected $aliases = ['ai'];

    public function handle(): int
    {
        $directory = base_path('python-service');

        if (! is_dir($directory)) {
            $this->components->error('Folder python-service tidak ditemukan.');

            return self::FAILURE;
        }

        $python = $this->findPython($directory);

        if ($python === null) {
            $this->components->error('Virtual environment Python belum siap.');
            $this->line('  Sekali saja, jalankan dari folder python-service:');
            $this->line('    python -m venv .venv');
            $this->line('    .venv\\Scripts\\python.exe -m pip install -r requirements.txt');

            return self::FAILURE;
        }

        $this->ensureEnvFile($directory);

        $host = (string) $this->option('host');
        $port = (string) $this->option('port');

        $this->components->info("Layanan AI berjalan di http://{$host}:{$port}");
        $this->line('  Jendela ini harus tetap terbuka. Tekan Ctrl+C untuk berhenti.');
        $this->newLine();

        $process = new Process(
            [$python, '-m', 'uvicorn', 'main:app', '--host', $host, '--port', $port],
            $directory,
        );
        $process->setTimeout(null);

        try {
            $process->run(function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });
        } catch (Throwable $e) {
            $this->components->error('Gagal menjalankan layanan AI: '.$e->getMessage());

            return self::FAILURE;
        }

        $exitCode = $process->getExitCode();

        if ($exitCode !== 0) {
            $this->newLine();
            $this->components->error("Layanan AI berhenti dengan kode {$exitCode}. Port {$port} mungkin sudah dipakai.");
        }

        return $exitCode ?? self::SUCCESS;
    }

    private function findPython(string $directory): ?string
    {
        $candidates = [
            $directory.DIRECTORY_SEPARATOR.'.venv'.DIRECTORY_SEPARATOR.'Scripts'.DIRECTORY_SEPARATOR.'python.exe',
            $directory.DIRECTORY_SEPARATOR.'.venv'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'python',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function ensureEnvFile(string $directory): void
    {
        $env = $directory.DIRECTORY_SEPARATOR.'.env';
        $example = $directory.DIRECTORY_SEPARATOR.'.env.example';

        if (is_file($env) || ! is_file($example)) {
            return;
        }

        if (@copy($example, $env)) {
            $this->components->warn('python-service/.env dibuat dari .env.example. Isi GEMINI_API_KEY bila memakai Gemini.');
        }
    }
}
