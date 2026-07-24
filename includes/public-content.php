<?php

declare(strict_types=1);

function public_field(array $entity, string $field, string $sectionKey, string $contentKey = ''): void
{
    $value = (string) ($entity['data'][$field] ?? '');
    echo '<span' . cms_edit_attrs($contentKey === '' ? 'section' : 'item', (int) $entity['id'], $sectionKey, $contentKey ?: $sectionKey, $field) . '>'
        . auth_escape($value) . '</span>';
}

function public_image(array $entity, string $class = 'img-fluid', bool $priority = false, string $sectionKey = ''): void
{
    $media = $entity['media'] ?? null;
    if (!$media) {
        return;
    }
    printf(
        '<img src="%s" width="%d" height="%d" alt="%s" class="%s" decoding="async"%s%s>',
        auth_escape(cms_media_url($media, 'large')),
        (int) $media['width'],
        (int) $media['height'],
        auth_escape($media['alt_text']),
        auth_escape($class),
        $priority ? ' fetchpriority="high"' : ' loading="lazy"',
        cms_edit_attrs(isset($entity['item_key']) ? 'item' : 'section', (int) $entity['id'], $sectionKey ?: (string) ($entity['section_key'] ?? ''), (string) ($entity['item_key'] ?? $entity['section_key'] ?? ''), 'media')
    );
}

function public_section_title(array $section): void
{
    $key = (string) $section['section_key'];
    echo '<div class="container section-title" data-aos="fade-up"><h2>';
    public_field($section, 'title', $key);
    echo '</h2><p>';
    public_field($section, 'subtitle', $key);
    echo '</p></div>';
}

function public_render_section(array $section): void
{
    $key = (string) $section['section_key'];
    $type = (string) $section['component_type'];
    $items = $section['items'];
    if ($type === 'hero') {
        echo '<section id="hero" class="hero section dark-background">';
        public_image($section, 'sietelsa-image-cover', true);
        echo '<div class="container text-center" data-aos="fade-up"><div class="row justify-content-center"><div class="col-lg-8"><h2>';
        public_field($section, 'title', $key);
        echo '</h2><p>';
        public_field($section, 'subtitle', $key);
        echo '</p><a class="btn-get-started" href="' . auth_escape(cms_clean_url($section['data']['button_url'] ?? '#nosotros')) . '">';
        public_field($section, 'button_text', $key);
        echo '</a></div></div></div></section>';
        return;
    }
    if ($type === 'about') {
        echo '<section id="nosotros" class="about section">';
        public_section_title($section);
        echo '<div class="container"><div class="row gy-3"><div class="col-lg-6" data-aos="fade-up">';
        public_image($section, 'img-fluid sietelsa-image-cover');
        echo '</div><div class="col-lg-6 d-flex flex-column justify-content-center"><div class="about-content ps-0 ps-lg-3"><h3>';
        public_field($section, 'heading', $key);
        echo '</h3><p class="fst-italic">';
        public_field($section, 'lead', $key);
        echo '</p><ul>';
        foreach ($items as $item) {
            echo '<li><i class="bi ' . auth_escape($item['data']['icon'] ?? 'bi-check-circle') . '"></i><div><h4>';
            public_field($item, 'title', $key, $item['item_key']);
            echo '</h4><p>';
            public_field($item, 'content', $key, $item['item_key']);
            echo '</p></div></li>';
        }
        echo '</ul><p>';
        public_field($section, 'content', $key);
        echo '</p></div></div></div></div></section>';
        return;
    }
    if ($type === 'cards') {
        echo '<section id="proyectos" class="cards section light-background"><div class="container">';
        public_section_title($section);
        echo '<div class="row no-gutters">';
        foreach ($items as $item) {
            echo '<div class="col-lg-4 col-md-6 card" data-aos="fade-up"><span>';
            public_field($item, 'number', $key, $item['item_key']);
            echo '</span><h4>';
            public_field($item, 'title', $key, $item['item_key']);
            echo '</h4><p>';
            public_field($item, 'content', $key, $item['item_key']);
            echo '</p></div>';
        }
        echo '</div></div></section>';
        return;
    }
    if ($type === 'tabs') {
        echo '<section id="caracteristicas" class="tabs section"><div class="container"><div class="row justify-content-between"><div class="col-lg-5"><ul class="nav nav-tabs">';
        foreach ($items as $index => $item) {
            echo '<li class="nav-item"><a class="nav-link ' . ($index === 0 ? 'active show' : '') . '" data-bs-toggle="tab" data-bs-target="#cms-tab-' . (int) $item['id'] . '"><i class="bi ' . auth_escape($item['data']['icon'] ?? 'bi-check') . '"></i><div><h4>';
            public_field($item, 'title', $key, $item['item_key']);
            echo '</h4><p>';
            public_field($item, 'content', $key, $item['item_key']);
            echo '</p></div></a></li>';
        }
        echo '</ul></div><div class="col-lg-6"><div class="tab-content">';
        foreach ($items as $index => $item) {
            echo '<div class="tab-pane fade ' . ($index === 0 ? 'active show' : '') . '" id="cms-tab-' . (int) $item['id'] . '">';
            public_image($item, 'img-fluid', false, $key);
            echo '</div>';
        }
        echo '</div></div></div></div></section>';
        return;
    }

    if (in_array($type, ['services', 'portfolio', 'team', 'testimonials', 'faq'], true)) {
        $sectionId = $type === 'services' ? 'servicios' : ($type === 'portfolio' ? 'portafolio' : $key);
        echo '<section id="' . auth_escape($sectionId) . '" class="' . auth_escape($type) . ' section' . ($type === 'services' ? ' light-background' : '') . '">';
        public_section_title($section);
        echo '<div class="container"><div class="row gy-4">';
        foreach ($items as $item) {
            if ($type === 'services') {
                $detailPath = cms_clean_url($item['data']['url'] ?? 'src/website/service-details.php');
                $detailUrl = app_url($detailPath) . (str_contains($detailPath, '?') ? '&' : '?') . 'id=' . (int) $item['id'];
                echo '<div class="col-lg-4 col-md-6"><div class="service-item item-' . auth_escape($item['data']['color'] ?? 'cyan') . ' position-relative"><i class="bi ' . auth_escape($item['data']['icon'] ?? 'bi-activity') . ' icon"></i><a href="' . auth_escape($detailUrl) . '" class="stretched-link"><h3>';
                public_field($item, 'title', $key, $item['item_key']);
                echo '</h3></a><p>';
                public_field($item, 'content', $key, $item['item_key']);
                echo '</p></div></div>';
            } elseif ($type === 'portfolio') {
                echo '<div class="col-lg-4 col-md-6 portfolio-item"><div class="portfolio-content h-100">';
                public_image($item, 'img-fluid', false, $key);
                echo '<div class="portfolio-info"><h4>';
                public_field($item, 'title', $key, $item['item_key']);
                echo '</h4><a href="' . auth_escape(cms_media_url($item['media'])) . '" class="glightbox preview-link"><i class="bi bi-zoom-in"></i></a><a href="' . auth_escape(app_url('src/website/portfolio-details.php?id=' . (int) $item['id'])) . '" class="details-link"><i class="bi bi-link-45deg"></i></a></div></div></div>';
            } elseif ($type === 'team') {
                echo '<div class="col-lg-3 col-md-6"><div class="member">';
                public_image($item, 'img-fluid', false, $key);
                echo '<div class="member-info"><h4>';
                public_field($item, 'title', $key, $item['item_key']);
                echo '</h4><span>';
                public_field($item, 'subtitle', $key, $item['item_key']);
                echo '</span></div></div></div>';
            } elseif ($type === 'testimonials') {
                echo '<div class="col-lg-4"><div class="testimonial-item"><p><i class="bi bi-quote quote-icon-left"></i>';
                public_field($item, 'content', $key, $item['item_key']);
                echo '<i class="bi bi-quote quote-icon-right"></i></p>';
                public_image($item, 'testimonial-img', false, $key);
                echo '<h3>';
                public_field($item, 'title', $key, $item['item_key']);
                echo '</h3><h4>';
                public_field($item, 'subtitle', $key, $item['item_key']);
                echo '</h4></div></div>';
            } else {
                echo '<div class="col-lg-6"><div class="faq-item"><h3>';
                public_field($item, 'title', $key, $item['item_key']);
                echo '</h3><div class="faq-content"><p>';
                public_field($item, 'content', $key, $item['item_key']);
                echo '</p></div></div></div>';
            }
        }
        echo '</div></div></section>';
        return;
    }

    if ($type === 'contact') {
        echo '<section id="contacto" class="contact section"><span id="ubicacion"></span>';
        public_section_title($section);
        $settings = $GLOBALS['cms_snapshot']['settings'];
        $mapUrl = cms_clean_url($settings['contact.map_url'] ?? '');
        echo '<div class="container"><div class="row gy-4"><div class="col-lg-5"><div class="info-wrap"><div class="info-item d-flex"><i class="bi bi-geo-alt"></i><div><h3>Ubicación</h3><p>' . auth_escape((string) ($settings['contact.address_line_1'] ?? '')) . '<br>' . auth_escape((string) ($settings['contact.address_line_2'] ?? '')) . '</p></div></div><div class="info-item d-flex"><i class="bi bi-telephone"></i><div><h3>Teléfono</h3><p>' . auth_escape((string) ($settings['contact.phone'] ?? '')) . '</p></div></div><div class="info-item d-flex"><i class="bi bi-envelope"></i><div><h3>Correo</h3><p>' . auth_escape((string) ($settings['contact.email'] ?? '')) . '</p></div></div>';
        if (str_starts_with($mapUrl, 'https://')) {
            echo '<iframe src="' . auth_escape($mapUrl) . '" title="Ubicación de SIETELSA" style="border:0;width:100%;height:270px" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>';
        }
        echo '</div></div><div class="col-lg-7"><form action="' . auth_escape(app_url('src/website/forms/contact.php')) . '" method="post" class="php-email-form" data-sietelsa-loading><input type="hidden" name="csrf_token" value="' . auth_escape(auth_csrf_token()) . '"><div class="row gy-4"><div class="col-md-6"><label for="contact-name">Nombre</label><input id="contact-name" name="name" class="form-control" required maxlength="150"></div><div class="col-md-6"><label for="contact-email">Correo</label><input id="contact-email" type="email" name="email" class="form-control" required maxlength="190"></div><div class="col-12"><label for="contact-subject">Asunto</label><input id="contact-subject" name="subject" class="form-control" required maxlength="200"></div><div class="col-12"><label for="contact-message">Mensaje</label><textarea id="contact-message" name="message" rows="6" class="form-control" required maxlength="5000"></textarea></div><div class="col-12 text-center"><div class="loading">Enviando</div><div class="error-message"></div><div class="sent-message">Mensaje enviado.</div><button type="submit">';
        public_field($section, 'button_text', $key);
        echo '</button></div></div></form></div></div></div></section>';
    }
}
