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
    .home-page .map-frame { aspect-ratio: 16 / 9; overflow: hidden; border: 1px solid var(--border-color); background: var(--surface-muted-color); }
    .home-page .map-frame iframe { display: block; width: 100%; height: 100%; border: 0; }
    /* HTTP/1280 與 HTTP/1024 的正式頁面驗收由 WO-13 瀏覽器量測記錄；此元件以 max-width 與 min-width:0 避免推擠側欄時產生水平捲動。 */
    @media (max-width: 640px) { .home-page .about, .home-page .contact { align-items: flex-start; flex-direction: column; } }
</style>
<div class="home-page">
    <div class="home-intro"><h1><?php echo t('home.greeting', ['name' => $_SESSION['admlogin'] ?? '']); ?></h1><p><?php echo t('home.subtitle'); ?></p></div>
    <section class="hero-section"><div class="hero-art" role="img" aria-label="<?php echo htmlspecialchars(t('home.hero.art_aria')); ?>"></div><div class="hero-content"><h1><?php echo t('home.hero.title'); ?></h1><p><?php echo t('home.hero.subtitle'); ?></p></div></section>
    <section class="section"><h2><?php echo t('home.features.title'); ?></h2><div class="services-container">
        <div class="service-item"><div class="home-icon-frame"><svg class="home-icon" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 4a.5.5 0 0 1 .5.5V6a.5.5 0 0 1-1 0V4.5A.5.5 0 0 1 8 4M3.732 5.732a.5.5 0 0 1 .707 0l.915.914a.5.5 0 1 1-.708.708l-.914-.915a.5.5 0 0 1 0-.707M2 10a.5.5 0 0 1 .5-.5h1.586a.5.5 0 0 1 0 1H2.5A.5.5 0 0 1 2 10m9.5 0a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 0 1H12a.5.5 0 0 1-.5-.5m.754-4.246a.39.39 0 0 0-.527-.02L7.547 9.31a.91.91 0 1 0 1.302 1.258l3.434-4.297a.39.39 0 0 0-.029-.518z"/><path fill-rule="evenodd" d="M0 10a8 8 0 1 1 15.547 2.661c-.442 1.253-1.845 1.602-2.932 1.25C11.309 13.488 9.475 13 8 13c-1.474 0-3.31.488-4.615.911-1.087.352-2.49.003-2.932-1.25A8 8 0 0 1 0 10m8-7a7 7 0 0 0-6.603 9.329c.203.575.923.876 1.68.63C4.397 12.533 6.358 12 8 12s3.604.532 4.923.96c.757.245 1.477-.056 1.68-.631A7 7 0 0 0 8 3"/></svg></div><p><?php echo t('home.features.item1'); ?></p></div>
        <div class="service-item"><div class="home-icon-frame"><svg class="home-icon" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5M3.854 2.146a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708L2 3.293l1.146-1.147a.5.5 0 0 1 .708 0m0 4a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708L2 7.293l1.146-1.147a.5.5 0 0 1 .708 0m0 4a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0"/><path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41m-3 2h1.1a4 4 0 0 1-1.5 2.182.5.5 0 1 1-.77-.636A3 3 0 0 0 8.534 9"/><path d="M12 1a3 3 0 1 1-2.5 4.66.5.5 0 0 1 .77-.64A2 2 0 1 0 12 2v1.5a.5.5 0 0 1-1 0V1z"/></svg></div><p><?php echo t('home.features.item2'); ?></p></div>
        <div class="service-item"><div class="home-icon-frame"><svg class="home-icon" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M5 7a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5m0-4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3m4.5 11H.5s-.5 0-.5-.5S.5 9 5 9c1.305 0 2.32.38 3.08.9l-.72.88C6.76 10.35 6.04 10 5 10c-3.4 0-3.96 2.05-4 3h8.5z"/><path fill-rule="evenodd" d="M8 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0V4.9l-3.613 4.417a.5.5 0 0 1-.74.037L5.06 6.767 3.404 9.044a.5.5 0 0 1-.808-.588l2-2.75a.5.5 0 0 1 .758-.06l2.609 2.61L11.445 4H8.5a.5.5 0 0 1-.5-.5"/></svg></div><p><?php echo t('home.features.item3'); ?></p></div>
    </div></section>
    <section class="section"><h2><?php echo t('home.about.title'); ?></h2><div class="about"><div class="home-icon-frame"><svg class="home-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M2 15.5V5l6-4 6 4v10.5a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5M3 6v9h2V9h2v6h2V9h2v6h2V6L8 2.667 3 6z"/></svg></div><p><?php echo t('home.about.body'); ?></p></div></section>
    <section class="section"><h2><?php echo t('home.location.title'); ?></h2><div class="map-frame"><iframe loading="lazy" title="<?php echo htmlspecialchars(t('home.location.map_title')); ?>" src="https://www.openstreetmap.org/export/embed.html?bbox=7.63861%2C45.95639%2C7.67861%2C45.99639&amp;layer=mapnik&amp;marker=45.97639%2C7.65861"></iframe></div></section>
    <section class="section"><h2><?php echo t('home.contact.title'); ?></h2><div class="contact"><div class="home-icon-frame"><svg class="home-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M1.885.511a1.745 1.745 0 0 1 2.61.163l2.01 2.61c.329.428.372 1.01.11 1.48L5.49 6.74a.678.678 0 0 0 .15.82l3.63 3.63a.678.678 0 0 0 .82.15l1.976-1.125a1.745 1.745 0 0 1 1.48.11l2.61 2.01c.707.545.78 1.58.163 2.61l-.73 1.217C14.93 17.19 13.66 17.66 12.51 17.16c-2.31-1.006-4.8-2.97-7.15-5.32C3.01 9.49 1.046 7 0.04 4.69c-.5-1.15-.03-2.42 1-3.08z"/></svg></div><p><?php echo t('home.contact.body'); ?></p></div></section>
</div>
