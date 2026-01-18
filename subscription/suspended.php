<?php
if (!defined('ROUTED')) {
    header('Location: ../index.php?page=404');
    exit;
}

require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    header('Location: ' . LOGIN_PAGE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte suspendu - Artisan_ND</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        body { background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%); }
        .box { max-width: 650px; margin: 60px auto; padding: 2.5rem; background: #fff; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .title { font-size: 1.8rem; margin-bottom: 0.8rem; }
        .muted { color: #666; margin-bottom: 1.5rem; }
        .actions { display:flex; gap:12px; flex-wrap:wrap; }
        .btn-support { background: linear-gradient(135deg, #8B4513 0%, #A0522D 100%); color:#fff; border:none; padding: 0.9rem 1.2rem; border-radius: 12px; text-decoration:none; font-weight:600; display:inline-block; }
        .btn-home { background:#f1f1f1; color:#1a1a1a; border:none; padding: 0.9rem 1.2rem; border-radius: 12px; text-decoration:none; font-weight:600; display:inline-block; }
    </style>
</head>
<body>
<main>
    <div class="container">
        <div class="box">
            <div class="title">Compte suspendu</div>
            <div class="muted">
                Votre compte partenaire a été suspendu. Veuillez contacter le support NestCorporation pour réactivation.
            </div>
            <div class="actions">
                <a class="btn-support" href="mailto:contact.nestcorp@gmail.com">Contacter le support</a>
                <a class="btn-home" href="<?php echo url('home'); ?>">Retour à l'accueil</a>
            </div>
        </div>
    </div>
</main>
</body>
</html>
