"""Uji unit tanpa jaringan untuk integrasi Gemini di main.py.

Menggunakan unittest (bawaan Python) dan mock. Tidak memanggil API sungguhan,
tidak memerlukan kunci, dan tidak mengirim gambar.

Jalankan:
  .\\.venv\\Scripts\\python.exe -m unittest -v test_gemini_mock
"""

import os
import sys
import types
import unittest
from unittest import mock

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import main  # noqa: E402


class FakeCandidate:
    def __init__(self, text, finish="STOP"):
        part = types.SimpleNamespace(text=text)
        self.content = types.SimpleNamespace(parts=[part])
        self.finish_reason = types.SimpleNamespace(name=finish)


class FakeResponse:
    def __init__(self, text, finish="STOP"):
        self.text = text
        self.candidates = [FakeCandidate(text, finish)]


FAKE_TYPES = types.SimpleNamespace(
    Part=types.SimpleNamespace(from_bytes=lambda **kwargs: ("part", kwargs))
)


class ParseVisionJsonTests(unittest.TestCase):
    def test_valid_json(self):
        result = main.parse_vision_json(
            '{"description":"Ada botol air.",'
            '"main_objects":["botol"],"other_objects":[],"uncertain":false}'
        )
        self.assertTrue(result["structured"])
        self.assertEqual(result["description"], "Ada botol air.")
        self.assertEqual(result["main_objects"], ["botol"])
        self.assertFalse(result["uncertain"])

    def test_code_fenced_json(self):
        result = main.parse_vision_json('```json\n{"description":"Ada kursi."}\n```')
        self.assertTrue(result["structured"])
        self.assertEqual(result["description"], "Ada kursi.")

    def test_invalid_json_falls_back_to_text(self):
        result = main.parse_vision_json("Saya melihat benda, tetapi tidak yakin.")
        self.assertFalse(result["structured"])
        self.assertEqual(result["description"], "Saya melihat benda, tetapi tidak yakin.")
        self.assertEqual(result["main_objects"], [])

    def test_object_list_normalization(self):
        result = main.parse_vision_json(
            '{"description":"x","main_objects":[{"name":"gelas"},'
            '"gelas","",{"label":"botol"}],"uncertain":true}'
        )
        self.assertEqual(result["main_objects"], ["gelas", "botol"])
        self.assertTrue(result["uncertain"])


@unittest.skipUnless(main.GENAI_AVAILABLE, "google-genai belum terpasang")
class CallGeminiTests(unittest.TestCase):
    def setUp(self):
        patcher = mock.patch.multiple(
            main,
            GENAI_AVAILABLE=True,
            GEMINI_API_KEY="kunci-uji",
            GEMINI_MODEL="model-uji",
            GEMINI_JSON_MODE=True,
            GEMINI_RETRIES=0,
            GEMINI_FALLBACK_MODELS=[],
        )
        patcher.start()
        self.addCleanup(patcher.stop)
        types_patch = mock.patch.object(main, "genai_types", FAKE_TYPES)
        types_patch.start()
        self.addCleanup(types_patch.stop)

    def test_returns_structured_result(self):
        response = FakeResponse(
            '{"description":"Ada botol air.","main_objects":["botol"],"uncertain":false}'
        )
        with mock.patch.object(main, "gemini_generate", return_value=response):
            result = main.call_gemini(b"gambar", [], {})
        self.assertEqual(result["description"], "Ada botol air.")
        self.assertEqual(result["main_objects"], ["botol"])
        self.assertTrue(result["structured"])

    def test_plain_text_response(self):
        with mock.patch.object(main, "gemini_generate", return_value=FakeResponse("Hanya teks biasa.")):
            result = main.call_gemini(b"gambar", [], {})
        self.assertEqual(result["description"], "Hanya teks biasa.")
        self.assertFalse(result["structured"])

    def test_json_mode_retried_on_bad_request(self):
        calls = []

        def fake(model, prompt, image_part, json_mode):
            calls.append(json_mode)
            if json_mode:
                raise main.genai_errors.ClientError(
                    code=400, response_json={"error": {"message": "json not supported"}}
                )
            return FakeResponse('{"description":"ok"}')

        with mock.patch.object(main, "gemini_generate", side_effect=fake):
            result = main.call_gemini(b"gambar", [], {})
        self.assertEqual(calls, [True, False])
        self.assertEqual(result["description"], "ok")

    def test_quota_maps_to_429(self):
        def fake(model, prompt, image_part, json_mode):
            raise main.genai_errors.ClientError(
                code=429, response_json={"error": {"message": "quota exceeded"}}
            )

        with mock.patch.object(main, "gemini_generate", side_effect=fake):
            with self.assertRaises(main.VisionServiceError) as raised:
                main.call_gemini(b"gambar", [], {})
        self.assertEqual(raised.exception.status_code, 429)

    def test_invalid_key_maps_to_502(self):
        def fake(model, prompt, image_part, json_mode):
            raise main.genai_errors.ClientError(
                code=403, response_json={"error": {"message": "permission denied"}}
            )

        with mock.patch.object(main, "gemini_generate", side_effect=fake):
            with self.assertRaises(main.VisionServiceError) as raised:
                main.call_gemini(b"gambar", [], {})
        self.assertEqual(raised.exception.status_code, 502)

    def test_safety_block_maps_to_422(self):
        with mock.patch.object(main, "gemini_generate", return_value=FakeResponse("", finish="SAFETY")):
            with self.assertRaises(main.VisionServiceError) as raised:
                main.call_gemini(b"gambar", [], {})
        self.assertEqual(raised.exception.status_code, 422)

    def test_missing_model_is_503(self):
        with mock.patch.object(main, "GEMINI_MODEL", ""):
            with self.assertRaises(main.VisionServiceError) as raised:
                main.call_gemini(b"gambar", [], {})
        self.assertEqual(raised.exception.status_code, 503)

    def test_transient_error_retried_then_succeeds(self):
        calls = []

        def fake(model, prompt, image_part, json_mode):
            calls.append(json_mode)
            if len(calls) == 1:
                raise main.genai_errors.ServerError(
                    code=503, response_json={"error": {"message": "high demand"}}
                )
            return FakeResponse('{"description":"pulih"}')

        with mock.patch.multiple(main, GEMINI_RETRIES=1, GEMINI_RETRY_DELAY=0):
            with mock.patch.object(main, "gemini_generate", side_effect=fake):
                result = main.call_gemini(b"gambar", [], {})
        self.assertEqual(result["description"], "pulih")
        self.assertEqual(calls, [True, True])


    def test_falls_back_to_secondary_model_on_quota(self):
        seen = []

        def fake(model, prompt, image_part, json_mode):
            seen.append(model)
            if model == "model-uji":
                raise main.genai_errors.ClientError(
                    code=429, response_json={"error": {"message": "quota"}}
                )
            return FakeResponse('{"description":"dari lite"}')

        with mock.patch.multiple(main, GEMINI_FALLBACK_MODELS=["lite-uji"]):
            with mock.patch.object(main, "gemini_generate", side_effect=fake):
                result = main.call_gemini(b"gambar", [], {})
        self.assertEqual(seen, ["model-uji", "lite-uji"])
        self.assertEqual(result["description"], "dari lite")
        self.assertEqual(result["model"], "lite-uji")


class ProviderSelectionTests(unittest.TestCase):
    def test_auto_prefers_gemini_when_configured(self):
        with mock.patch.multiple(
            main, AI_VISION_PROVIDER="auto", GENAI_AVAILABLE=True,
            GEMINI_API_KEY="k", GEMINI_MODEL="m",
        ):
            self.assertEqual(main.choose_provider()[0], "gemini")

    def test_auto_uses_ollama_when_not_configured(self):
        with mock.patch.multiple(
            main, AI_VISION_PROVIDER="auto", GENAI_AVAILABLE=True,
            GEMINI_API_KEY="", GEMINI_MODEL="",
        ):
            self.assertEqual(main.choose_provider()[0], "ollama")

    def test_gemini_mode_without_config_falls_back_with_reason(self):
        with mock.patch.multiple(
            main, AI_VISION_PROVIDER="gemini", GENAI_AVAILABLE=True,
            GEMINI_API_KEY="", GEMINI_MODEL="",
        ):
            provider, reason = main.choose_provider()
        self.assertEqual(provider, "ollama")
        self.assertIsNotNone(reason)


if __name__ == "__main__":
    unittest.main(verbosity=2)
