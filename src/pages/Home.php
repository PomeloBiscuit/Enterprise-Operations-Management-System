<?php
require_once __DIR__ . '/../auth.inc.php';
require_once __DIR__ . '/../i18n.inc.php';
if (!can_access_self()) {
    echo "<p style='text-align:center; color:var(--danger-color);'>" . t('common.permission_denied') . "</p>";
    return;
}
?>
<style>
    .home-page { color: var(--text-color); }
    .home-page .home-intro { max-width: 1200px; margin: 0 auto 20px; }
    .home-page .hero-section { position: relative; overflow: hidden; padding: 80px 20px; color: var(--text-color); background: var(--surface-muted-color); text-align: center; }
    .home-page .hero-art { position: absolute; inset: 0; z-index: 0; background: var(--surface-muted-color); }
    .home-page .hero-art::before, .home-page .hero-art::after { position: absolute; content: ''; border: 2px solid var(--border-color); border-radius: 50%; }
    .home-page .hero-art::before { top: -32vw; left: -12vw; width: 48vw; height: 48vw; }
    .home-page .hero-art::after { right: -15vw; bottom: -30vw; width: 42vw; height: 42vw; }
    .home-page .hero-content { position: relative; z-index: 1; }
    .home-page .hero-section h1 { font-size: clamp(2rem, 5vw, 3.5rem); font-weight: bold; }
    .home-page .hero-section p { margin-top: 20px; font-size: clamp(1.1rem, 2.5vw, 1.5rem); }
    .home-page .section { max-width: 1200px; margin: 20px auto; padding: 40px 20px; background: var(--surface-color); }
    .home-page .section h2 { margin-bottom: 40px; color: var(--link-color); font-size: clamp(1.7rem, 4vw, 2.5rem); font-weight: bold; text-align: center; }
    .home-page .services-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .home-page .service-item { padding: 20px; border: 1px solid var(--border-color); background: var(--surface-color); text-align: center; }
    .home-page .home-icon-frame { display: flex; align-items: center; justify-content: center; box-sizing: border-box; flex-shrink: 0; color: var(--link-color); background: var(--surface-muted-color); border: 1px solid var(--border-color); }
    .home-page .service-item .home-icon-frame { width: 240px; height: 240px; max-width: 100%; margin: 0 auto 20px; }
    .home-page .about, .home-page .contact { display: flex; align-items: center; gap: 20px; }
    .home-page .about .home-icon-frame, .home-page .contact .home-icon-frame { width: 150px; height: 150px; }
    .home-page .home-icon { width: 55%; height: 55%; fill: currentColor; }
    .home-page .about p, .home-page .contact p { font-size: 1.2em; line-height: 1.6; }
    /* HTTP/1280 與 HTTP/1024 的正式頁面驗收由 WO-13 瀏覽器量測記錄；此元件以 max-width 與 min-width:0 避免推擠側欄時產生水平捲動。 */
    @media (max-width: 640px) { .home-page .about, .home-page .contact { align-items: flex-start; flex-direction: column; } }
</style>
<div class="home-page">
    <div class="home-intro"><h1><?php echo t('home.greeting', ['name' => $_SESSION['admlogin'] ?? '']); ?></h1><p><?php echo t('home.subtitle'); ?></p></div>
    <section class="hero-section"><div class="hero-art" role="img" aria-label="<?php echo htmlspecialchars(t('home.hero.art_aria')); ?>"></div><div class="hero-content"><h1><?php echo t('home.hero.title'); ?></h1><p><?php echo t('home.hero.subtitle'); ?></p></div></section>
    <section class="section"><h2><?php echo t('home.features.title'); ?></h2><div class="services-container">
        <div class="service-item"><div class="home-icon-frame"><svg class="home-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 0h1v15H0V0zm2.5 12H4V7H2.5v5zM5 15h1.5V4H5v11zm2.5 0H9V9H7.5v6zm2.5 0h1.5V2H10v13zm2.5 0H14v-5h-1.5v5z"/></svg></div><p><?php echo t('home.features.item1'); ?></p></div>
        <div class="service-item"><div class="home-icon-frame"><svg class="home-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M4.5 0A1.5 1.5 0 0 0 3 1.5V3H2a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-1h2a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2h-2V1.5A1.5 1.5 0 0 0 8.5 0h-4zM4 3V1.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5V3H4zm6 2h4a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-2v-1a1 1 0 0 0-1-1H9a1 1 0 0 0-1 1v1H2a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h8v1z"/></svg></div><p><?php echo t('home.features.item2'); ?></p></div>
        <div class="service-item"><div class="home-icon-frame"><svg class="home-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M13 7a3 3 0 1 0-2.49-4.673A4 4 0 0 0 3.5 5.5a3.5 3.5 0 0 0 .5 6.964V15h8v-2.536A3.5 3.5 0 0 0 13 7zM8 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4zm-5.5 4a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zm11 0a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM8 7c2.21 0 4 1.343 4 3v4H4v-4c0-1.657 1.79-3 4-3z"/></svg></div><p><?php echo t('home.features.item3'); ?></p></div>
    </div></section>
    <section class="section"><h2><?php echo t('home.about.title'); ?></h2><div class="about"><div class="home-icon-frame"><svg class="home-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M2 15.5V5l6-4 6 4v10.5a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5M3 6v9h2V9h2v6h2V9h2v6h2V6L8 2.667 3 6z"/></svg></div><p><?php echo t('home.about.body'); ?></p></div></section>
    <section class="section"><h2><?php echo t('home.contact.title'); ?></h2><div class="contact"><div class="home-icon-frame"><svg class="home-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M1.885.511a1.745 1.745 0 0 1 2.61.163l2.01 2.61c.329.428.372 1.01.11 1.48L5.49 6.74a.678.678 0 0 0 .15.82l3.63 3.63a.678.678 0 0 0 .82.15l1.976-1.125a1.745 1.745 0 0 1 1.48.11l2.61 2.01c.707.545.78 1.58.163 2.61l-.73 1.217C14.93 17.19 13.66 17.66 12.51 17.16c-2.31-1.006-4.8-2.97-7.15-5.32C3.01 9.49 1.046 7 0.04 4.69c-.5-1.15-.03-2.42 1-3.08z"/></svg></div><p><?php echo t('home.contact.body'); ?></p></div></section>
</div>
