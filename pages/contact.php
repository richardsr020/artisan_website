<?php prevent_direct_access(); ?>
<h2 class="page-title">Contactez-nous</h2>

<div class="contact-section">
    <div class="contact-info">
        <div class="contact-card">
            <h3>📧 Email</h3>
            <p><a href="mailto:<?php echo CONTACT_EMAIL; ?>"><?php echo CONTACT_EMAIL; ?></a></p>
        </div>
        <div class="contact-card">
            <h3>📞 Téléphone</h3>
            <p><a href="tel:<?php echo str_replace(' ', '', CONTACT_PHONE); ?>"><?php echo CONTACT_PHONE; ?></a></p>
        </div>
        <div class="contact-card">
            <h3>💼 Support</h3>
            <p>Notre équipe est disponible pour répondre à toutes vos questions.</p>
        </div>
    </div>
</div>

<style>
.contact-section {
    margin-top: 2rem;
}

.contact-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.contact-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(250, 250, 250, 0.9) 100%);
    padding: 2.5rem;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
    backdrop-filter: blur(10px);
    text-align: center;
    transition: all 0.3s ease;
}

.contact-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 50px rgba(139, 69, 19, 0.15);
}

.contact-card h3 {
    color: #8B4513;
    margin-bottom: 1rem;
    font-size: 1.5rem;
    font-weight: 600;
}

.contact-card a {
    color: #8B4513;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s ease;
}

.contact-card a:hover {
    color: #A0522D;
    text-decoration: underline;
}
</style>


