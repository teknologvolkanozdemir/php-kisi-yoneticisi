<?php
session_start();
require_once "db.php";

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

function csrf_ok(): bool
{
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

$hata = "";
$basari = "";

function validate(string $ad, string $email, string $telefon): string
{
    if ($ad === "" || $email === "") {
        return "Ad Soyad ve Email zorunludur.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Geçerli bir email giriniz.";
    }
    if (mb_strlen($ad) > 120 || mb_strlen($email) > 150 || mb_strlen($telefon) > 30) {
        return "Alan uzunluğu sınırı aşıldı.";
    }
    return "";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_soyad = trim($_POST['ad_soyad'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telefon  = trim($_POST['telefon'] ?? '');
    $id       = (int)($_POST['id'] ?? 0);

    if (!csrf_ok()) {
        $hata = "Geçersiz istek.";
    } elseif (isset($_POST['sil'])) {
        $stmt = $pdo->prepare("DELETE FROM kisiler WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php?durum=silindi");
        exit;
    } elseif (isset($_POST['ekle']) || isset($_POST['guncelle'])) {
        $guncelle = isset($_POST['guncelle']);
        $hata = validate($ad_soyad, $email, $telefon);
        if ($hata === "" && $guncelle && $id <= 0) {
            $hata = "Geçersiz kayıt.";
        }
        if ($hata === "") {
            try {
                if ($guncelle) {
                    $stmt = $pdo->prepare("UPDATE kisiler SET ad_soyad = ?, email = ?, telefon = ? WHERE id = ?");
                    $stmt->execute([$ad_soyad, $email, $telefon, $id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO kisiler (ad_soyad, email, telefon) VALUES (?, ?, ?)");
                    $stmt->execute([$ad_soyad, $email, $telefon]);
                }
                header("Location: index.php?durum=" . ($guncelle ? "guncellendi" : "eklendi"));
                exit;
            } catch (PDOException $ex) {
                $hata = ($ex->getCode() === '23000')
                    ? "Bu email zaten kayıtlı."
                    : "Veritabanı hatası oluştu.";
            }
        }
        if ($guncelle) {
            $duzenlenecek = ['id' => $id, 'ad_soyad' => $ad_soyad, 'email' => $email, 'telefon' => $telefon];
        }
    }
}

if (!isset($duzenlenecek)) {
    $duzenlenecek = null;
    if (isset($_GET['duzenle'])) {
        $stmt = $pdo->prepare("SELECT * FROM kisiler WHERE id = ?");
        $stmt->execute([(int)$_GET['duzenle']]);
        $duzenlenecek = $stmt->fetch() ?: null;
    }
}

$kisiler = $pdo->query("SELECT * FROM kisiler ORDER BY id DESC")->fetchAll();

$mesajlar = [
    'eklendi'     => 'Kişi eklendi.',
    'guncellendi' => 'Kişi güncellendi.',
    'silindi'     => 'Kişi silindi.',
];
if (isset($_GET['durum']) && isset($mesajlar[$_GET['durum']])) {
    $basari = $mesajlar[$_GET['durum']];
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>PHP Kişi Yöneticisi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background: #f7f7f7; }
        .kutu { background: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        input { padding: 8px; margin: 5px 0; width: 280px; }
        button { padding: 8px 12px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #efefef; }
        .hata { color: #b30000; margin-bottom: 10px; }
        .basari { color: #007a1f; margin-bottom: 10px; }
        form.satir { display: inline; }
        a { text-decoration: none; }
    </style>
</head>
<body>

<div class="kutu">
    <h2><?= $duzenlenecek ? "Kişi Düzenle" : "Yeni Kişi Ekle" ?></h2>

    <?php if ($hata): ?><div class="hata"><?= e($hata) ?></div><?php endif; ?>
    <?php if ($basari): ?><div class="basari"><?= e($basari) ?></div><?php endif; ?>

    <form method="post" action="index.php">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= e($duzenlenecek['id'] ?? '') ?>">
        <div><input type="text" name="ad_soyad" placeholder="Ad Soyad" required maxlength="120"
                    value="<?= e($duzenlenecek['ad_soyad'] ?? '') ?>"></div>
        <div><input type="email" name="email" placeholder="Email" required maxlength="150"
                    value="<?= e($duzenlenecek['email'] ?? '') ?>"></div>
        <div><input type="text" name="telefon" placeholder="Telefon" maxlength="30"
                    value="<?= e($duzenlenecek['telefon'] ?? '') ?>"></div>

        <?php if ($duzenlenecek): ?>
            <button type="submit" name="guncelle" value="1">Güncelle</button>
            <a href="index.php">İptal</a>
        <?php else: ?>
            <button type="submit" name="ekle" value="1">Ekle</button>
        <?php endif; ?>
    </form>
</div>

<div class="kutu">
    <h2>Kişi Listesi</h2>
    <table>
        <tr><th>ID</th><th>Ad Soyad</th><th>Email</th><th>Telefon</th><th>İşlem</th></tr>
        <?php foreach ($kisiler as $k): ?>
            <tr>
                <td><?= e($k['id']) ?></td>
                <td><?= e($k['ad_soyad']) ?></td>
                <td><?= e($k['email']) ?></td>
                <td><?= e($k['telefon']) ?></td>
                <td>
                    <a href="?duzenle=<?= (int)$k['id'] ?>">Düzenle</a> |
                    <form class="satir" method="post" action="index.php" onsubmit="return confirm('Silinsin mi?')">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                        <button type="submit" name="sil" value="1">Sil</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

</body>
</html>
