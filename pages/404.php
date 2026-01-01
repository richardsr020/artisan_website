<?php prevent_direct_access(); ?>
<div class="error-404">
    <div class="error-content">
        <h1 class="error-code">404</h1>
        <h2 class="error-title">Page non trouvée</h2>
        <p class="error-message">Désolé, la page que vous recherchez n'existe pas ou a été déplacée.</p>
        <div class="error-actions">
            <a href="<?php echo url('home'); ?>" class="cta-button">Retour à l'accueil</a>
            <a href="<?php echo url('download'); ?>" class="hero-link">Télécharger le logiciel</a>
        </div>
    </div>
</div>

<style>
.error-404 {
    text-align: center;
    padding: 4rem 2rem;
    min-height: 60vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.error-content {
    max-width: 600px;
}

.error-code {
    font-size: 8rem;
    font-weight: 700;
    background: linear-gradient(135deg, #8B4513 0%, #FF8C00 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 1rem;
    line-height: 1;
}

.error-title {
    font-size: 2.5rem;
    color: #1a1a1a;
    margin-bottom: 1rem;
    font-weight: 700;
}

.error-message {
    font-size: 1.2rem;
    color: #666666;
    margin-bottom: 2.5rem;
    line-height: 1.6;
}

.error-actions {
    display: flex;
    gap: 1.5rem;
    justify-content: center;
    flex-wrap: wrap;
}
</style>

