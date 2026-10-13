// Uji unit untuk fungsi murni de-deduplikasi suara di ai-cam.blade.php.
// Fungsi (normalizeKey, keyTokens, similarity, shortenForSpeech) diambil
// langsung dari sumber blade lalu dijalankan di Node, sehingga yang diuji
// adalah kode asli, bukan salinan.
//
// Jalankan:  node tests/js/speech-dedupe.test.mjs

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const bladePath = join(here, '..', '..', 'resources', 'views', 'ai-cam.blade.php');
const blade = readFileSync(bladePath, 'utf8');

function extractFunction(source, name) {
    const start = source.indexOf(`function ${name}(`);
    if (start === -1) throw new Error(`Fungsi ${name} tidak ditemukan di blade`);
    const braceStart = source.indexOf('{', start);
    let depth = 0;
    for (let i = braceStart; i < source.length; i += 1) {
        const ch = source[i];
        if (ch === '{') depth += 1;
        else if (ch === '}') {
            depth -= 1;
            if (depth === 0) return source.slice(start, i + 1);
        }
    }
    throw new Error(`Kurung tidak seimbang pada fungsi ${name}`);
}

const names = ['normalizeKey', 'keyTokens', 'similarity', 'shortenForSpeech'];
const extracted = names.map((name) => extractFunction(blade, name)).join('\n');
const factory = new Function(`${extracted}\nreturn { ${names.join(', ')} };`);
const api = factory();

let passed = 0;
let failed = 0;

function check(label, condition) {
    if (condition) {
        passed += 1;
        console.log(`PASS ${label}`);
    } else {
        failed += 1;
        console.error(`FAIL ${label}`);
    }
}

check('normalizeKey menghapus tanda baca dan menormalkan spasi',
    api.normalizeKey('Ada 3 Botol!  Air?') === 'ada 3 botol air');

check('keyTokens hanya menyimpan kata bermakna (>3 huruf)',
    JSON.stringify(api.keyTokens('ada tiga botol air')) === JSON.stringify(['tiga', 'botol']));

check('similarity teks identik bernilai 1',
    api.similarity('ada botol air', 'ada botol air') === 1);

check('similarity kalimat hampir sama melewati ambang 0.55',
    api.similarity('ada botol air di meja', 'ada botol air di meja hijau') > 0.55);

check('similarity kalimat agak berbeda tetap di bawah ambang 0.55',
    api.similarity('terlihat botol air', 'ada botol air') < 0.55);

check('similarity teks tak berhubungan bernilai 0',
    api.similarity('kursi kayu', 'botol air') === 0);

const twoSentences = 'Ada botol air di meja. Ada gelas di sampingnya. Kalimat ketiga diabaikan.';
const short = api.shortenForSpeech(twoSentences);
check('shortenForSpeech membatasi maksimal dua kalimat',
    short.includes('botol') && !short.includes('ketiga'));

const longText = 'Kata '.repeat(80).trim();
check('shortenForSpeech memotong teks sangat panjang (<= 200 karakter)',
    api.shortenForSpeech(longText).length <= 200);

console.log(`\n${passed} lulus, ${failed} gagal`);
process.exit(failed === 0 ? 0 : 1);
