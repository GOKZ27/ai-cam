<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiCamController extends Controller
{
    public function index()
    {
        return view('ai-cam');
    }

    public function detect(Request $request)
    {
        return $this->forwardImage($request, '/detect', 35);
    }

    public function describe(Request $request)
    {
        return $this->forwardImage($request, '/describe', 190);
    }

    public function health()
    {
        try {
            $response = Http::timeout(3)->get($this->baseUrl().'/health');

            return response()->json($response->json() ?? ['status' => 'unknown'], $response->status());
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'Layanan Python tidak dapat dihubungi. Pastikan FastAPI berjalan di port 8001.',
            ], 503);
        }
    }

    private function baseUrl(): string
    {
        return rtrim(config('services.ai_cam.python_url', 'http://127.0.0.1:8001'), '/');
    }

    private function forwardImage(Request $request, string $endpoint, int $timeout)
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $file = $request->file('image');

        try {
            $response = Http::timeout($timeout)
                ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post($this->baseUrl().$endpoint);

            return response()->json($response->json() ?? [
                'message' => 'Layanan Python mengembalikan respons yang tidak valid.',
            ], $response->status());
        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'Layanan Python tidak dapat dihubungi. Pastikan FastAPI berjalan di port 8001.',
            ], 503);
        }
    }
}
