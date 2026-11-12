<?php
/**
 * The main website's header (logo, menu, one button) and footer, printed on the eAlbum login and album pages so
 * they look like the website's other inner pages. They copy the header and footer of the website's index.html
 * (github.com/yatish6153-hue/devesh-studio-website): when the website's menu or footer changes, change them here too.
 * Styles: album-assets/css/site.css. Mobile menu: album-assets/js/site.js.
 */

// The website's menu, in its order. Services opens a list of the studio's services.
const SITE_MENU = [
    ['about.html', 'About'],
    ['services.html', 'Services'],
    ['portfolio.html', 'Portfolio'],
    ['shop.html', 'Shop'],
    ['ealbum.html', 'eAlbum'],
    ['contact.html', 'Contact'],
    ['account.html', 'Login'],
];

const SITE_SERVICES = [
    ['services.html#weddings', 'Weddings'],
    ['services.html#pre-wedding', 'Pre-wedding'],
    ['services.html#portraits', 'Portraits &amp; fashion'],
    ['services.html#events', 'Events &amp; celebrations'],
    ['services.html#videography', 'Videography'],
    ['services.html#design', 'Design &amp; animation'],
];

/**
 * The header. $button is the button on the right: ['href' => …, 'label' => …, 'icon' => name or ''].
 * eAlbum is marked as the current page.
 */
function site_header(array $button): void
{
    $home = (string) cfg('website_home', 'index.html');
    $studio = (string) cfg('studio_name', 'Devesh Studio');
    $btnIcon = !empty($button['icon']) ? icon((string) $button['icon']) : '';
    ?>
<header class="site-head">
    <nav class="nav-pill" aria-label="Main">
        <a class="logo" href="<?= e($home) ?>"><img src="album-assets/img/logo.svg?v=<?= ASSET_VERSION ?>" alt="<?= e($studio) ?>" width="82" height="52"></a>
        <ul class="nav-links">
            <?php foreach (SITE_MENU as $item): ?>
                <?php if ($item[0] === 'services.html'): ?>
                    <li class="has-drop">
                        <a href="services.html">Services <?= icon('chevs', 'chev') ?></a>
                        <ul class="drop">
                            <?php foreach (SITE_SERVICES as $service): ?>
                                <li><a href="<?= e($service[0]) ?>"><?= $service[1] ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php else: ?>
                    <li><a href="<?= e($item[0]) ?>"<?= $item[0] === 'ealbum.html' ? ' aria-current="page"' : '' ?>><?= e($item[1]) ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
        <a class="pbtn sm head-btn" href="<?= e((string) $button['href']) ?>"><?= $btnIcon ?><span><?= e((string) $button['label']) ?></span></a>
        <button class="menu-btn" type="button" aria-expanded="false" aria-controls="mobile-menu"><?= icon('menu') ?><span class="sr-only">Menu</span></button>
        <div class="mobile-menu" id="mobile-menu" hidden>
            <ul>
                <?php foreach (SITE_MENU as $item): ?>
                    <li><a href="<?= e($item[0]) ?>"<?= $item[0] === 'ealbum.html' ? ' aria-current="page"' : '' ?>><?= e($item[1]) ?></a></li>
                <?php endforeach; ?>
                <li><a class="mm-btn" href="<?= e((string) $button['href']) ?>"><?= $btnIcon ?><?= e((string) $button['label']) ?></a></li>
            </ul>
        </div>
    </nav>
</header>
    <?php
}

/** The website's footer: about the studio, the menu, the services, contact details and social links. */
function site_footer(): void
{
    $home = (string) cfg('website_home', 'index.html');
    $studio = (string) cfg('studio_name', 'Devesh Studio');
    ?>
<footer class="foot">
    <div class="wrap foot-grid">
        <div class="foot-brand">
            <a class="foot-logo" href="<?= e($home) ?>"><img src="album-assets/img/logo.svg?v=<?= ASSET_VERSION ?>" alt="<?= e($studio) ?>" width="82" height="52" loading="lazy"></a>
            <p>A complete design solution: photography, films, design and animation, with prints and frames made to keep.</p>
        </div>
        <nav aria-label="Explore">
            <h3>Explore</h3>
            <ul>
                <?php foreach (SITE_MENU as $item): ?>
                    <li><a href="<?= e($item[0]) ?>"><?= e($item[1]) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <nav aria-label="Services">
            <h3>Services</h3>
            <ul>
                <?php foreach (['Weddings', 'Pre-wedding', 'Portraits', 'Events', 'Videography', 'Design &amp; animation'] as $service): ?>
                    <li><a href="services.html"><?= $service ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div>
            <h3>Contact</h3>
            <ul>
                <li><a href="tel:+919799016153">+91 97990 16153</a></li>
                <li><a href="mailto:devesh.paota@gmail.com">devesh.paota@gmail.com</a></li>
                <li><a href="contact.html">Paota and Jaipur, Rajasthan</a></li>
                <li><a href="contact.html">Studio addresses and map</a></li>
            </ul>
        </div>
    </div>
    <div class="wrap foot-bottom">
        <span>© <?= date('Y') ?> Devesh Digital Studio</span>
        <nav aria-label="Legal and social">
            <a href="privacy.html">Privacy</a>
            <a href="terms.html">Terms</a>
            <a href="<?= e(cfg('instagram')) ?>" target="_blank" rel="noopener">Instagram</a>
            <a href="<?= e(cfg('facebook')) ?>" target="_blank" rel="noopener">Facebook</a>
            <a href="<?= e(cfg('youtube')) ?>" target="_blank" rel="noopener">YouTube</a>
        </nav>
    </div>
</footer>
    <?php
}
