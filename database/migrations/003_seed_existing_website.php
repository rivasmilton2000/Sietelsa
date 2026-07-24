<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../includes/conexion.php';

$db = db_connection();
$db->beginTransaction();

function seed_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function seed_page(PDO $db, string $slug, string $name, array $seo, int $userId): int
{
    $statement = $db->prepare(
        'INSERT IGNORE INTO pages
            (slug, name, draft_seo, published_seo, status, is_visible, updated_by)
         VALUES (:slug, :name, :draft_seo, :published_seo, "published", 1, :updated_by)'
    );
    $statement->execute([
        'slug' => $slug,
        'name' => $name,
        'draft_seo' => seed_json($seo),
        'published_seo' => seed_json($seo),
        'updated_by' => $userId,
    ]);

    $find = $db->prepare('SELECT id FROM pages WHERE slug = :slug');
    $find->execute(['slug' => $slug]);

    return (int) $find->fetchColumn();
}

function seed_media(PDO $db, string $relativePath, string $alt, int $userId): ?int
{
    $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($absolutePath)) {
        return null;
    }

    $info = getimagesize($absolutePath);
    if ($info === false || !isset($info['mime'])) {
        return null;
    }

    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    $insert = $db->prepare(
        'INSERT IGNORE INTO media_library
            (original_name, stored_name, relative_path, mime_type, extension, width, height, file_size, sha256, alt_text, created_by)
         VALUES
            (:original_name, :stored_name, :relative_path, :mime_type, :extension, :width, :height, :file_size, :sha256, :alt_text, :created_by)'
    );
    $insert->execute([
        'original_name' => basename($absolutePath),
        'stored_name' => basename($absolutePath),
        'relative_path' => $relativePath,
        'mime_type' => $info['mime'],
        'extension' => $extension,
        'width' => (int) $info[0],
        'height' => (int) $info[1],
        'file_size' => filesize($absolutePath),
        'sha256' => hash_file('sha256', $absolutePath),
        'alt_text' => $alt,
        'created_by' => $userId,
    ]);

    $find = $db->prepare('SELECT id FROM media_library WHERE relative_path = :relative_path');
    $find->execute(['relative_path' => $relativePath]);
    $id = $find->fetchColumn();

    return $id === false ? null : (int) $id;
}

function seed_section(
    PDO $db,
    int $pageId,
    string $key,
    string $component,
    array $data,
    int $order,
    int $userId,
    ?int $mediaId = null
): int {
    $statement = $db->prepare(
        'INSERT IGNORE INTO page_sections
            (page_id, section_key, component_type, draft_data, published_data,
             draft_media_id, published_media_id, draft_sort_order, published_sort_order,
             draft_visible, published_visible, status, updated_by)
         VALUES
            (:page_id, :section_key, :component_type, :draft_data, :published_data,
             :draft_media_id, :published_media_id, :draft_sort_order, :published_sort_order,
             1, 1, "published", :updated_by)'
    );
    $statement->execute([
        'page_id' => $pageId,
        'section_key' => $key,
        'component_type' => $component,
        'draft_data' => seed_json($data),
        'published_data' => seed_json($data),
        'draft_media_id' => $mediaId,
        'published_media_id' => $mediaId,
        'draft_sort_order' => $order,
        'published_sort_order' => $order,
        'updated_by' => $userId,
    ]);

    $find = $db->prepare(
        'SELECT id FROM page_sections WHERE page_id = :page_id AND section_key = :section_key'
    );
    $find->execute(['page_id' => $pageId, 'section_key' => $key]);

    return (int) $find->fetchColumn();
}

function seed_item(
    PDO $db,
    int $sectionId,
    string $key,
    string $type,
    array $data,
    int $order,
    int $userId,
    ?int $mediaId = null
): void {
    $statement = $db->prepare(
        'INSERT IGNORE INTO content_items
            (section_id, item_key, item_type, draft_data, published_data,
             draft_media_id, published_media_id, draft_sort_order, published_sort_order,
             draft_visible, published_visible, status, updated_by)
         VALUES
            (:section_id, :item_key, :item_type, :draft_data, :published_data,
             :draft_media_id, :published_media_id, :draft_sort_order, :published_sort_order,
             1, 1, "published", :updated_by)'
    );
    $statement->execute([
        'section_id' => $sectionId,
        'item_key' => $key,
        'item_type' => $type,
        'draft_data' => seed_json($data),
        'published_data' => seed_json($data),
        'draft_media_id' => $mediaId,
        'published_media_id' => $mediaId,
        'draft_sort_order' => $order,
        'published_sort_order' => $order,
        'updated_by' => $userId,
    ]);
}

function seed_setting(
    PDO $db,
    string $key,
    string $group,
    string $type,
    mixed $value,
    int $userId
): void {
    $statement = $db->prepare(
        'INSERT IGNORE INTO site_settings
            (setting_key, setting_group, value_type, draft_value, published_value, status, is_public, updated_by)
         VALUES (:setting_key, :setting_group, :value_type, :draft_value, :published_value, "published", 1, :updated_by)'
    );
    $json = seed_json(['value' => $value]);
    $statement->execute([
        'setting_key' => $key,
        'setting_group' => $group,
        'value_type' => $type,
        'draft_value' => $json,
        'published_value' => $json,
        'updated_by' => $userId,
    ]);
}

function seed_navigation(
    PDO $db,
    string $key,
    string $label,
    string $url,
    int $order,
    string $access,
    bool $system,
    int $userId
): void {
    $statement = $db->prepare(
        'INSERT IGNORE INTO navigation_items
            (nav_key, draft_label, published_label, draft_url, published_url,
             draft_target, published_target, draft_access, published_access,
             draft_sort_order, published_sort_order, draft_visible, published_visible,
             is_system, status, updated_by)
         VALUES
            (:nav_key, :draft_label, :published_label, :draft_url, :published_url,
             "_self", "_self", :draft_access, :published_access,
             :draft_sort_order, :published_sort_order, 1, 1, :is_system, "published", :updated_by)'
    );
    $statement->execute([
        'nav_key' => $key,
        'draft_label' => $label,
        'published_label' => $label,
        'draft_url' => $url,
        'published_url' => $url,
        'draft_access' => $access,
        'published_access' => $access,
        'draft_sort_order' => $order,
        'published_sort_order' => $order,
        'is_system' => $system ? 1 : 0,
        'updated_by' => $userId,
    ]);
}

try {
    $adminId = (int) $db->query(
        "SELECT id FROM usuarios WHERE rol = 'admin' AND activo = 1 ORDER BY id LIMIT 1"
    )->fetchColumn();

    if ($adminId < 1) {
        throw new RuntimeException('Se requiere un administrador activo para migrar el contenido.');
    }

    $homeId = seed_page($db, 'inicio', 'Inicio', [
        'title' => 'SIETELSA | Servicios de Electricidad y Telecomunicaciones',
        'description' => 'Servicios de electricidad y telecomunicaciones de SIETELSA.',
        'keywords' => 'electricidad, telecomunicaciones, SIETELSA',
    ], $adminId);

    $media = [];
    $mediaPaths = [
        'hero' => ['src/website/assets/img/hero-bg.jpg', 'Instalaciones de SIETELSA'],
        'about' => ['src/website/assets/img/about.jpg', 'Equipo de trabajo'],
        'tab1' => ['src/website/assets/img/tabs-1.jpg', 'Característica uno'],
        'tab2' => ['src/website/assets/img/tabs-2.jpg', 'Característica dos'],
        'tab3' => ['src/website/assets/img/tabs-3.jpg', 'Característica tres'],
    ];
    for ($i = 1; $i <= 5; $i++) {
        $mediaPaths['testimonial' . $i] = [
            "src/website/assets/img/testimonials/testimonials-$i.jpg",
            "Testimonio $i",
        ];
    }
    foreach (['app', 'product', 'branding', 'books'] as $category) {
        for ($i = 1; $i <= 3; $i++) {
            $mediaPaths[$category . $i] = [
                "src/website/assets/img/portfolio/$category-$i.jpg",
                "Portafolio $category $i",
            ];
        }
    }
    for ($i = 1; $i <= 4; $i++) {
        $mediaPaths['team' . $i] = ["src/website/assets/img/team/team-$i.jpg", "Integrante $i"];
    }
    foreach ($mediaPaths as $key => [$path, $alt]) {
        $media[$key] = seed_media($db, $path, $alt, $adminId);
    }

    $heroId = seed_section($db, $homeId, 'hero', 'hero', [
        'title' => 'Welcome to Our Maxim',
        'subtitle' => 'We are team of talented designers making websites with Bootstrap',
        'button_text' => 'Get Started',
        'button_url' => '#nosotros',
    ], 10, $adminId, $media['hero']);

    $aboutId = seed_section($db, $homeId, 'nosotros', 'about', [
        'title' => 'About',
        'subtitle' => 'Necessitatibus eius consequatur ex aliquid fuga eum quidem sint consectetur velit',
        'heading' => 'Voluptatem dignissimos provident quasi corporis voluptates sit assumenda.',
        'lead' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
        'content' => 'Ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum',
    ], 20, $adminId, $media['about']);
    seed_item($db, $aboutId, 'about-feature-1', 'feature', [
        'title' => 'Ullamco laboris nisi ut aliquip consequat',
        'content' => 'Magni facilis facilis repellendus cum excepturi quaerat praesentium libre trade',
        'icon' => 'bi-diagram-3',
    ], 10, $adminId);
    seed_item($db, $aboutId, 'about-feature-2', 'feature', [
        'title' => 'Magnam soluta odio exercitationem reprehenderi',
        'content' => 'Quo totam dolorum at pariatur aut distinctio dolorum laudantium illo direna pasata redi',
        'icon' => 'bi-fullscreen-exit',
    ], 20, $adminId);

    $projectsId = seed_section($db, $homeId, 'proyectos', 'cards', [
        'title' => 'Proyectos',
        'subtitle' => '',
    ], 30, $adminId);
    $projectItems = [
        ['Lorem Ipsum', 'Ulamco laboris nisi ut aliquip ex ea commodo consequat. Et consectetur ducimus vero placeat'],
        ['Repellat Nihil', 'Dolorem est fugiat occaecati voluptate velit esse. Dicta veritatis dolor quod et vel dire leno para dest'],
        ['Ad ad velit qui', 'Molestiae officiis omnis illo asperiores. Aut doloribus vitae sunt debitis quo vel nam quis'],
        ['Repellendus molestiae', 'Inventore quo sint a sint rerum. Distinctio blanditiis deserunt quod soluta quod nam mider lando casa'],
        ['Sapiente Magnam', 'Vitae dolorem in deleniti ipsum omnis tempore voluptatem. Qui possimus est repellendus est quibusdam'],
        ['Facilis Impedit', 'Quis eum numquam veniam ea voluptatibus voluptas. Excepturi aut nostrum repudiandae voluptatibus corporis sequi'],
    ];
    foreach ($projectItems as $index => [$title, $content]) {
        seed_item($db, $projectsId, 'project-' . ($index + 1), 'project', [
            'title' => $title,
            'content' => $content,
            'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
        ], ($index + 1) * 10, $adminId);
    }

    $tabsId = seed_section($db, $homeId, 'caracteristicas', 'tabs', [
        'title' => 'Características',
        'subtitle' => '',
    ], 40, $adminId);
    $tabs = [
        ['Modi sit est dela pireda nest', 'Ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur', 'bi-binoculars', 'tab1'],
        ['Unde praesenti mara setra le', 'Recusandae atque nihil. Delectus vitae non similique magnam molestiae sapiente similique tenetur aut voluptates sed voluptas ipsum voluptas', 'bi-box-seam', 'tab2'],
        ['Pariatur explica nitro dela', 'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Debitis nulla est maxime voluptas dolor aut', 'bi-brightness-high', 'tab3'],
    ];
    foreach ($tabs as $index => [$title, $content, $icon, $mediaKey]) {
        seed_item($db, $tabsId, 'tab-' . ($index + 1), 'tab', [
            'title' => $title,
            'content' => $content,
            'icon' => $icon,
        ], ($index + 1) * 10, $adminId, $media[$mediaKey]);
    }

    $servicesId = seed_section($db, $homeId, 'servicios', 'services', [
        'title' => 'Services',
        'subtitle' => 'Necessitatibus eius consequatur ex aliquid fuga eum quidem sint consectetur velit',
    ], 50, $adminId);
    $services = [
        ['Nesciunt Mete', 'Provident nihil minus qui consequatur non omnis maiores. Eos accusantium minus dolores iure perferendis tempore et consequatur.', 'bi-activity', 'cyan'],
        ['Eosle Commodi', 'Ut autem aut autem non a. Sint sint sit facilis nam iusto sint. Libero corrupti neque eum hic non ut nesciunt dolorem.', 'bi-broadcast', 'orange'],
        ['Ledo Markt', 'Ut excepturi voluptatem nisi sed. Quidem fuga consequatur. Minus ea aut. Vel qui id voluptas adipisci eos earum corrupti.', 'bi-easel', 'teal'],
        ['Asperiores Commodi', 'Non et temporibus minus omnis sed dolor esse consequatur. Cupiditate sed error ea fuga sit provident adipisci neque.', 'bi-bounding-box-circles', 'red'],
        ['Velit Doloremque.', 'Cumque et suscipit saepe. Est maiores autem enim facilis ut aut ipsam corporis aut. Sed animi at autem alias eius labore.', 'bi-calendar4-week', 'indigo'],
        ['Dolori Architecto', 'Hic molestias ea quibusdam eos. Fugiat enim doloremque aut neque non et debitis iure. Corrupti recusandae ducimus enim.', 'bi-chat-square-text', 'pink'],
    ];
    foreach ($services as $index => [$title, $content, $icon, $color]) {
        seed_item($db, $servicesId, 'service-' . ($index + 1), 'service', [
            'title' => $title,
            'content' => $content,
            'icon' => $icon,
            'color' => $color,
            'button_text' => 'Learn More',
            'url' => 'src/website/service-details.php',
        ], ($index + 1) * 10, $adminId);
    }

    $testimonialsId = seed_section($db, $homeId, 'testimonios', 'testimonials', [
        'title' => 'Testimonials',
        'subtitle' => 'Necessitatibus eius consequatur ex aliquid fuga eum quidem sint consectetur velit',
    ], 60, $adminId);
    $testimonials = [
        ['Saul Goodman', 'Ceo & Founder', 'Proin iaculis purus consequat sem cure digni ssim donec porttitora entum suscipit rhoncus. Accusantium quam, ultricies eget id, aliquam eget nibh et. Maecen aliquam, risus at semper.'],
        ['Sara Wilsson', 'Designer', 'Export tempor illum tamen malis malis eram quae irure esse labore quem cillum quid malis quorum velit fore eram velit sunt aliqua noster fugiat irure amet legam anim culpa.'],
        ['Jena Karlis', 'Store Owner', 'Enim nisi quem export duis labore cillum quae magna enim sint quorum nulla quem veniam duis minim tempor labore quem eram duis noster aute amet eram fore quis sint minim.'],
        ['Matt Brandon', 'Freelancer', 'Fugiat enim eram quae cillum dolore dolor amet nulla culpa multos export minim fugiat dolor enim duis veniam ipsum anim magna sunt elit fore quem dolore labore illum veniam.'],
        ['John Larson', 'Entrepreneur', 'Quis quorum aliqua sint quem legam fore sunt eram irure aliqua veniam tempor noster veniam sunt culpa nulla illum cillum fugiat legam esse veniam culpa fore nisi cillum quid.'],
    ];
    foreach ($testimonials as $index => [$name, $position, $quote]) {
        seed_item($db, $testimonialsId, 'testimonial-' . ($index + 1), 'testimonial', [
            'title' => $name,
            'subtitle' => $position,
            'content' => $quote,
        ], ($index + 1) * 10, $adminId, $media['testimonial' . ($index + 1)]);
    }

    $portfolioId = seed_section($db, $homeId, 'portafolio', 'portfolio', [
        'title' => 'Portfolio',
        'subtitle' => 'Necessitatibus eius consequatur ex aliquid fuga eum quidem sint consectetur velit',
        'filters' => ['all' => 'All', 'app' => 'App', 'product' => 'Product', 'branding' => 'Branding', 'books' => 'Books'],
    ], 70, $adminId);
    $position = 0;
    foreach (['app', 'product', 'branding', 'books'] as $category) {
        for ($i = 1; $i <= 3; $i++) {
            $position++;
            seed_item($db, $portfolioId, "$category-$i", 'portfolio', [
                'title' => ucfirst($category) . " $i",
                'category' => $category,
                'url' => 'src/website/portfolio-details.php',
            ], $position * 10, $adminId, $media[$category . $i]);
        }
    }

    $teamId = seed_section($db, $homeId, 'equipo', 'team', [
        'title' => 'Team',
        'subtitle' => 'Necessitatibus eius consequatur ex aliquid fuga eum quidem sint consectetur velit',
    ], 80, $adminId);
    $team = [
        ['Walter White', 'Chief Executive Officer'],
        ['Sarah Jhonson', 'Product Manager'],
        ['William Anderson', 'CTO'],
        ['Amanda Jepson', 'Accountant'],
    ];
    foreach ($team as $index => [$name, $position]) {
        seed_item($db, $teamId, 'team-' . ($index + 1), 'team', [
            'title' => $name,
            'subtitle' => $position,
            'twitter' => '',
            'facebook' => '',
            'instagram' => '',
            'linkedin' => '',
        ], ($index + 1) * 10, $adminId, $media['team' . ($index + 1)]);
    }

    $faqId = seed_section($db, $homeId, 'preguntas', 'faq', [
        'title' => 'Frequently Asked Questions',
        'subtitle' => 'Necessitatibus eius consequatur ex aliquid fuga eum quidem sint consectetur velit',
    ], 90, $adminId);
    $faqs = [
        ['Non consectetur a erat nam at lectus urna duis?', 'Feugiat pretium nibh ipsum consequat. Tempus iaculis urna id volutpat lacus laoreet non curabitur gravida. Venenatis lectus magna fringilla urna porttitor rhoncus dolor purus non.'],
        ['Feugiat scelerisque varius morbi enim nunc faucibus?', 'Dolor sit amet consectetur adipiscing elit pellentesque habitant morbi. Id interdum velit laoreet id donec ultrices. Fringilla phasellus faucibus scelerisque eleifend donec pretium.'],
        ['Dolor sit amet consectetur adipiscing elit pellentesque?', 'Eleifend mi in nulla posuere sollicitudin aliquam ultrices sagittis orci. Faucibus pulvinar elementum integer enim.'],
        ['Ac odio tempor orci dapibus. Aliquam eleifend mi in nulla?', 'Dolor sit amet consectetur adipiscing elit pellentesque habitant morbi. Id interdum velit laoreet id donec ultrices.'],
        ['Tempus quam pellentesque nec nam aliquam sem et tortor?', 'Molestie a iaculis at erat pellentesque adipiscing commodo. Dignissim suspendisse in est ante in.'],
        ['Perspiciatis quod quo quos nulla quo illum ullam?', 'Enim ea facilis quaerat voluptas quidem et dolorem. Quis et consequatur non sed in suscipit sequi.'],
    ];
    foreach ($faqs as $index => [$question, $answer]) {
        seed_item($db, $faqId, 'faq-' . ($index + 1), 'faq', [
            'title' => $question,
            'content' => $answer,
        ], ($index + 1) * 10, $adminId);
    }

    seed_section($db, $homeId, 'contacto', 'contact', [
        'title' => 'Contact',
        'subtitle' => 'Necessitatibus eius consequatur ex aliquid fuga eum quidem sint consectetur velit',
        'name_label' => 'Your Name',
        'email_label' => 'Your Email',
        'subject_label' => 'Subject',
        'message_label' => 'Message',
        'button_text' => 'Send Message',
    ], 100, $adminId);

    $settings = [
        ['site.name', 'general', 'text', 'SIETELSA'],
        ['site.logo', 'general', 'url', 'img/logoSietelsa.png'],
        ['contact.address_line_1', 'contact', 'text', 'A108 Adam Street'],
        ['contact.address_line_2', 'contact', 'text', 'New York, NY 535022'],
        ['contact.phone', 'contact', 'phone', '+1 5589 55488 55'],
        ['contact.email', 'contact', 'email', 'info@example.com'],
        ['contact.hours', 'contact', 'text', 'Lunes a viernes, 8:00 a.m. a 5:00 p.m.'],
        ['contact.map_url', 'contact', 'url', 'https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d48389.78314118045!2d-74.006138!3d40.710059!3m2!1i1024!1i768!4f13.1'],
        ['footer.links_title', 'footer', 'text', 'Useful Links'],
        ['footer.services_title', 'footer', 'text', 'Our Services'],
        ['footer.newsletter_title', 'footer', 'text', 'Our Newsletter'],
        ['footer.newsletter_text', 'footer', 'textarea', 'Subscribe to our newsletter and receive the latest news about our products and services!'],
        ['footer.copyright', 'footer', 'text', 'Todos los derechos reservados'],
        ['social.twitter', 'social', 'url', ''],
        ['social.facebook', 'social', 'url', ''],
        ['social.instagram', 'social', 'url', ''],
        ['social.linkedin', 'social', 'url', ''],
    ];
    foreach ($settings as [$key, $group, $type, $value]) {
        seed_setting($db, $key, $group, $type, $value, $adminId);
    }

    $navigation = [
        ['servicios', 'SERVICIOS', '#servicios', 10, 'public', false],
        ['portafolio', 'PORTAFOLIO', '#portafolio', 20, 'public', false],
        ['nosotros', 'NOSOTROS', '#nosotros', 30, 'public', false],
        ['proyectos', 'PROYECTOS', '#proyectos', 40, 'public', false],
        ['ubicacion', 'UBICACIÓN', '#ubicacion', 50, 'public', false],
        ['contacto', 'CONTACTO', '#contacto', 60, 'public', false],
        ['auth', 'INICIAR SESIÓN', 'src/login/login.php', 70, 'guest', true],
    ];
    foreach ($navigation as [$key, $label, $url, $order, $access, $system]) {
        seed_navigation($db, $key, $label, $url, $order, $access, $system, $adminId);
    }

    $db->commit();
    echo "Contenido inicial migrado correctamente.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "No fue posible migrar el contenido: {$exception->getMessage()}\n");
    exit(1);
}
