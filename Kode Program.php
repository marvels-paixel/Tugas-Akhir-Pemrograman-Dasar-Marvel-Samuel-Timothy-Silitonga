<?php

session start();

const RUTE = [
    ['asal' => 'Semarang', 'tujuan' => 'Yogyakarta', 'harga' => 110000, 'armada' => 'hiace'],
    ['asal' => 'Semarang', 'tujuan' => 'Solo', 'harga' => 75000, 'armada' => 'hiace'],
    ['asal' => 'Semarang', 'tujuan' => 'Surabaya', 'harga' => 150000, 'armada' => 'busmini'],
    ['asal' => 'Semarang', 'tujuan' => 'Bandung', 'harga' => 210000, 'armada' => 'busmini'],
];
const JAM_BERANGKAT = ['05:00', '08:00', '13:00', '19:00'];

const KATEGORI = [
    'dewasa'  => 'Dewasa',
    'pelajar' => 'Pelajar / mahasiswa (diskon 10%)',
    'lansia'  => 'Lansia (diskon 15%)',
    'anak'    => 'Anak 3-11 tahun (diskon 25%)',
];
const BIAYA_KURSI_DEPAN = 15000;

abstract class Armada
{
    protected $nama;
    protected $baris;
    protected $kolom;

    public function __construct(string $nama, int $baris, int $kolom)
    {
        $this->nama = $nama;
        $this->baris = $baris;
        $this->kolom = $kolom;
    }

    abstract public function faktortarif(): float;
    abstract public function kolomlorong(): int;

    public function getnama(): string { return $this->nama; }
    public function getbaris(): int { return $this->baris; }
    public function getkolom(): int { return $this->kolom; }
    public function getkapasitas(): int { return $this->baris * $this->kolom; }
}

class Hiace extends Armada
{
    public function __construct()
    {
        parent::__construct('Hiace Commuter', 5, 3);
    }

    public function faktortarif(): float { return 1.0; }

    public function kolomlorong(): int { return -1; }
}

class BusMini extends Armada
{
    public function __construct()
    {
        parent::__construct('Bus Mini Executive', 6, 4);
    }

    public function faktortarif(): float { return 0.9; }

    public function kolomlorong(): int { return 2; }
}

function e($teks) : string
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function rupiah (int $angka): string 
{
    return 'Rp' . number_format($angka, 0, ',', '.');
}

function buatarmada(string $jenis): Armada
{
    return $jenis === 'busmini' ? new BusMini() : new Hiace();
}

function labelkursi(int $baris, int $kolom): string 
{
    return ($baris + 1) . chr(65 + $kolom);
}

function buatdenahkosong(Armada $a): array
{
    $denah = [];

    for ($baris = 0; $baris < $a->getBaris(); $baris++) {
        for ($kolom = 0; $kolom < $a->getKolom(); $kolom++) {
            $denah[$baris][$kolom] = null;
        }
    }

    return $denah;
}

function ambildenah(string $kunci, Armada $a): array
{
    if (!isset($_SESSION['denah'][$kunci])) {
        $_SESSION['denah'][$kunci] = buatDenahKosong($a);
    }

    return $_SESSION['denah'][$kunci];
}

function cekkursikosong(array $denah, int $baris, int $kolom): bool
{
    return array_key_exists($baris, $denah)
        && array_key_exists($kolom, $denah[$baris])
        && $denah[$baris][$kolom] === null;
}

function carikursikosongpertama(array $denah, bool $lewatiDepan): ?array
{
    $baris = $lewatiDepan ? 1 : 0;

    while ($baris < count($denah)) {
        for ($kolom = 0; $kolom < count($denah[$baris]); $kolom++) {
            if (cekKursiKosong($denah, $baris, $kolom)) {
                return [$baris, $kolom];
            }
        }

        $b++;
    }

    return null;
}

function hitungharga(array $rute, Armada $a, int $baris, string $kategori): int
{
    $harga = $rute['harga'] * $a->faktorTarif();

    if ($baris === 0) {
        $harga += BIAYA_KURSI_DEPAN;
    }

    switch ($kategori) {
        case 'pelajar': $diskon = 0.10; break;
        case 'lansia':  $diskon = 0.15; break;
        case 'anak':    $diskon = 0.25; break;
        default:        $diskon = 0.0; break;
    }

    $harga = $harga * (1 - $diskon);

    return (int) (round($harga / 1000) * 1000);
}

function hitungterisi(array $denah): int
{
    $terisi = 0;

    for ($baris = 0; $baris < count($denah); $baris++) {
        for ($kolom = 0; $kolom < count($denah[$baris]); $kolom++) {
            if ($denah[$baris][$kolom] !== null) {
                $terisi++;
            }
        }
    }

    return $terisi;
}

function bersihkantelp(string $mentah): ?string
{
    $telp = preg_replace('/[\s().-]/', '', $mentah);

    return preg_match('/^\+?\d{6,15}$/', $telp) ? $telp : null;
}

function buatkodetiket(): string
{
    return 'TS-' . strtoupper(substr(md5(uniqid('', true)), 0, 5));
}

function setpesan(string $tipe, string $teks): void
{
    $_SESSION['pesan'] = ['tipe' => $tipe, 'teks' => $teks];
}

$idxRute = (int) ($_REQUEST['rute'] ?? 0);
$idxJam  = (int) ($_REQUEST['jam'] ?? 0);

if (!isset(RUTE[$idxRute])) { $idxRute = 0; }
if (!isset(JAM_BERANGKAT[$idxJam])) { $idxJam = 0; }

$rute    = RUTE[$idxRute];
$armada  = buatarmada($rute['armada']);
$kunci   = $idxRute . '-' . $idxJam;
$denah   = ambildenah($kunci, $armada);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

if ($aksi === 'pesan') {
    $nama     = trim($_POST['nama'] ?? '');
    $telp     = bersihkanTelp($_POST['telp'] ?? '');
    $kategori = $_POST['kategori'] ?? '';
    $otomatis = isset($_POST['otomatis']);

if ($nama === '' || mb_strlen($nama) > 40) {
    setpesan('error', 'Nama penumpang wajib diisi (maksimal 40 karakter).');

} elseif ($telp === null) {
    setpesan('error', 'Nomor HP tidak valid.');

} elseif (!array_key_exists($kategori, KATEGORI)) {
    setpesan('error', 'Pilih kategori penumpang yang tersedia.');

} else {

if ($otomatis) {
    $posisi = carikursikosongpertama($denah, $kategori === 'anak');

} else {
    $bagian = explode('-', $_POST['kursi'] ?? '');

    $posisi = (count($bagian) === 2 && ctype_digit($bagian[0]) && ctype_digit($bagian[1]))
        ? [(int) $bagian[0], (int) $bagian[1]] : null;
}

if ($posisi === null) {
    setpesan('error', 'Pilih satu kursi pada denah, atau centang pilihan kursi otomatis.');

} elseif (!cekkursikosong($denah, $posisi[0], $posisi[1])) {
    setpesan('error', 'Kursi tidak tersedia. Pilih kursi lain.');

} elseif ($posisi[0] === 0 && $kategori === 'anak') {
    setpesan('error', 'Penumpang anak tidak boleh duduk di kursi depan.');

} else {

$harga = hitungharga($rute, $armada, $posisi[0], $kategori);
$kode  = buatokdetiket();

$denah[$posisi[0]][$posisi[1]] = [
    'kode'     => $kode,
    'nama'     => $nama,
    'telp'     => $telp,
    'kategori' => $kategori,
    'harga'    => $harga,
];

$_SESSION['denah'][$kunci] = $denah;

setpesan('ok', sprintf(
    'Tiket %s: %s duduk di kursi %s, harga %s.',
    $kode, $nama, labelkursi($posisi[0], $posisi[1]), rupiah($harga)
));

} elseif ($aksi === 'batal') {
    $tr = (int) ($_POST['tr'] ?? -1);
    $tj = (int) ($_POST['tj'] ?? -1);
    $baris  = (int) ($_POST['baris'] ?? -1);
    $kolom  = (int) ($_POST['kolom'] ?? -1);

if (isset(RUTE[$tr]) && isset(JAM_BERANGKAT[$tj])) {
    $kunciBatal = $tr . '-' . $tj;

    $dn = ambildenah($kunciBatal, buatarmada(RUTE[$tr]['armada']));

    if (isset($dn[$baris][$kolom]) && $dn[$baris][$kolom] !== null) {
        $kode = $dn[$baris][$kolom]['kode'];

        $dn[$baris][$kolom] = null;

        $_SESSION['denah'][$kunciBatal] = $dn;

        setpesan('ok', "Tiket $kode dibatalkan, kursi " . labelkursi($baris, $kolom) . ' kosong kembali.');
    }
}

header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . "?rute=$idxRute&jam=$idxJam");
exit;

$pesan = $_SESSION['pesan'] ?? null;
unset($_SESSION['pesan']);

$statTrip = hitungterisi($denah); 

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TravelSeat - Pemesanan Kursi Travel Online</title>

<style>
    :root {
    --night: #0e1626; 
    --panel: #16223a; 
    --panel2: #1c2b48; 
    --line: #2b3c5e;
    --text: #e8edf6; 
    --muted: #9fb0cc; 
    --amber: #ffb703; 
    --teal: #35d0ba;
    --taken: #2a3550; 
    --err: #ff8a80; 
    --ok: #5eead4;

    --display: "Times New Roman", Times, serif;
}
* {box-sizing: border-box;}

body { 
    margin: 0; 
    background: var(--night); 
    color: var(--text);
    font: 16px/1.5 "Times New Roman", system-ui, sans-serif; 
}

a { color: inherit; text-decoration: none; }

:focus-visible {  outline: 3px solid var(--amber);  outline-offset: 2px; }

.berangkat { 
    padding: 22px 5vw 0; 
    background: linear-gradient(#0a111e, var(--night)); 
    border-bottom: 1px solid var(--line); 
}

.judul { 
    margin: 0; 
    font: 700 2.4rem/1 var(--display); 
    letter-spacing: .02em; 
}

.subjudul { 
    display: block; 
    margin-top: 6px; 
    font: 400 .95rem/1.3 "Segoe UI", sans-serif; 
    color: var(--muted); 
    letter-spacing: 0; 
}

.rute { 
    display: flex; 
    gap: 8px; 
    margin-top: 20px; 
    overflow-x: auto; 
    padding-bottom: 2px; 
}

.tombolrute {  
    flex: 0 0 auto; 
    padding: 10px 18px; 
    border: 1px solid var(--line); 
    border-bottom: 0;
    border-radius: 8px 8px 0 0; 
    background: var(--panel); 
    color: var(--muted); 
    font: 600 1.05rem var(--display); 
}

.hargadirute { 
    display: block; 
    font: 400 .8rem "Times New Roman", sans-serif; 
}

.ruteyanglagidipilih { 
    background: var(--amber); 
    border-color: var(--amber); 
    color: #1a1300; 
}

.ruteyanglagidipilih2 { 
    color: #4a3600; 
}

.jam { 
    display: flex; 
    gap: 10px; 
    padding: 14px 5vw; 
    background: var(--panel); 
    border-bottom: 1px solid var(--line); 
    overflow-x: auto; 
}

.tomboljam { 
    flex: 0 0 auto; 
    min-width: 104px; 
    padding: 8px 14px; 
    border: 1px solid var(--line); 
    border-radius: 8px; 
    background: var(--night); 
}

.tulisanjam { 
    display: block; 
    font: 700 1.5rem/1.1 var(--display); 
}

.informasijumlahkursi { 
    font-size: .8rem; 
    color: var(--muted); 
}

</style>

