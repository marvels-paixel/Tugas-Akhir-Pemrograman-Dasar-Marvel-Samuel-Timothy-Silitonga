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
    setPesan('error', 'Nama penumpang wajib diisi (maksimal 40 karakter).');

} elseif ($telp === null) {
    setPesan('error', 'Nomor HP tidak valid.');

} elseif (!array_key_exists($kategori, KATEGORI)) {
    setPesan('error', 'Pilih kategori penumpang yang tersedia.');

} else {

if ($otomatis) {
    $posisi = carikursikosongpertama($denah, $kategori === 'anak');

} else {
    $bagian = explode('-', $_POST['kursi'] ?? '');

    $posisi = (count($bagian) === 2 && ctype_digit($bagian[0]) && ctype_digit($bagian[1]))
        ? [(int) $bagian[0], (int) $bagian[1]] : null;
}

if ($posisi === null) {
    setPesan('error', 'Pilih satu kursi pada denah, atau centang pilihan kursi otomatis.');

} elseif (!cekkursikosong($denah, $posisi[0], $posisi[1])) {
    setPesan('error', 'Kursi tidak tersedia. Pilih kursi lain.');

} elseif ($posisi[0] === 0 && $kategori === 'anak') {
    setPesan('error', 'Penumpang anak tidak boleh duduk di kursi depan.');

} else {

$harga = hitungHarga($rute, $armada, $posisi[0], $kategori);
$kode  = buatKodeTiket();

$denah[$posisi[0]][$posisi[1]] = [
    'kode'     => $kode,
    'nama'     => $nama,
    'telp'     => $telp,
    'kategori' => $kategori,
    'harga'    => $harga,
];

$_SESSION['denah'][$kunci] = $denah;
